<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\LlmProviderInterface;
use App\Services\Ai\Providers\ChatAiProvider;
use App\Services\Ai\Providers\DeepSeekProvider;
use InvalidArgumentException;

class LlmProviderManager
{
    public function __construct(
        protected DeepSeekProvider $deepSeek,
        protected ChatAiProvider $chatAi,
    ) {}

    public function current(): LlmProviderInterface
    {
        $provider = strtolower((string) config('ai.provider', 'deepseek'));

        return match ($provider) {
            'deepseek' => $this->deepSeek,
            'chatai' => $this->chatAi,
            default => throw new InvalidArgumentException("AI provider \"{$provider}\" tidak dikenali. Gunakan: deepseek, chatai."),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function currentConfig(): array
    {
        $provider = $this->current()->name();

        return config("ai.providers.{$provider}", []);
    }
}
