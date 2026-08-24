<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Actions\AgentSaleActionService;
use App\Services\Ai\AgentContext;

class SaleActionTool extends AbstractAgentTool
{
    public function __construct(
        protected AgentSaleActionService $sales,
    ) {}

    public function name(): string
    {
        return 'manage_sale';
    }

    public function description(): string
    {
        return 'Prepare a POS cash sale draft for the active branch: add items, set customer or cash payment, show or clear the draft, and propose it for user confirmation. This tool never creates the transaction. After propose, the user must click the confirm button in the chat widget.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'operation',
                'product_query',
                'quantity',
                'variant_id',
                'customer_query',
                'customer_id',
                'payment_query',
                'payment_method_id',
            ],
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'description' => 'add_item, set_customer, set_payment, show, clear, or propose.',
                    'enum' => ['add_item', 'set_customer', 'set_payment', 'show', 'clear', 'propose'],
                ],
                'product_query' => [
                    'type' => ['string', 'null'],
                    'description' => 'Product name or SKU for add_item.',
                ],
                'quantity' => [
                    'type' => ['number', 'null'],
                    'description' => 'Quantity for add_item.',
                ],
                'variant_id' => [
                    'type' => ['string', 'null'],
                    'description' => 'Exact variant id when the user picked from choices.',
                ],
                'customer_query' => [
                    'type' => ['string', 'null'],
                    'description' => 'Customer name for set_customer. Empty means walk-in.',
                ],
                'customer_id' => [
                    'type' => ['string', 'null'],
                    'description' => 'Exact customer id when the user picked from choices.',
                ],
                'payment_query' => [
                    'type' => ['string', 'null'],
                    'description' => 'Cash payment method name or code for set_payment.',
                ],
                'payment_method_id' => [
                    'type' => ['string', 'null'],
                    'description' => 'Exact payment method id when the user picked from choices.',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?array
    {
        return ['menu' => 'POS', 'action' => 'is_create'];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        return $this->sales->handle($arguments, $context);
    }
}
