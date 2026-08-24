<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AgentToolInterface;
use App\Services\Ai\Tools\GetHelpTool;
use App\Services\Ai\Tools\GetSalesSummaryTool;
use App\Services\Ai\Tools\GetStockTool;
use App\Services\Ai\Tools\GuideTourTool;
use App\Services\Ai\Tools\ManageRecordTool;
use App\Services\Ai\Tools\OpenPageTool;
use App\Services\Ai\Tools\SaleActionTool;
use App\Services\Ai\Tools\SearchCustomerTool;
use App\Services\Ai\Tools\SearchDocsTool;
use App\Services\Ai\Tools\SearchProductTool;

class AgentToolRegistry
{
    /**
     * @var array<string, AgentToolInterface>
     */
    protected array $tools = [];

    public function __construct(
        SearchDocsTool $searchDocsTool,
        SearchProductTool $searchProductTool,
        GetStockTool $getStockTool,
        SearchCustomerTool $searchCustomerTool,
        GetSalesSummaryTool $getSalesSummaryTool,
        GetHelpTool $getHelpTool,
        SaleActionTool $saleActionTool,
        ManageRecordTool $manageRecordTool,
        GuideTourTool $guideTourTool,
        OpenPageTool $openPageTool,
    ) {
        foreach ([
            $searchDocsTool,
            $searchProductTool,
            $getStockTool,
            $searchCustomerTool,
            $getSalesSummaryTool,
            $getHelpTool,
            $saleActionTool,
            $manageRecordTool,
            $guideTourTool,
            $openPageTool,
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
