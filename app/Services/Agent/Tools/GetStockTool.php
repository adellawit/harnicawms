<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;
use App\Services\Product\ProductSearchService;
use App\Services\Telegram\TelegramProductResolver;

class GetStockTool extends AbstractAgentTool
{
    public function __construct(
        protected ProductSearchService $productSearch,
        protected TelegramProductResolver $productResolver,
    ) {}

    public function name(): string
    {
        return 'get_stock';
    }

    public function description(): string
    {
        return 'Get on-hand stock for products matching a keyword or SKU in the active branch.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['query'],
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Product name or SKU keyword.',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?array
    {
        return ['menu' => 'Stock', 'action' => 'is_read'];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $branchId = $context->requireBranch();
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            return [
                'success' => false,
                'message' => 'Query stok wajib diisi.',
            ];
        }

        $priceListId = $this->productResolver->resolveDefaultPriceListId($branchId, $context->companyId);
        $results = $this->productSearch->search($query, $branchId, $context->companyId, $priceListId, 10);

        if ($results->isEmpty()) {
            return [
                'success' => true,
                'branch' => $this->branchLabel($context),
                'query' => $query,
                'count' => 0,
                'items' => [],
                'message' => "Stok untuk \"{$query}\" tidak ditemukan.",
            ];
        }

        $items = $results->map(fn (array $row) => [
            'label' => $row['label'],
            'sku' => $row['sku'],
            'stock' => $row['stock'],
        ])->values()->all();

        return [
            'success' => true,
            'branch' => $this->branchLabel($context),
            'query' => $query,
            'count' => count($items),
            'items' => $items,
        ];
    }
}
