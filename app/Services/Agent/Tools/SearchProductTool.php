<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;
use App\Services\Product\ProductSearchService;
use App\Services\Telegram\TelegramProductResolver;

class SearchProductTool extends AbstractAgentTool
{
    public function __construct(
        protected ProductSearchService $productSearch,
        protected TelegramProductResolver $productResolver,
    ) {}

    public function name(): string
    {
        return 'search_product';
    }

    public function description(): string
    {
        return 'Search sale products by name, code, or SKU in the active branch. Returns label, SKU, price, and stock.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['query', 'limit'],
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'Product name, code, or SKU keyword.',
                ],
                'limit' => [
                    'type' => ['integer', 'null'],
                    'description' => 'Maximum results (default 5, max 10).',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?array
    {
        return ['menu' => 'Product', 'action' => 'is_read'];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $branchId = $context->requireBranch();
        $query = trim((string) ($arguments['query'] ?? ''));
        $limit = min(max((int) ($arguments['limit'] ?? 5), 1), 10);

        if ($query === '') {
            return [
                'success' => false,
                'message' => 'Query produk wajib diisi.',
            ];
        }

        $priceListId = $this->productResolver->resolveDefaultPriceListId($branchId, $context->companyId);
        $results = $this->productSearch->search($query, $branchId, $context->companyId, $priceListId, $limit);

        if ($results->isEmpty()) {
            return [
                'success' => true,
                'branch' => $this->branchLabel($context),
                'query' => $query,
                'count' => 0,
                'items' => [],
                'message' => "Produk \"{$query}\" tidak ditemukan.",
            ];
        }

        $items = $results->map(fn (array $row) => [
            'label' => $row['label'],
            'sku' => $row['sku'],
            'price' => $row['unit_price'],
            'price_formatted' => $this->formatCurrency((float) $row['unit_price']),
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
