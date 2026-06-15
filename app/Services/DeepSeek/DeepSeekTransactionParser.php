<?php

namespace App\Services\DeepSeek;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class DeepSeekTransactionParser
{
    public function __construct(
        protected DeepSeekService $deepSeek,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function parse(string $message, string $productContext = ''): array
    {
        $systemPrompt = $this->systemPrompt();
        $userContent = trim($message);

        if ($productContext !== '') {
            $userContent = "Produk populer cabang (referensi):\n{$productContext}\n\nPesan kasir:\n{$message}";
        }

        $tools = [$this->parseTransactionTool()];
        $toolChoice = [
            'type' => 'function',
            'function' => ['name' => 'parse_transaction_intent'],
        ];

        $response = $this->deepSeek->chat(
            messages: [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            tools: $tools,
            toolChoice: $toolChoice,
        );

        $toolCalls = data_get($response, 'choices.0.message.tool_calls', []);

        if ($toolCalls === []) {
            $content = data_get($response, 'choices.0.message.content');
            if (is_string($content) && $content !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            throw new RuntimeException('DeepSeek tidak mengembalikan hasil parse.');
        }

        $arguments = data_get($toolCalls, '0.function.arguments');

        if (! is_string($arguments) || $arguments === '') {
            throw new RuntimeException('DeepSeek tool arguments kosong.');
        }

        $parsed = json_decode($arguments, true);

        if (! is_array($parsed)) {
            Log::warning('DeepSeek invalid JSON arguments', ['arguments' => $arguments]);
            throw new RuntimeException('DeepSeek mengembalikan JSON tidak valid.');
        }

        return $parsed;
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
Kamu parser transaksi POS untuk kasir Indonesia di Telegram.

Tugasmu: ekstrak intent transaksi dari pesan kasir, lalu panggil function parse_transaction_intent.

Aturan:
1. JANGAN invent SKU, harga, atau nama produk resmi — hanya salin query natural user di product_query.
2. Quantity: angka dari pesan (dua=2, sepuluh=10). Jika tidak jelas, quantity=null.
3. Customer: nama orang jika disebut ("untuk Budi", "buat pak Joko", "customer Andi"). Jika tidak ada, customer_name=null.
4. Payment hint: tunai/cash→cash, transfer/tf→transfer, qris→qris, tempo/kredit→credit.
5. Jika pesan bukan transaksi (salam, bantuan, perintah kosong), intent=unknown.
6. Catat ambiguities jika qty/customer/produk tidak jelas.

Contoh:
Input: "3 kopi arabica sama 2 susu uht untuk ibu rina bayar tunai"
→ customer_name: "ibu rina", items dengan product_query dan quantity, payment_hint: cash

Input: "halo"
→ intent: unknown
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseTransactionTool(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'parse_transaction_intent',
                'strict' => config('deepseek.use_strict_tools', true),
                'description' => 'Parse natural language POS transaction from cashier message.',
                'parameters' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['intent', 'customer_name', 'items', 'payment_hint', 'ambiguities'],
                    'properties' => [
                        'intent' => [
                            'type' => 'string',
                            'enum' => ['create_transaction', 'add_items', 'set_customer', 'set_payment', 'cancel', 'unknown'],
                        ],
                        'customer_name' => [
                            'type' => ['string', 'null'],
                        ],
                        'items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['product_query', 'quantity', 'unit_hint'],
                                'properties' => [
                                    'product_query' => ['type' => 'string'],
                                    'quantity' => ['type' => ['number', 'null']],
                                    'unit_hint' => ['type' => ['string', 'null']],
                                ],
                            ],
                        ],
                        'payment_hint' => [
                            'type' => ['string', 'null'],
                        ],
                        'ambiguities' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
