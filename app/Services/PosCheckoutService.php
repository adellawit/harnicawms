<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderPayment;
use App\Models\Warehouse;
use App\Services\MembershipPointService;
use App\Services\Promotion\PromotionEngineService;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosCheckoutService
{
    public function __construct(
        protected MembershipPointService $membershipPointService,
    ) {}

    /**
     * @return array{
     *   items_data: array<int, array<string, mixed>>,
     *   subtotal_net: float,
     *   total_item_disc: float,
     *   tax_amount: float,
     *   txn_disc_amt: float,
     *   total: float,
     *   tax_rate: float,
     *   tax_enabled: bool,
     *   txn_disc_type: string,
     *   txn_disc_val: float
     * }
     */
    public function buildCartTotals(Request $request): array
    {
        $itemsData = [];

        foreach ($request->items as $item) {
            $variant = ProductVariant::findOrFail($item['variant_id']);
            $qty = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineTotal = round($qty * $unitPrice, 4);

            $discType = $item['discount_type'] ?? 'percent';
            $discVal = (float) ($item['discount_value'] ?? 0);
            $itemDiscAmt = 0;
            if ($discType === 'percent') {
                $itemDiscAmt = round($lineTotal * min($discVal, 100) / 100, 4);
            } else {
                $itemDiscAmt = round(min($discVal, $lineTotal), 4);
            }

            $lineSubtotal = $lineTotal - $itemDiscAmt;

            $itemsData[] = [
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'unit_id' => $item['unit_id'],
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'discount_type' => $discType,
                'discount_value' => $discVal,
                'discount_amount' => $itemDiscAmt,
                'subtotal' => $lineSubtotal,
                'is_promo_free' => false,
                'promotion_id' => null,
                'source_warehouse_id' => null,
            ];
        }

        $branchId = $this->resolveBranchId($request);
        $companyId = optional(WmsContext::distributor())->id;
        $orderWarehouseId = $branchId
            ? optional(WmsContext::defaultWarehouse($branchId))->id
            : null;

        $itemsData = PromotionEngineService::applyToCartLines(
            $itemsData,
            $companyId,
            $branchId,
            $orderWarehouseId
        );

        // Totals exclude free promo lines (already subtotal 0).
        $subtotalGross = 0;
        $totalItemDisc = 0;
        foreach ($itemsData as $row) {
            if (! empty($row['is_promo_free'])) {
                continue;
            }
            $lineGross = round((float) $row['quantity'] * (float) $row['unit_price'], 4);
            $subtotalGross += $lineGross;
            $totalItemDisc += (float) ($row['discount_amount'] ?? 0);
        }

        $subtotalNet = $subtotalGross - $totalItemDisc;

        $txnDiscType = $request->discount_type ?? 'percent';
        $txnDiscVal = (float) ($request->discount_value ?? 0);
        $txnDiscAmt = 0;
        if ($txnDiscType === 'percent') {
            $txnDiscAmt = round($subtotalNet * min($txnDiscVal, 100) / 100, 4);
        } else {
            $txnDiscAmt = round(min($txnDiscVal, $subtotalNet), 4);
        }

        $taxRate = (float) $request->tax_rate;
        $taxEnabled = (bool) $request->tax_enabled;
        $taxAmount = $taxEnabled ? round($subtotalNet * $taxRate / 100, 4) : 0;

        $totalBeforeRedeem = $subtotalNet + $taxAmount - $txnDiscAmt;
        if ($totalBeforeRedeem < 0) {
            $totalBeforeRedeem = 0;
        }

        $redeem = $this->membershipPointService->normalizeRedeemRequest(
            $request->customer_id,
            $branchId,
            (int) ($request->redeem_points ?? 0),
            $totalBeforeRedeem
        );

        $total = round($totalBeforeRedeem - $redeem['discount_amount'], 4);
        if ($total < 0) {
            $total = 0;
        }

        return [
            'items_data' => $itemsData,
            'subtotal_net' => $subtotalNet,
            'total_item_disc' => $totalItemDisc,
            'tax_amount' => $taxAmount,
            'txn_disc_amt' => $txnDiscAmt,
            'total' => $total,
            'tax_rate' => $taxRate,
            'tax_enabled' => $taxEnabled,
            'txn_disc_type' => $txnDiscType,
            'txn_disc_val' => $txnDiscVal,
            'promo_free_count' => collect($itemsData)->where('is_promo_free', true)->count(),
            'redeem_points' => $redeem['points'],
            'redeem_discount_amount' => $redeem['discount_amount'],
            'redeem_value_per_point' => $redeem['redeem_value_per_point'],
        ];
    }

    protected function resolveBranchId(Request $request): ?string
    {
        return $request->branch_id
            ?: auth('web')->user()?->current_business_unit_id
            ?: null;
    }

    /**
     * @param  array<string, mixed>  $totals
     */
    public function createSalesOrder(
        Request $request,
        array $totals,
        string $salesNumber,
        string $branchId,
        ?string $companyId,
        ?string $userId,
        string $status,
        string $paymentStatus,
        string $orderType = 'pos',
    ): SalesOrder {
        $customerName = null;
        if ($request->customer_id) {
            $customer = Customer::find($request->customer_id);
            $customerName = $customer?->name;
        }

        $warehouseId = optional(WmsContext::defaultWarehouse($branchId))->id;

        $order = SalesOrder::create([
            'sales_number' => $salesNumber,
            'sales_date' => now()->toDateString(),
            'order_type' => $orderType,
            'customer_id' => $request->customer_id,
            'customer_name' => $customerName ?? 'Walk-in Customer',
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'price_list_id' => $request->price_list_id,
            'method_payment_id' => $request->payment_method_id,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'subtotal' => $totals['subtotal_net'],
            'tax_rate' => $totals['tax_rate'],
            'tax_enabled' => $totals['tax_enabled'],
            'tax_amount' => $totals['tax_amount'],
            'discount_type' => $totals['txn_disc_type'],
            'discount_value' => $totals['txn_disc_val'],
            'discount_amount' => $totals['txn_disc_amt'],
            'item_discount_total' => $totals['total_item_disc'],
            'shipping_amount' => 0,
            'total' => $totals['total'],
            'membership_points_redeemed' => (int) ($totals['redeem_points'] ?? 0),
            'membership_redeem_discount_amount' => (float) ($totals['redeem_discount_amount'] ?? 0),
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
            'fulfilled_at' => $status === 'completed' ? now() : null,
            'notes' => $request->notes,
            'created_by' => $userId,
        ]);

        $createdByLineKey = [];

        foreach ($totals['items_data'] as $iData) {
            $parentKey = $iData['_parent_line_key'] ?? null;
            $lineKey = $iData['_line_key'] ?? null;

            $payload = [
                'sales_order_id' => $order->id,
                'product_id' => $iData['product_id'],
                'product_variant_id' => $iData['product_variant_id'],
                'unit_id' => $iData['unit_id'],
                'quantity' => $iData['quantity'],
                'unit_price' => $iData['unit_price'],
                'discount_type' => $iData['discount_type'] ?? 'percent',
                'discount_value' => $iData['discount_value'] ?? 0,
                'discount_amount' => $iData['discount_amount'] ?? 0,
                'subtotal' => $iData['subtotal'],
                'notes' => $iData['notes'] ?? null,
                'is_promo_free' => (bool) ($iData['is_promo_free'] ?? false),
                'promotion_id' => $iData['promotion_id'] ?? null,
                'source_warehouse_id' => $iData['source_warehouse_id']
                    ?? (! empty($iData['is_promo_free']) ? null : $warehouseId),
                'parent_item_id' => $parentKey !== null
                    ? ($createdByLineKey[$parentKey] ?? null)
                    : null,
                'created_by' => $userId,
            ];

            // Paid lines default to order warehouse when not set.
            if (empty($payload['is_promo_free']) && empty($payload['source_warehouse_id'])) {
                $payload['source_warehouse_id'] = $warehouseId;
            }

            $item = SalesOrderItem::create($payload);

            if ($lineKey !== null) {
                $createdByLineKey[$lineKey] = $item->id;
            }
        }

        return $order;
    }

    public function completePaidOrder(SalesOrder $order, ?string $userId = null): void
    {
        if ($order->payment_status === 'paid' && $order->status === 'completed') {
            return;
        }

        DB::transaction(function () use ($order, $userId) {
            $order = SalesOrder::lockForUpdate()->findOrFail($order->id);

            if ($order->payment_status === 'paid' && $order->status === 'completed') {
                return;
            }

            $order->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'paid_at' => now(),
                'fulfilled_at' => now(),
                'updated_by' => $userId,
            ]);

            SalesOrderPayment::where('sales_order_id', $order->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'updated_by' => $userId,
                ]);

            $order->loadMissing(['items', 'customer.agent.defaultWarehouse']);

            $agentWarehouse = $this->resolveAgentDestinationWarehouse($order);
            $actorId = $userId ?? $order->created_by;

            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if (! $product?->is_stock_item) {
                    continue;
                }

                $sourceWarehouseId = $item->source_warehouse_id ?: $order->warehouse_id;

                $outbound = StockMutationService::outbound(
                    $item->product_id,
                    $item->product_variant_id,
                    $order->company_id,
                    $order->branch_id,
                    $item->unit_id,
                    (float) $item->quantity,
                    SalesOrder::class,
                    $order->id,
                    $actorId,
                    $item->is_promo_free ? 'POS Sale (promo free)' : 'POS Sale',
                    $sourceWarehouseId
                );

                // Agent POS: stock moves to the agent's warehouse (paid + free lines).
                if ($agentWarehouse) {
                    $agentBranchId = $agentWarehouse->branch_id ?: $order->branch_id;

                    StockMutationService::inbound(
                        $item->product_id,
                        $item->product_variant_id,
                        $order->company_id,
                        $agentBranchId,
                        $item->unit_id,
                        (float) $item->quantity,
                        (float) $item->unit_price,
                        SalesOrder::class,
                        $order->id,
                        $actorId,
                        $item->is_promo_free
                            ? 'POS transfer ke gudang Agent (promo free) - '.$order->sales_number
                            : 'POS transfer ke gudang Agent - '.$order->sales_number,
                        null,
                        $outbound['earliest_expiry'] ?? null,
                        $agentWarehouse->id
                    );
                }
            }

            $this->membershipPointService->redeemForPaidOrder(
                $order,
                (int) ($order->membership_points_redeemed ?? 0),
                (float) ($order->membership_redeem_discount_amount ?? 0),
                $actorId
            );

            $this->membershipPointService->awardForPaidOrder($order->fresh(), $actorId);
        });
    }

    /**
     * Resolve destination warehouse when POS customer is a partner Agent.
     * Reseller / walk-in: null (sale only, no agent stock-in).
     *
     * @throws \RuntimeException when customer is Agent but has no warehouse
     */
    protected function resolveAgentDestinationWarehouse(SalesOrder $order): ?Warehouse
    {
        $customer = $order->customer;
        if (! $customer || ! $customer->isPartnerAgent()) {
            return null;
        }

        $agent = $customer->agent;
        $warehouse = $agent?->defaultWarehouse
            ?: ($agent ? WmsContext::defaultAgentWarehouse($agent->id) : null);

        if (! $warehouse) {
            throw new \RuntimeException(
                'Agent belum memiliki gudang. Set default warehouse Agent sebelum transaksi POS.'
            );
        }

        return $warehouse;
    }
}
