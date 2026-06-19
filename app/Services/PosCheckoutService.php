<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ProductStockMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderPayment;
use App\Models\StockMutationType;
use App\Services\MembershipPointService;
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
        $subtotalGross = 0;
        $totalItemDisc = 0;

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
            $subtotalGross += $lineTotal;
            $totalItemDisc += $itemDiscAmt;

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
            ];
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

        $total = $subtotalNet + $taxAmount - $txnDiscAmt;
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
        ];
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
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
            'fulfilled_at' => $status === 'completed' ? now() : null,
            'notes' => $request->notes,
            'created_by' => $userId,
        ]);

        foreach ($totals['items_data'] as $iData) {
            SalesOrderItem::create(array_merge($iData, [
                'sales_order_id' => $order->id,
                'created_by' => $userId,
            ]));
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

            $salesMutationType = StockMutationType::where('code', 'SALES')
                ->whereNull('deleted_at')
                ->first();

            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if (! $product?->is_stock_item) {
                    continue;
                }

                $this->deductStock(
                    productId: $item->product_id,
                    variantId: $item->product_variant_id,
                    unitId: $item->unit_id,
                    branchId: $order->branch_id,
                    companyId: $order->company_id,
                    quantity: (float) $item->quantity,
                    salesOrderId: $order->id,
                    mutationType: $salesMutationType,
                    userId: $userId ?? $order->created_by,
                    warehouseId: $order->warehouse_id,
                );
            }

            // Award membership points after order is officially paid/completed.
            $this->membershipPointService->awardForPaidOrder($order, $userId ?? $order->created_by);
        });
    }

    protected function deductStock(
        string $productId,
        string $variantId,
        string $unitId,
        string $branchId,
        ?string $companyId,
        float $quantity,
        string $salesOrderId,
        ?StockMutationType $mutationType,
        ?string $userId,
        ?string $warehouseId = null,
    ): void {
        StockMutationService::outbound(
            $productId,
            $variantId,
            $companyId,
            $branchId,
            $unitId,
            $quantity,
            SalesOrder::class,
            $salesOrderId,
            $userId,
            'POS Sale',
            $warehouseId
        );
    }
}
