<?php

namespace App\Services\Ai\Providers;

class DeepSeekProvider extends AbstractLlmProvider
{
    protected function providerKey(): string
    {
        return 'deepseek';
    }

    protected function resolveBaseUrl(bool $useStrictTools): string
    {
        return $useStrictTools
            ? (string) $this->config('beta_url', 'https://api.deepseek.com/beta')
            : (string) $this->config('base_url', 'https://api.deepseek.com');
    }
}
