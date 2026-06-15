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
    public function listForUser(User $user, int $limit = 20): Collection
    {
        return AgentConversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'title', 'branch_id', 'updated_at']);
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
     * @return array<int, array{role: string, content: string|null, tool_calls?: array<int, array<string, mixed>>}>
     */
    public function buildChatMessages(AgentConversation $conversation, string $systemPrompt): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        $rows = AgentMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get();

        foreach ($rows as $row) {
            if ($row->role === 'tool') {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) data_get($row->tool_payload, 'tool_call_id', ''),
                    'content' => (string) ($row->content ?? ''),
                ];

                continue;
            }

            if ($row->role === 'assistant' && is_array($row->tool_payload) && ($row->tool_payload['tool_calls'] ?? []) !== []) {
                $message = [
                    'role' => 'assistant',
                    'tool_calls' => $row->tool_payload['tool_calls'],
                ];

                if ($row->content !== null && $row->content !== '') {
                    $message['content'] = $row->content;
                } else {
                    $message['content'] = null;
                }

                $messages[] = $message;

                continue;
            }

            $messages[] = [
                'role' => $row->role,
                'content' => (string) ($row->content ?? ''),
            ];
        }

        return $messages;
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
