<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\AgentContext;
use App\Services\Telegram\TelegramCustomerService;

class SearchCustomerTool extends AbstractAgentTool
{
    public function __construct(
        protected TelegramCustomerService $customers,
    ) {}

    public function name(): string
    {
        return 'search_customer';
    }

    public function description(): string
    {
        return 'Search customers by name in the active branch.';
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
                    'description' => 'Customer name keyword.',
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
        return ['menu' => 'Customer', 'action' => 'is_read'];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $branchId = $context->requireBranch();
        $query = trim((string) ($arguments['query'] ?? ''));
        $limit = min(max((int) ($arguments['limit'] ?? 5), 1), 10);

        if ($query === '') {
            return [
                'success' => false,
                'message' => 'Query customer wajib diisi.',
            ];
        }

        $matches = $this->customers->search($query, $branchId, $limit);

        $items = collect($matches)->map(fn ($customer) => [
            'id' => $customer->id,
            'code' => $customer->code,
            'name' => $customer->name,
        ])->values()->all();

        return [
            'success' => true,
            'branch' => $this->branchLabel($context),
            'query' => $query,
            'count' => count($items),
            'items' => $items,
            'message' => $items === [] ? "Customer \"{$query}\" tidak ditemukan." : null,
        ];
    }
}
