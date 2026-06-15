<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AgentContext;
use App\Services\Ai\Contracts\AgentToolInterface;

abstract class AbstractAgentTool implements AgentToolInterface
{
    public function toOpenAiTool(): array
    {
        $function = [
            'name' => $this->name(),
            'description' => $this->description(),
            'parameters' => $this->normalizeParameters($this->parameters()),
        ];

        if (app(LlmProviderManager::class)->current()->usesStrictTools()) {
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

        if (app(LlmProviderManager::class)->current()->usesStrictTools()) {
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
