<?php

namespace App\Support;

class StockSourceTypeLabel
{
    private const LABELS = [
        'Inbound' => 'Stok Masuk Manual',
        'TransferIn' => 'Transfer Masuk',
        'TransferOut' => 'Transfer Keluar',
        'MarketingAllocationIn' => 'Alokasi Marketing (Masuk)',
        'MarketingAllocationOut' => 'Alokasi Marketing (Keluar)',
        'ProductionOutput' => 'Hasil Produksi',
        'ProductionConsume' => 'Konsumsi Produksi',
        'ProductionConsumeReverse' => 'Pembalikan Konsumsi Produksi',
        'PurchaseReceive' => 'Penerimaan PO',
        'ReplenishmentReceipt' => 'Penerimaan Replenishment',
        'ReplenishmentReturn' => 'Retur Replenishment',
        'App\Models\ProductPurchaseOrderReceive' => 'Penerimaan PO',
    ];

    public static function label(?string $type): string
    {
        if ($type === null || $type === '') {
            return '-';
        }

        if (isset(self::LABELS[$type])) {
            return self::LABELS[$type];
        }

        if (str_contains($type, '\\')) {
            return match (class_basename($type)) {
                'ProductPurchaseOrderReceive' => 'Penerimaan PO',
                'SalesOrder' => 'Penjualan',
                'ProductionOrder' => 'Produksi',
                default => class_basename($type),
            };
        }

        return $type;
    }
}
