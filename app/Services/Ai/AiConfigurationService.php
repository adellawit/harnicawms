<?php

namespace App\Services\Ai;

use App\Support\EnvWriter;
use Illuminate\Support\Facades\Artisan;

class AiConfigurationService
{
    public function __construct(
        protected EnvWriter $env,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $deepseekKey = $this->env->get('DEEPSEEK_API_KEY', config('ai.providers.deepseek.api_key'));
        $chatAiKey = $this->env->get('CHATAI_API_KEY', config('ai.providers.chatai.api_key'));

        return [
            'provider' => strtolower((string) config('ai.provider', 'deepseek')),
            'agent' => [
                'enabled' => (bool) config('agent.enabled'),
                'widget_enabled' => (bool) config('agent.widget_enabled'),
                'max_tool_rounds' => (int) config('agent.max_tool_rounds', 5),
                'max_message_length' => (int) config('agent.max_message_length', 2000),
                'rate_limit_per_minute' => (int) config('agent.rate_limit_per_minute', 30),
                'permission_menu' => (string) config('agent.permission_menu', 'AI Assistant'),
            ],
            'deepseek' => [
                'enabled' => (bool) config('ai.providers.deepseek.enabled'),
                'api_key' => $deepseekKey,
                'api_key_masked' => $this->env->maskSecret($deepseekKey),
                'base_url' => (string) config('ai.providers.deepseek.base_url'),
                'beta_url' => (string) config('ai.providers.deepseek.beta_url'),
                'model' => (string) config('ai.providers.deepseek.model'),
                'timeout' => (int) config('ai.providers.deepseek.timeout', 30),
                'max_tokens' => (int) config('ai.providers.deepseek.max_tokens', 800),
                'use_strict_tools' => (bool) config('ai.providers.deepseek.use_strict_tools', true),
            ],
            'chatai' => [
                'enabled' => (bool) config('ai.providers.chatai.enabled'),
                'api_key' => $chatAiKey,
                'api_key_masked' => $this->env->maskSecret($chatAiKey),
                'base_url' => (string) config('ai.providers.chatai.base_url'),
                'model' => (string) config('ai.providers.chatai.model'),
                'timeout' => (int) config('ai.providers.chatai.timeout', 30),
                'max_tokens' => (int) config('ai.providers.chatai.max_tokens', 800),
                'use_strict_tools' => (bool) config('ai.providers.chatai.use_strict_tools', true),
            ],
            'status' => [
                'provider_label' => app(LlmProviderManager::class)->current()->label(),
                'configured' => app(LlmProviderManager::class)->current()->isConfigured(),
                'widget_enabled' => (bool) config('agent.widget_enabled'),
                'service_enabled' => (bool) config('agent.enabled'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): void
    {
        $provider = strtolower((string) ($input['provider'] ?? 'deepseek'));

        $values = [
            'AI_PROVIDER' => $provider,
            'AGENT_ENABLED' => $this->boolValue($input['agent_enabled'] ?? false),
            'AGENT_WIDGET_ENABLED' => $this->boolValue($input['agent_widget_enabled'] ?? false),
            'AGENT_MAX_TOOL_ROUNDS' => (int) ($input['agent_max_tool_rounds'] ?? 5),
            'AGENT_MAX_MESSAGE_LENGTH' => (int) ($input['agent_max_message_length'] ?? 2000),
            'AGENT_RATE_LIMIT_PER_MINUTE' => (int) ($input['agent_rate_limit_per_minute'] ?? 30),
            'AGENT_PERMISSION_MENU' => (string) ($input['agent_permission_menu'] ?? 'AI Assistant'),
            'DEEPSEEK_ENABLED' => $this->boolValue($input['deepseek_enabled'] ?? false),
            'DEEPSEEK_BASE_URL' => (string) ($input['deepseek_base_url'] ?? 'https://api.deepseek.com'),
            'DEEPSEEK_BETA_URL' => (string) ($input['deepseek_beta_url'] ?? 'https://api.deepseek.com/beta'),
            'DEEPSEEK_MODEL' => (string) ($input['deepseek_model'] ?? 'deepseek-chat'),
            'DEEPSEEK_TIMEOUT' => (int) ($input['deepseek_timeout'] ?? 30),
            'DEEPSEEK_MAX_TOKENS' => (int) ($input['deepseek_max_tokens'] ?? 800),
            'DEEPSEEK_USE_STRICT_TOOLS' => $this->boolValue($input['deepseek_use_strict_tools'] ?? true),
            'CHATAI_ENABLED' => $this->boolValue($input['chatai_enabled'] ?? false),
            'CHATAI_BASE_URL' => (string) ($input['chatai_base_url'] ?? 'https://api.openai.com/v1'),
            'CHATAI_MODEL' => (string) ($input['chatai_model'] ?? 'gpt-4o-mini'),
            'CHATAI_TIMEOUT' => (int) ($input['chatai_timeout'] ?? 30),
            'CHATAI_MAX_TOKENS' => (int) ($input['chatai_max_tokens'] ?? 800),
            'CHATAI_USE_STRICT_TOOLS' => $this->boolValue($input['chatai_use_strict_tools'] ?? true),
        ];

        if (filled($input['deepseek_api_key'] ?? null)) {
            $values['DEEPSEEK_API_KEY'] = (string) $input['deepseek_api_key'];
        }

        if (filled($input['chatai_api_key'] ?? null)) {
            $values['CHATAI_API_KEY'] = (string) $input['chatai_api_key'];
        }

        $this->env->setMany($values);
        Artisan::call('config:clear');
    }

    protected function boolValue(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
