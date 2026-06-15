<?php

namespace App\Services\Agent;

use App\Models\AgentConversation;
use App\Models\User;
use App\Services\DeepSeek\DeepSeekService;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WmsAgentService
{
    public function __construct(
        protected DeepSeekService $deepSeek,
        protected AgentConversationService $conversations,
        protected AgentToolRegistry $toolRegistry,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   conversation_id: string,
     *   reply: array{content: string, format: string, attachments?: array<int, array<string, mixed>>},
     *   message?: string
     * }
     */
    public function handle(User $user, string $message, ?string $conversationId = null): array
    {
        if (! config('agent.enabled')) {
            return $this->error('Agent chat belum diaktifkan.');
        }

        if (! $this->deepSeek->isConfigured()) {
            return $this->error('DeepSeek belum dikonfigurasi. Set DEEPSEEK_API_KEY.');
        }

        $message = trim($message);

        if ($message === '') {
            return $this->error('Pesan tidak boleh kosong.');
        }

        if (mb_strlen($message) > (int) config('agent.max_message_length', 2000)) {
            return $this->error('Pesan terlalu panjang.');
        }

        $conversation = $this->conversations->resolve($user, $conversationId, $message);
        $context = AgentContext::fromUser($user, 'web', $conversation->id);

        $this->conversations->appendMessage($conversation, 'user', $message);

        $tools = $this->toolRegistry->openAiToolsForContext($context);
        $systemPrompt = $this->systemPrompt($context, $tools);
        $maxRounds = (int) config('agent.max_tool_rounds', 5);
        $attachments = [];

        try {
            for ($round = 0; $round < $maxRounds; $round++) {
                $chatMessages = $this->conversations->buildChatMessages($conversation, $systemPrompt);

                $response = $this->deepSeek->chat(
                    messages: $chatMessages,
                    tools: $tools,
                );

                $assistantMessage = data_get($response, 'choices.0.message', []);
                $toolCalls = data_get($assistantMessage, 'tool_calls', []);
                $content = data_get($assistantMessage, 'content');

                if ($toolCalls === []) {
                    $finalContent = is_string($content) && trim($content) !== ''
                        ? trim($content)
                        : 'Maaf, saya tidak dapat memproses permintaan tersebut.';

                    $this->conversations->appendMessage($conversation, 'assistant', $finalContent);

                    return [
                        'success' => true,
                        'conversation_id' => $conversation->id,
                        'reply' => [
                            'content' => $finalContent,
                            'format' => 'markdown',
                            'attachments' => $attachments,
                        ],
                    ];
                }

                $this->conversations->appendMessage(
                    $conversation,
                    'assistant',
                    is_string($content) ? $content : null,
                    null,
                    ['tool_calls' => $toolCalls],
                );

                foreach ($toolCalls as $toolCall) {
                    $toolCallId = (string) data_get($toolCall, 'id', '');
                    $toolName = (string) data_get($toolCall, 'function.name', '');
                    $argumentsJson = (string) data_get($toolCall, 'function.arguments', '{}');
                    $arguments = json_decode($argumentsJson, true);

                    if (! is_array($arguments)) {
                        $arguments = [];
                    }

                    $toolResult = $this->executeTool($conversation, $context, $toolName, $arguments);
                    $toolContent = json_encode($toolResult, JSON_UNESCAPED_UNICODE);

                    $tableAttachment = $this->buildAttachmentFromToolResult($toolName, $toolResult);

                    if ($tableAttachment !== null) {
                        $attachments[] = $tableAttachment;
                    }

                    $this->conversations->appendMessage(
                        $conversation,
                        'tool',
                        $toolContent ?: '{}',
                        $toolName,
                        ['tool_call_id' => $toolCallId, 'arguments' => $arguments],
                    );

                    $chatMessages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => $toolContent ?: '{}',
                    ];
                }
            }

            $fallback = 'Permintaan membutuhkan terlalu banyak langkah. Coba pertanyaan yang lebih spesifik.';
            $this->conversations->appendMessage($conversation, 'assistant', $fallback);

            return [
                'success' => true,
                'conversation_id' => $conversation->id,
                'reply' => [
                    'content' => $fallback,
                    'format' => 'markdown',
                    'attachments' => $attachments,
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('WMS Agent error', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Gagal memproses pesan: '.$e->getMessage(), $conversation);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $tools
     */
    protected function systemPrompt(AgentContext $context, array $tools): string
    {
        $userName = trim($context->user->first_name.' '.$context->user->last_name);
        $branch = $context->branchName ?? 'tidak diset';
        $toolNames = collect($tools)->map(fn ($t) => data_get($t, 'function.name'))->filter()->implode(', ');

        return <<<PROMPT
Kamu asisten WMS (Warehouse Management System) untuk admin Indonesia.

Konteks user:
- Nama: {$userName}
- Cabang aktif: {$branch}
- Channel: web admin panel

Aturan:
1. Jawab dalam Bahasa Indonesia, ringkas dan profesional.
2. Untuk data produk, stok, customer, atau penjualan — WAJIB panggil tool yang tersedia. Jangan mengarang angka.
3. Jika user minta aksi write (buat transaksi, adjustment stok, dll) — jelaskan bahwa fitur belum tersedia dan arahkan ke modul WMS yang sesuai.
4. Jika cabang belum diset, minta user pilih cabang di profil.
5. Format angka rupiah dengan pemisah ribuan jika relevan.

Tool tersedia: {$toolNames}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function executeTool(
        AgentConversation $conversation,
        AgentContext $context,
        string $toolName,
        array $arguments,
    ): array {
        $tool = $this->toolRegistry->get($toolName);

        if ($tool === null) {
            return [
                'success' => false,
                'message' => "Tool \"{$toolName}\" tidak dikenali.",
            ];
        }

        if (! $context->hasPermission($tool->requiredPermission())) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk operasi ini.',
            ];
        }

        $started = microtime(true);

        try {
            $result = $tool->execute($arguments, $context);
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        $this->conversations->logToolCall(
            $conversation,
            $context->user,
            $toolName,
            $arguments,
            $result,
            $durationMs,
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $toolResult
     * @return array<string, mixed>|null
     */
    protected function buildAttachmentFromToolResult(string $toolName, array $toolResult): ?array
    {
        $items = $toolResult['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return null;
        }

        return match ($toolName) {
            'search_product' => [
                'type' => 'table',
                'headers' => ['Produk', 'SKU', 'Harga', 'Stok'],
                'rows' => collect($items)->map(fn ($item) => [
                    (string) ($item['label'] ?? '-'),
                    (string) ($item['sku'] ?? '-'),
                    (string) ($item['price_formatted'] ?? $item['price'] ?? '-'),
                    (string) ($item['stock'] ?? '-'),
                ])->all(),
            ],
            'get_stock' => [
                'type' => 'table',
                'headers' => ['Produk', 'SKU', 'Stok'],
                'rows' => collect($items)->map(fn ($item) => [
                    (string) ($item['label'] ?? '-'),
                    (string) ($item['sku'] ?? '-'),
                    (string) ($item['stock'] ?? '-'),
                ])->all(),
            ],
            'search_customer' => [
                'type' => 'table',
                'headers' => ['Kode', 'Nama'],
                'rows' => collect($items)->map(fn ($item) => [
                    (string) ($item['code'] ?? '-'),
                    (string) ($item['name'] ?? '-'),
                ])->all(),
            ],
            default => null,
        };
    }

    /**
     * @return array{success: false, message: string, conversation_id?: string, reply?: array<string, mixed>}
     */
    protected function error(string $message, ?AgentConversation $conversation = null): array
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($conversation !== null) {
            $payload['conversation_id'] = $conversation->id;
        }

        return $payload;
    }
}
