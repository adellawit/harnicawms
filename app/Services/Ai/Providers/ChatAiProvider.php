<?php

namespace App\Services\Ai\Providers;

class ChatAiProvider extends AbstractLlmProvider
{
    protected function providerKey(): string
    {
        return 'chatai';
    }

    protected function resolveBaseUrl(bool $useStrictTools): string
    {
        return (string) $this->config('base_url', 'https://api.openai.com/v1');
    }
}
