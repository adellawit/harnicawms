<?php

namespace App\Services\Ai;

use App\Models\Ai\AgentConversation;
use App\Models\Ai\AgentMessage;
use App\Models\Ai\AgentToolLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AgentConversationService
{
    public function findForUser(string $conversationId, User $user): ?AgentConversation
    {
        return AgentConversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * @return Collection<int, AgentConversation>
     */
    public function listForUser(User $user, int $limit = 30): Collection
    {
        $limit = max(1, min($limit, 50));

        return AgentConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'title', 'branch_id', 'updated_at']);
    }

    /**
     * Compact thread list for the in-app widget (this user only).
     *
     * @return array<int, array{id: string, title: string, snippet: string, updated_at: string|null, updated_label: string}>
     */
    public function listSummariesForUser(User $user, int $limit = 30): array
    {
        $conversations = $this->listForUser($user, $limit);

        if ($conversations->isEmpty()) {
            return [];
        }

        $snippets = [];

        AgentMessage::query()
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->where('role', 'user')
            ->whereNotNull('content')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['conversation_id', 'content'])
            ->each(function (AgentMessage $row) use (&$snippets): void {
                if (! isset($snippets[$row->conversation_id])) {
                    $snippets[$row->conversation_id] = trim((string) $row->content);
                }
            });

        return $conversations->map(function (AgentConversation $row) use ($snippets): array {
            $fromTitle = trim((string) ($row->title ?? ''));
            $fromFirst = trim((string) ($snippets[$row->id] ?? ''));
            $snippet = $fromTitle !== '' ? $fromTitle : Str::limit($fromFirst, 80, '…');

            if ($snippet === '') {
                $snippet = 'Percakapan';
            }

            $updatedAt = $row->updated_at;

            return [
                'id' => $row->id,
                'title' => $snippet,
                'snippet' => $snippet,
                'updated_at' => $updatedAt?->toIso8601String(),
                'updated_label' => $updatedAt
                    ? $updatedAt->copy()->locale('id')->diffForHumans()
                    : '',
            ];
        })->values()->all();
    }

    /**
     * Visible user/assistant turns for the widget (skips tool rows and empty drafts).
     *
     * @return array<int, array{id: string, role: string, content: string, created_at: string|null}>
     */
    public function widgetMessages(AgentConversation $conversation, int $limit = 50): array
    {
        $limit = max(1, min($limit, 100));

        $rows = AgentMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'created_at']);

        if ($rows->count() > $limit) {
            $rows = $rows->slice($rows->count() - $limit)->values();
        }

        return $rows->map(static fn (AgentMessage $row): array => [
            'id' => $row->id,
            'role' => $row->role,
            'content' => (string) $row->content,
            'created_at' => $row->created_at?->toIso8601String(),
        ])->all();
    }

    public function create(User $user, ?string $branchId, ?string $title = null): AgentConversation
    {
        return AgentConversation::query()->create([
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'title' => $title,
        ]);
    }

    public function resolve(User $user, ?string $conversationId, string $firstMessage): AgentConversation
    {
        if ($conversationId !== null) {
            $existing = $this->findForUser($conversationId, $user);

            if ($existing !== null) {
                return $existing;
            }
        }

        $title = Str::limit(trim($firstMessage), 80, '…');

        return $this->create($user, $user->getBranchIdForTransaction(), $title);
    }

    public function appendMessage(
        AgentConversation $conversation,
        string $role,
        ?string $content = null,
        ?string $toolName = null,
        ?array $toolPayload = null,
    ): AgentMessage {
        $message = AgentMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'tool_name' => $toolName,
            'tool_payload' => $toolPayload,
            'created_at' => now(),
        ]);

        $conversation->touch();

        return $message;
    }

    /**
     * @return array<int, array{role: string, content: string|null, tool_calls?: array<int, array<string, mixed>>, tool_call_id?: string}>
     */
    public function buildChatMessages(AgentConversation $conversation, string $systemPrompt): array
    {
        $rows = AgentMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $this->toProviderMessages($rows),
        );
    }

    /**
     * Pair each assistant tool_calls block with its tool results by id.
     *
     * created_at on auth.agent_messages is timestamp(0), so several rows in the
     * same second share a timestamp. Ordering by time alone can emit a tool
     * message before its assistant, which DeepSeek rejects with 400.
     *
     * @param  Collection<int, AgentMessage>  $rows
     * @return array<int, array{role: string, content: string|null, tool_calls?: array<int, array<string, mixed>>, tool_call_id?: string}>
     */
    public function toProviderMessages(Collection $rows): array
    {
        $toolsByCallId = [];

        foreach ($rows as $row) {
            if ($row->role !== 'tool') {
                continue;
            }

            $callId = trim((string) data_get($row->tool_payload, 'tool_call_id', ''));

            if ($callId !== '') {
                $toolsByCallId[$callId] = $row;
            }
        }

        $messages = [];

        foreach ($rows as $row) {
            if ($row->role === 'tool') {
                continue;
            }

            $toolCalls = $this->toolCallsOf($row);

            if ($row->role === 'assistant' && $toolCalls !== []) {
                $messages[] = [
                    'role' => 'assistant',
                    'tool_calls' => $toolCalls,
                    'content' => ($row->content !== null && $row->content !== '') ? $row->content : null,
                ];

                foreach ($toolCalls as $call) {
                    $callId = trim((string) ($call['id'] ?? ''));

                    if ($callId === '') {
                        continue;
                    }

                    $tool = $toolsByCallId[$callId] ?? null;
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $callId,
                        'content' => $tool !== null
                            ? (string) ($tool->content ?? '{}')
                            : '{"success":false,"message":"Hasil tool tidak tersedia."}',
                    ];
                    unset($toolsByCallId[$callId]);
                }

                continue;
            }

            if ($row->role === 'user') {
                $this->closeOpenToolRound($messages);
            }

            $messages[] = [
                'role' => $row->role,
                'content' => (string) ($row->content ?? ''),
            ];
        }

        return $this->dropIncompleteToolRounds($messages);
    }

    /**
     * DeepSeek menolak pesan role=tool yang tidak langsung menjawab assistant.tool_calls.
     * Itu terjadi kalau request sebelumnya timeout di tengah putaran tool.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    public function dropIncompleteToolRounds(array $messages): array
    {
        $sanitized = [];
        $count = count($messages);

        for ($index = 0; $index < $count; $index++) {
            $message = $messages[$index];
            $toolCalls = $message['tool_calls'] ?? [];

            if (($message['role'] ?? '') === 'tool') {
                continue;
            }

            if (($message['role'] ?? '') !== 'assistant' || ! is_array($toolCalls) || $toolCalls === []) {
                $sanitized[] = $message;

                continue;
            }

            $pendingIds = [];

            foreach ($toolCalls as $toolCall) {
                $callId = (string) ($toolCall['id'] ?? '');

                if ($callId !== '') {
                    $pendingIds[$callId] = true;
                }
            }

            $toolMessages = [];
            $cursor = $index + 1;

            while ($cursor < $count && ($messages[$cursor]['role'] ?? '') === 'tool') {
                $callId = (string) ($messages[$cursor]['tool_call_id'] ?? '');

                if ($callId !== '' && isset($pendingIds[$callId])) {
                    $toolMessages[] = $messages[$cursor];
                    unset($pendingIds[$callId]);
                }

                $cursor++;
            }

            $index = $cursor - 1;

            if ($pendingIds !== []) {
                continue;
            }

            $sanitized[] = $message;

            foreach ($toolMessages as $toolMessage) {
                $sanitized[] = $toolMessage;
            }
        }

        return $sanitized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function toolCallsOf(AgentMessage $row): array
    {
        $payload = $row->tool_payload;

        if (! is_array($payload)) {
            return [];
        }

        $toolCalls = $payload['tool_calls'] ?? [];

        if (! is_array($toolCalls) || $toolCalls === []) {
            return [];
        }

        $normalized = [];

        foreach ($toolCalls as $call) {
            if (! is_array($call)) {
                continue;
            }

            $call['type'] = $call['type'] ?? 'function';
            $normalized[] = $call;
        }

        return $normalized;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     */
    protected function closeOpenToolRound(array &$messages): void
    {
        $last = $messages !== [] ? $messages[array_key_last($messages)] : null;

        if (($last['role'] ?? '') !== 'tool') {
            return;
        }

        $decoded = json_decode((string) ($last['content'] ?? ''), true);
        $content = is_array($decoded) && filled($decoded['message'] ?? null)
            ? (string) $decoded['message']
            : 'Siap, sudah dikerjakan.';

        $messages[] = [
            'role' => 'assistant',
            'content' => $content,
        ];
    }

    public function logToolCall(
        AgentConversation $conversation,
        User $user,
        string $toolName,
        array $input,
        array $output,
        int $durationMs,
    ): void {
        AgentToolLog::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'tool_name' => $toolName,
            'input' => $input,
            'output' => $output,
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);
    }

    public function deleteForUser(string $conversationId, User $user): bool
    {
        $conversation = $this->findForUser($conversationId, $user);

        if ($conversation === null) {
            return false;
        }

        return (bool) $conversation->delete();
    }
}
