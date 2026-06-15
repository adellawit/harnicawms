<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class TelegramBotService
{
    public function isConfigured(): bool
    {
        if (! config('telegram.enabled')) {
            return false;
        }

        if (config('telegram.mock')) {
            return true;
        }

        return filled(config('telegram.bot_token'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function request(string $method, array $payload = []): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Telegram bot belum dikonfigurasi.');
        }

        if (config('telegram.mock')) {
            Log::info('[Telegram MOCK] '.$method, $payload);

            if ($method === 'sendMessage' && isset($payload['text']) && app()->runningInConsole()) {
                $plain = strip_tags(str_replace(['<b>', '</b>', '<code>', '</code>'], '', (string) $payload['text']));
                fwrite(STDOUT, PHP_EOL.'[BOT] '.$plain.PHP_EOL);
            }

            return ['ok' => true, 'result' => ['message_id' => 1], 'mock' => true];
        }

        $token = config('telegram.bot_token');
        $baseUrl = rtrim(config('telegram.api_base_url'), '/');

        $response = Http::timeout(20)
            ->acceptJson()
            ->post("{$baseUrl}/bot{$token}/{$method}", $payload);

        $data = $response->json();

        if (! $response->successful() || ($data['ok'] ?? false) !== true) {
            Log::warning('Telegram API error', [
                'method' => $method,
                'status' => $response->status(),
                'response' => $data ?? $response->body(),
            ]);

            throw new RuntimeException('Telegram API error: '.($data['description'] ?? $response->status()));
        }

        return $data;
    }

    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->request('sendMessage', $payload);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): array
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        return $this->request('answerCallbackQuery', $payload);
    }

    /**
     * @param  array<int, array<int, array<string, string>>>  $rows
     */
    public function inlineKeyboard(array $rows): array
    {
        return [
            'inline_keyboard' => $rows,
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $buttons
     */
    public function inlineRow(array $buttons): array
    {
        return [$buttons];
    }

    public function button(string $text, string $callbackData): array
    {
        return [
            'text' => $text,
            'callback_data' => $this->truncateCallbackData($callbackData),
        ];
    }

    protected function truncateCallbackData(string $data): string
    {
        return mb_substr($data, 0, 64);
    }
}
