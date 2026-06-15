<?php

namespace App\Services\DeepSeek;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DeepSeekService
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $tools = [], ?array $toolChoice = null): array
    {
        if (! config('deepseek.enabled') || ! config('deepseek.api_key')) {
            throw new RuntimeException('DeepSeek belum dikonfigurasi.');
        }

        $useStrict = config('deepseek.use_strict_tools') && $tools !== [];
        $baseUrl = rtrim($useStrict ? config('deepseek.beta_url') : config('deepseek.base_url'), '/');

        $payload = [
            'model' => config('deepseek.model'),
            'messages' => $messages,
            'max_tokens' => config('deepseek.max_tokens'),
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            if ($toolChoice !== null) {
                $payload['tool_choice'] = $toolChoice;
            }
        }

        $response = Http::timeout(config('deepseek.timeout'))
            ->withToken(config('deepseek.api_key'))
            ->acceptJson()
            ->post("{$baseUrl}/chat/completions", $payload);

        if (! $response->successful()) {
            $body = $response->json() ?? $response->body();
            $detail = is_array($body) ? (string) data_get($body, 'error.message', json_encode($body)) : (string) $body;

            Log::warning('DeepSeek API error', [
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new RuntimeException('DeepSeek API error: '.$response->status().' — '.$detail);
        }

        return $response->json();
    }

    public function isConfigured(): bool
    {
        return config('deepseek.enabled') && filled(config('deepseek.api_key'));
    }
}
