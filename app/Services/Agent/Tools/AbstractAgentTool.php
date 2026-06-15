<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;
use App\Services\Agent\Contracts\AgentToolInterface;

abstract class AbstractAgentTool implements AgentToolInterface
{
    public function toOpenAiTool(): array
    {
        $function = [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => $this->normalizeParameters($this->parameters()),
        ];

        if (config('deepseek.use_strict_tools', true)) {
            $function['strict'] = true;
        }

        return [
            'type' => 'function',
            'function' => $function,
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|\stdClass
     */
    protected function normalizeParameters(array $parameters): array|\stdClass
    {
        $parameters['type'] = $parameters['type'] ?? 'object';

        $properties = $parameters['properties'] ?? [];

        if ($properties === []) {
            $parameters['properties'] = new \stdClass();
        }

        if (config('deepseek.use_strict_tools', true)) {
            $parameters['additionalProperties'] = false;

            if (! array_key_exists('required', $parameters)) {
                $parameters['required'] = is_array($properties)
                    ? array_keys($properties)
                    : [];
            }
        }

        return $parameters;
    }

    protected function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    protected function branchLabel(AgentContext $context): string
    {
        return $context->branchName ?? 'Cabang aktif';
    }
}
