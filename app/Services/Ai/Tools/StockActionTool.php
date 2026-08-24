<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Actions\AgentStockActionService;
use App\Services\Ai\AgentContext;

class StockActionTool extends AbstractAgentTool
{
    public function __construct(
        protected AgentStockActionService $stocks,
    ) {}

    public function name(): string
    {
        return 'manage_stock';
    }

    public function description(): string
    {
        return 'Prepare a stock quantity adjustment draft for the active branch default warehouse. Use set_quantity with apply_to=all_sale_items to set every saleable product to the same target, or apply_to=one/matching for a product query. This tool never writes stock. After set_quantity the user must click the confirm button in the chat widget.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['operation', 'target_quantity', 'apply_to', 'product_query', 'variant_id'],
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['set_quantity', 'show', 'clear', 'propose'],
                    'description' => 'set_quantity builds the draft and asks for confirmation. show/clear/propose manage the draft.',
                ],
                'target_quantity' => [
                    'type' => ['number', 'null'],
                    'description' => 'Target on-hand quantity (for example 100).',
                ],
                'apply_to' => [
                    'type' => ['string', 'null'],
                    'enum' => ['one', 'matching', 'all_sale_items'],
                    'description' => 'one = single product, matching = all search hits, all_sale_items = every saleable SKU in the branch (max 40).',
                ],
                'product_query' => [
                    'type' => ['string', 'null'],
                    'description' => 'Product name or SKU when apply_to is one or matching.',
                ],
                'variant_id' => [
                    'type' => ['string', 'null'],
                    'description' => 'Exact variant id when the user picked from choices.',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?array
    {
        return ['menu' => 'Stock Adjustment', 'action' => 'is_update'];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        return $this->stocks->handle($arguments, $context);
    }
}
