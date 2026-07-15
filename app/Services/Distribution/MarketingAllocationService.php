<?php

namespace App\Services\Distribution;

use App\Models\MarketingStockAllocation;
use App\Models\MarketingStockAllocationItem;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\Warehouse;
use App\Services\BatchStockService;
use App\Services\StockMutationService;
use App\Services\UnitConversionService;
use App\Support\WmsContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingAllocationService
{
    public const SOURCE_TYPE = 'FG';

    public const DEST_TYPE = 'MARKETING';

    /**
     * Resolve Gudang Product (FG) for branch/company.
     */
    public static function resolveProductWarehouse(?string $companyId = null, ?string $branchId = null): ?Warehouse
    {
        $query = Warehouse::query()
            ->where('warehouse_type_code', self::SOURCE_TYPE)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $warehouses = $query->orderBy('code')->get();
        if ($warehouses->isEmpty()) {
            return null;
        }

        return $warehouses->first(fn (Warehouse $w) => str_contains(strtoupper($w->code ?? ''), 'WH-PRD')
            || str_contains(strtolower($w->name ?? ''), 'product'))
            ?? $warehouses->first();
    }

    /**
     * Resolve Gudang Marketing for branch/company.
     */
    public static function resolveMarketingWarehouse(?string $companyId = null, ?string $branchId = null): ?Warehouse
    {
        $query = Warehouse::query()
            ->where('warehouse_type_code', self::DEST_TYPE)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('code')->first();
    }

    /**
     * @return list<array{variant_id: string, label: string, quantity: float, unit_id: string, unit_label: string, stock_unit_id: string}>
     */
    public static function availableStockLines(Warehouse $fromWarehouse): array
    {
        return ProductVariantStock::query()
            ->with([
                'variant.product.unitConversions.fromUnit',
                'variant.product.unitConversions.toUnit',
                'variant.product.defaultUnit:id,symbol,name',
                'unit:id,symbol,name',
            ])
            ->where('warehouse_id', $fromWarehouse->id)
            ->where('quantity', '>', 0)
            ->whereNull('deleted_at')
            ->orderByDesc('quantity')
            ->get()
            ->map(function (ProductVariantStock $row) {
                $variant = $row->variant;
                $product = $variant?->product;
                $label = $variant?->display_name
                    ?? $product?->name
                    ?? 'Product';

                $stockQty = (float) $row->quantity;
                $stockUnitId = $row->unit_id;
                $largestUnitId = $product?->default_unit_id ?: $stockUnitId;
                $largestUnit = $product?->defaultUnit;
                $displayQty = $stockQty;
                $displayUnitId = $stockUnitId;
                $displayUnitLabel = $row->unit?->symbol ?: ($row->unit?->name ?? '');

                if ($product && $stockUnitId && $largestUnitId && $stockUnitId !== $largestUnitId) {
                    $converted = UnitConversionService::convertQuantity(
                        $product,
                        $stockQty,
                        $stockUnitId,
                        $largestUnitId
                    );
                    if ($converted !== null) {
                        $displayQty = (float) $converted;
                        $displayUnitId = $largestUnitId;
                        $displayUnitLabel = $largestUnit?->symbol ?: ($largestUnit?->name ?? $displayUnitLabel);
                    }
                } elseif ($largestUnit) {
                    $displayUnitId = $largestUnitId;
                    $displayUnitLabel = $largestUnit->symbol ?: ($largestUnit->name ?? $displayUnitLabel);
                }

                return [
                    'variant_id' => $row->product_variant_id,
                    'label' => $label,
                    'quantity' => round($displayQty, 6),
                    'unit_id' => $displayUnitId,
                    'unit_label' => $displayUnitLabel,
                    'stock_unit_id' => $stockUnitId,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Create allocation + move stock immediately (Product → Marketing).
     *
     * @param  list<array{variant_id: string, qty: float|int|string}>  $lines
     */
    public static function createAndTransfer(
        array $lines,
        ?string $notes = null,
        ?string $allocationDate = null,
        ?string $userId = null
    ): MarketingStockAllocation {
        $distributor = WmsContext::distributor();
        $companyId = optional($distributor)->id;

        $from = self::resolveProductWarehouse($companyId);
        $to = self::resolveMarketingWarehouse($companyId);

        if (! $from || ! $to) {
            throw ValidationException::withMessages([
                'warehouse' => 'Product warehouse (FG) or Marketing warehouse is not configured.',
            ]);
        }

        if ($from->id === $to->id) {
            throw ValidationException::withMessages([
                'warehouse' => 'Source and destination warehouses must be different.',
            ]);
        }

        $branchId = $from->branch_id ?: $to->branch_id;
        $operationalBranchFrom = $from->branch_id ?: $from->company_id;
        $operationalBranchTo = $to->branch_id ?: $to->company_id;

        if (! $operationalBranchFrom || ! $operationalBranchTo) {
            throw ValidationException::withMessages([
                'warehouse' => 'Source or destination warehouse is missing branch context.',
            ]);
        }

        $normalized = [];
        foreach ($lines as $line) {
            $variantId = $line['variant_id'] ?? null;
            $qty = (float) ($line['qty'] ?? 0);
            if (! $variantId || $qty <= 0) {
                continue;
            }
            $normalized[$variantId] = ($normalized[$variantId] ?? 0) + $qty;
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'lines' => 'Add at least one item with quantity greater than zero.',
            ]);
        }

        return DB::transaction(function () use (
            $normalized,
            $notes,
            $allocationDate,
            $userId,
            $companyId,
            $branchId,
            $from,
            $to,
            $operationalBranchFrom,
            $operationalBranchTo
        ) {
            $allocation = MarketingStockAllocation::create([
                'allocation_number' => self::generateNumber(),
                'allocation_date' => $allocationDate ?: now()->toDateString(),
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'status' => 'completed',
                'notes' => $notes,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($normalized as $variantId => $qty) {
                $variant = ProductVariant::with([
                    'product.unitConversions',
                    'product.defaultUnit',
                ])->find($variantId);
                if (! $variant || ! $variant->product) {
                    throw ValidationException::withMessages([
                        'lines' => "Product variant {$variantId} was not found.",
                    ]);
                }

                $product = $variant->product;
                $unitId = $product->default_unit_id;
                if (! $unitId) {
                    throw ValidationException::withMessages([
                        'lines' => 'Product is missing a default (largest) unit.',
                    ]);
                }

                $sourceStock = ProductVariantStock::query()
                    ->where('product_variant_id', $variant->id)
                    ->where('warehouse_id', $from->id)
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (! $sourceStock || ! $sourceStock->unit_id) {
                    throw ValidationException::withMessages([
                        'lines' => sprintf(
                            'No stock found for %s in Product warehouse.',
                            $variant->display_name ?? $product->name
                        ),
                    ]);
                }

                $availableInLargest = UnitConversionService::convertQuantity(
                    $product,
                    (float) $sourceStock->quantity,
                    $sourceStock->unit_id,
                    $unitId
                );
                if ($availableInLargest === null) {
                    $availableInLargest = (float) $sourceStock->quantity;
                    $unitId = $sourceStock->unit_id;
                }

                if ((float) $availableInLargest < $qty - 1e-9) {
                    throw ValidationException::withMessages([
                        'lines' => sprintf(
                            'Insufficient stock for %s in Product warehouse. Available: %s, requested: %s.',
                            $variant->display_name ?? $product->name,
                            rtrim(rtrim(number_format((float) $availableInLargest, 4, '.', ''), '0'), '.'),
                            rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.')
                        ),
                    ]);
                }

                $batchLines = BatchStockService::consumeOutboundLines(
                    $variant->product_id,
                    $from->id,
                    $unitId,
                    $qty,
                    $userId
                );

                $result = StockMutationService::outbound(
                    $variant->product_id,
                    $variant->id,
                    $companyId,
                    $operationalBranchFrom,
                    $unitId,
                    $qty,
                    'MarketingAllocationOut',
                    $allocation->id,
                    $userId,
                    'Marketing Allocation '.$allocation->allocation_number,
                    $from->id,
                    false
                );

                StockMutationService::inbound(
                    $variant->product_id,
                    $variant->id,
                    $companyId,
                    $operationalBranchTo,
                    $unitId,
                    $qty,
                    $result['unit_cost'],
                    'MarketingAllocationIn',
                    $allocation->id,
                    $userId,
                    'Marketing Allocation '.$allocation->allocation_number,
                    null,
                    $result['earliest_expiry'],
                    $to->id
                );

                foreach ($batchLines as $batchLine) {
                    BatchStockService::receiveInbound(
                        $variant->product_id,
                        $companyId,
                        $batchLine['batch_number'],
                        $batchLine['expiry_date'],
                        $to->id,
                        $operationalBranchTo,
                        $batchLine['unit_id'],
                        $batchLine['quantity'],
                        $userId
                    );
                }

                MarketingStockAllocationItem::create([
                    'allocation_id' => $allocation->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $unitId,
                    'quantity' => $qty,
                    'unit_cost' => $result['unit_cost'],
                    'total_cost' => $result['total_cost'],
                ]);
            }

            return $allocation->load(['items.variant.product', 'items.unit', 'fromWarehouse', 'toWarehouse']);
        });
    }

    public static function generateNumber(): string
    {
        $prefix = 'ALK-'.date('Ym').'-';
        $last = MarketingStockAllocation::withTrashed()
            ->where('allocation_number', 'like', $prefix.'%')
            ->orderByDesc('allocation_number')
            ->value('allocation_number');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
