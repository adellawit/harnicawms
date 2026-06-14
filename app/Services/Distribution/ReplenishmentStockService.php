<?php

namespace App\Services\Distribution;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\ReplenishmentOrder;
use App\Models\ReturnOrder;
use App\Models\ReturnItem;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\FifoCostService;
use App\Services\StockMutationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pergerakan stok untuk alur replenishment Distributor <-> Agen.
 *
 * - ship(): stok keluar dari Distributor (FIFO COGS), buat shipment + tracking.
 * - receive(): stok masuk ke Agen pada harga transfer (membuat layer FIFO Agen).
 * - returnGoods(): stok keluar dari Agen (FIFO), masuk kembali ke Distributor.
 */
class ReplenishmentStockService
{
    /**
     * Distributor mengirim barang sesuai item order (sisa qty yang belum dikirim).
     */
    public static function ship(ReplenishmentOrder $order, array $payload, ?string $userId = null): Shipment
    {
        return DB::transaction(function () use ($order, $payload, $userId) {
            $shipment = Shipment::create([
                'shipment_number' => self::generateNumber('shipments', 'shipment_number', 'SHP'),
                'order_id' => $order->id,
                'ship_date' => $payload['ship_date'] ?? Carbon::now()->toDateString(),
                'carrier' => $payload['carrier'] ?? null,
                'tracking_number' => $payload['tracking_number'] ?? null,
                'status' => 'in_transit',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($order->items as $item) {
                $qty = (float) ($payload['qty'][$item->id] ?? 0);
                $outstanding = (float) $item->qty_ordered - (float) $item->qty_shipped;
                $qty = min($qty, max($outstanding, 0));
                if ($qty <= 0) {
                    continue;
                }

                StockMutationService::outbound(
                    $item->product_id,
                    $item->product_variant_id,
                    $order->distributor_id,
                    $order->distributor_id, // stok distributor disimpan di branch=company id (gudang distributor)
                    $item->unit_id,
                    $qty,
                    'ReplenishmentShipment',
                    $shipment->id,
                    $userId,
                    'Pengiriman ke agen - order ' . $order->order_number
                );

                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $qty,
                ]);

                $item->qty_shipped = (float) $item->qty_shipped + $qty;
                $item->save();
            }

            $order->update(['status' => 'shipped', 'updated_by' => $userId]);

            return $shipment->fresh('items');
        });
    }

    /**
     * Agen menerima barang dari shipment -> stok masuk pada harga transfer (layer FIFO Agen).
     */
    public static function receive(Shipment $shipment, ?string $userId = null): Receipt
    {
        return DB::transaction(function () use ($shipment, $userId) {
            $order = $shipment->order;

            $receipt = Receipt::create([
                'receipt_number' => self::generateNumber('receipts', 'receipt_number', 'RCV'),
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'receive_date' => Carbon::now()->toDateString(),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($shipment->items as $sItem) {
                $orderItem = $sItem->orderItem;
                $transferPrice = $orderItem ? (float) $orderItem->unit_price : 0.0;
                $qty = (float) $sItem->quantity;
                if ($qty <= 0) {
                    continue;
                }

                // Stok masuk ke gudang Agen pada harga transfer = HPP agen
                StockMutationService::inbound(
                    $sItem->product_id,
                    $sItem->product_variant_id,
                    $order->distributor_id,
                    $order->agent_id, // branch = agen
                    $sItem->unit_id,
                    $qty,
                    $transferPrice,
                    'ReplenishmentReceipt',
                    $receipt->id,
                    $userId,
                    'Penerimaan dari distributor - order ' . $order->order_number
                );

                ReceiptItem::create([
                    'receipt_id' => $receipt->id,
                    'order_item_id' => $orderItem?->id,
                    'product_id' => $sItem->product_id,
                    'product_variant_id' => $sItem->product_variant_id,
                    'unit_id' => $sItem->unit_id,
                    'quantity' => $qty,
                ]);

                if ($orderItem) {
                    $orderItem->qty_received = (float) $orderItem->qty_received + $qty;
                    $orderItem->save();
                }
            }

            $shipment->update(['status' => 'delivered', 'updated_by' => $userId]);
            self::refreshOrderStatus($order, $userId);

            return $receipt->fresh('items');
        });
    }

    /**
     * Agen meretur barang ke Distributor -> stok keluar Agen, masuk kembali ke Distributor.
     */
    public static function returnGoods(ReplenishmentOrder $order, array $payload, ?string $userId = null): ReturnOrder
    {
        return DB::transaction(function () use ($order, $payload, $userId) {
            $return = ReturnOrder::create([
                'return_number' => self::generateNumber('returns', 'return_number', 'RTN'),
                'order_id' => $order->id,
                'agent_id' => $order->agent_id,
                'distributor_id' => $order->distributor_id,
                'return_date' => $payload['return_date'] ?? Carbon::now()->toDateString(),
                'reason' => $payload['reason'] ?? null,
                'status' => 'completed',
                'notes' => $payload['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            foreach ($order->items as $item) {
                $qty = (float) ($payload['qty'][$item->id] ?? 0);
                $returnable = (float) $item->qty_received - (float) $item->qty_returned;
                $qty = min($qty, max($returnable, 0));
                if ($qty <= 0) {
                    continue;
                }

                // Stok keluar dari Agen (FIFO)
                StockMutationService::outbound(
                    $item->product_id,
                    $item->product_variant_id,
                    $order->distributor_id,
                    $order->agent_id,
                    $item->unit_id,
                    $qty,
                    'ReplenishmentReturn',
                    $return->id,
                    $userId,
                    'Retur ke distributor - order ' . $order->order_number
                );

                // Stok masuk kembali ke Distributor pada harga transfer
                StockMutationService::inbound(
                    $item->product_id,
                    $item->product_variant_id,
                    $order->distributor_id,
                    $order->distributor_id,
                    $item->unit_id,
                    $qty,
                    (float) $item->unit_price,
                    'ReplenishmentReturn',
                    $return->id,
                    $userId,
                    'Retur masuk dari agen - order ' . $order->order_number
                );

                ReturnItem::create([
                    'return_id' => $return->id,
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'unit_id' => $item->unit_id,
                    'quantity' => $qty,
                ]);

                $item->qty_returned = (float) $item->qty_returned + $qty;
                $item->save();
            }

            return $return->fresh('items');
        });
    }

    protected static function refreshOrderStatus(ReplenishmentOrder $order, ?string $userId): void
    {
        $order->load('items');
        $allReceived = $order->items->every(fn ($i) => (float) $i->qty_received >= (float) $i->qty_ordered);
        $anyReceived = $order->items->contains(fn ($i) => (float) $i->qty_received > 0);

        $status = $allReceived ? 'received' : ($anyReceived ? 'partially_received' : $order->status);
        $order->update(['status' => $status, 'updated_by' => $userId]);
    }

    protected static function generateNumber(string $table, string $column, string $prefix): string
    {
        $full = 'distribution.' . $table;
        $like = $prefix . '-' . date('Ym') . '-';
        $last = DB::table($full)->where($column, 'like', $like . '%')->orderByDesc($column)->value($column);
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $like . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
