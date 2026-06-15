<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AgentToolInterface;
use App\Services\Ai\Tools\GetHelpTool;
use App\Services\Ai\Tools\GetSalesSummaryTool;
use App\Services\Ai\Tools\GetStockTool;
use App\Services\Ai\Tools\SearchCustomerTool;
use App\Services\Ai\Tools\SearchProductTool;

class AgentToolRegistry
{
    /**
     * @var array<string, AgentToolInterface>
     */
    protected array $tools = [];

    public function __construct(
        SearchProductTool $searchProductTool,
        GetStockTool $getStockTool,
        SearchCustomerTool $searchCustomerTool,
        GetSalesSummaryTool $getSalesSummaryTool,
        GetHelpTool $getHelpTool,
    ) {
        foreach ([
            $searchProductTool,
            $getStockTool,
            $searchCustomerTool,
            $getSalesSummaryTool,
            $getHelpTool,
        ] as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function openAiToolsForContext(AgentContext $context): array
    {
        $allowed = config('agent.allowed_tools', []);
        $definitions = [];

        foreach ($this->tools as $name => $tool) {
            if ($allowed !== [] && ! in_array($name, $allowed, true)) {
                continue;
            }

            if (! $context->hasPermission($tool->requiredPermission())) {
                continue;
            }

            $definitions[] = $tool->toOpenAiTool();
        }

        return $definitions;
    }

    public function get(string $name): ?AgentToolInterface
    {
        return $this->tools[$name] ?? null;
    }
}
