<?php

namespace App\Support;

use App\Models\ParameterDetail;
use App\Models\ProductPurchaseOrder;
use Illuminate\Support\Collection;

class PurchaseOrderStatus
{
    public const PARAMETER_CODE = 'PO_STATUS';

    /** Status awal dokumen baru. Status operasional hanya lewat Update Status / receive. */
    public const INITIAL = 'draft';

    /** Target maksimum Update Status dari index. Receiving/Payment otomatis dari penerimaan. */
    public const MANUAL_TARGET = 'process';

    public static function resolveOnCreate(): string
    {
        return self::INITIAL;
    }

    /**
     * @return Collection<int, ParameterDetail|object{id: string, key: string, value: string}>
     */
    public static function selectableOptions(): Collection
    {
        $options = ParameterDetail::query()
            ->whereHas('parameter', fn ($q) => $q->where('code', self::PARAMETER_CODE))
            ->orderByRaw("CASE key WHEN 'draft' THEN 1 WHEN 'process' THEN 2 WHEN 'receiving' THEN 3 WHEN 'payment' THEN 4 ELSE 5 END")
            ->get(['id', 'key', 'value']);

        if (! $options->contains('key', 'cancelled')) {
            $options->push((object) [
                'id' => 'cancelled',
                'key' => 'cancelled',
                'value' => 'Cancelled',
            ]);
        }

        return $options;
    }

    public static function manualUpdateOptions(): Collection
    {
        $options = self::selectableOptions()
            ->filter(fn ($option) => ($option->key ?? '') === self::MANUAL_TARGET)
            ->values();

        if ($options->isEmpty()) {
            $options->push((object) [
                'id' => self::MANUAL_TARGET,
                'key' => self::MANUAL_TARGET,
                'value' => self::label(self::MANUAL_TARGET),
            ]);
        }

        return $options;
    }

    public static function canUpdate(ProductPurchaseOrder $purchase): bool
    {
        if ($purchase->trashed()) {
            return false;
        }

        $status = $purchase->status_key ?? $purchase->status;

        return $status === self::INITIAL;
    }

    public static function validateManualUpdate(ProductPurchaseOrder $purchase, string $newStatus): ?string
    {
        if ($newStatus !== self::MANUAL_TARGET) {
            return 'Update Status hanya dapat dinaikkan sampai Process.';
        }

        return self::validateTransition($purchase, $newStatus);
    }

    public static function validateTransition(ProductPurchaseOrder $purchase, string $newStatus): ?string
    {
        $current = $purchase->status_key ?? $purchase->status;

        if ($current === $newStatus) {
            return null;
        }

        if ($newStatus === 'received') {
            return 'Status Received diatur otomatis saat penerimaan barang.';
        }

        $allowed = [
            'draft' => ['process', 'cancelled'],
            'process' => ['receiving', 'cancelled'],
            'receiving' => ['payment', 'cancelled', 'process'],
            'payment' => ['receiving'],
            'cancelled' => ['draft'],
            'received' => ['payment'],
        ];

        if (! in_array($newStatus, $allowed[$current] ?? [], true)) {
            return 'Tidak dapat mengubah status dari '.self::label($current).' ke '.self::label($newStatus).'.';
        }

        $purchase->loadMissing('items.receiveItems');

        if ($newStatus === 'cancelled') {
            $hasReceives = $purchase->items->contains(
                fn ($item) => (float) $item->receiveItems->sum('quantity_received') > 0
            );
            if ($hasReceives) {
                return 'PO yang sudah memiliki penerimaan barang tidak dapat dibatalkan.';
            }
        }

        if ($newStatus === 'payment') {
            foreach ($purchase->items as $item) {
                $received = (float) $item->receiveItems->sum('quantity_received');
                if ($received + 1e-6 < (float) $item->quantity) {
                    return 'Status Payment hanya dapat diatur setelah semua item diterima penuh.';
                }
            }
        }

        return null;
    }

    public static function label(?string $key): string
    {
        return match ($key) {
            'draft' => 'Draft',
            'process' => 'Process',
            'receiving' => 'Receiving',
            'payment' => 'Payment',
            'received' => 'Received',
            'cancelled' => 'Cancelled',
            default => ucfirst($key ?? '-'),
        };
    }
}
