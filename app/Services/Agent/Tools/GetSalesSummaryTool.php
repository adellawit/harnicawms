<?php

namespace App\Services\Agent\Tools;

use App\Models\SalesOrder;
use App\Services\Agent\AgentContext;
use Carbon\Carbon;

class GetSalesSummaryTool extends AbstractAgentTool
{
    public function name(): string
    {
        return 'get_sales_summary';
    }

    public function description(): string
    {
        return 'Get sales summary (transaction count and total amount) for a date in the active branch. Defaults to today.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['date'],
            'properties' => [
                'date' => [
                    'type' => ['string', 'null'],
                    'description' => 'Date in YYYY-MM-DD format. Defaults to today.',
                ],
            ],
        ];
    }

    public function requiredPermission(): ?array
    {
        return ['menu' => 'Sales Summary', 'action' => 'is_read'];
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        $branchId = $context->requireBranch();
        $dateInput = trim((string) ($arguments['date'] ?? ''));

        try {
            $date = $dateInput !== '' ? Carbon::parse($dateInput)->toDateString() : Carbon::today()->toDateString();
        } catch (\Throwable) {
            return [
                'success' => false,
                'message' => 'Format tanggal tidak valid. Gunakan YYYY-MM-DD.',
            ];
        }

        $cancelledStatuses = ['cancelled', 'void', 'refund'];

        $query = SalesOrder::query()
            ->whereDate('sales_date', $date)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', $cancelledStatuses);

        $transactionCount = (clone $query)->count();
        $totalAmount = (float) (clone $query)->sum('total');

        return [
            'success' => true,
            'branch' => $this->branchLabel($context),
            'date' => $date,
            'transaction_count' => $transactionCount,
            'total_amount' => $totalAmount,
            'total_formatted' => $this->formatCurrency($totalAmount),
        ];
    }
}
