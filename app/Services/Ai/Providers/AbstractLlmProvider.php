<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\LlmProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

abstract class AbstractLlmProvider implements LlmProviderInterface
{
    abstract protected function providerKey(): string;

    abstract protected function resolveBaseUrl(bool $useStrictTools): string;

    public function name(): string
    {
        return $this->providerKey();
    }

    public function label(): string
    {
        return (string) $this->config('label', ucfirst($this->providerKey()));
    }

    public function chat(array $messages, array $tools = [], ?array $toolChoice = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException($this->label().' belum dikonfigurasi.');
        }

        $useStrict = $this->usesStrictTools() && $tools !== [];
        $baseUrl = rtrim($this->resolveBaseUrl($useStrict), '/');

        $payload = [
            'model' => $this->config('model'),
            'messages' => $messages,
            'max_tokens' => (int) $this->config('max_tokens', 800),
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;

            if ($toolChoice !== null) {
                $payload['tool_choice'] = $toolChoice;
            }
        }

        $response = Http::timeout((int) $this->config('timeout', 30))
            ->withToken((string) $this->config('api_key'))
            ->acceptJson()
            ->post("{$baseUrl}/chat/completions", $payload);

        if (! $response->successful()) {
            $body = $response->json() ?? $response->body();
            $detail = is_array($body)
                ? (string) data_get($body, 'error.message', json_encode($body))
                : (string) $body;

            Log::warning($this->label().' API error', [
                'provider' => $this->providerKey(),
                'status' => $response->status(),
                'body' => $body,
            ]);

            throw new RuntimeException($this->label().' API error: '.$response->status().' — '.$detail);
        }

        return $response->json();
    }

    public function isConfigured(): bool
    {
        return (bool) $this->config('enabled') && filled($this->config('api_key'));
    }

    public function usesStrictTools(): bool
    {
        return (bool) $this->config('use_strict_tools', true);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("ai.providers.{$this->providerKey()}.{$key}", $default);
    }
}
