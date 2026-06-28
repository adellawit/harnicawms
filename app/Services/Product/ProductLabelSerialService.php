<?php

namespace App\Services\Product;

use App\Models\ProductLabelSerial;
use Illuminate\Support\Facades\DB;

class ProductLabelSerialService
{
    /**
     * Allocate sequential serial numbers per product + unit level.
     * Format: YY + L + 9-digit sequence (L = level satuan 1=Dus, 2=Box, 3=Pcs, ...).
     *
     * @return array<int, string>
     */
    public function allocateSerials(
        int $quantity,
        string $productId,
        string $unitId,
        int $unitLevel,
        ?string $variantId = null,
        ?string $userId = null
    ): array {
        if ($quantity < 1) {
            return [];
        }

        return DB::transaction(function () use ($quantity, $productId, $unitId, $unitLevel, $variantId, $userId) {
            $lastSequence = $this->lastSequenceForUnit($productId, $unitId, $unitLevel);

            $serials = [];

            for ($i = 1; $i <= $quantity; $i++) {
                $sequence = $lastSequence + $i;
                $serialNumber = $this->formatSerialNumber($unitLevel, $sequence);

                ProductLabelSerial::create([
                    'serial_number' => $serialNumber,
                    'year_prefix' => date('y'),
                    'sequence' => $sequence,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'unit_id' => $unitId,
                    'unit_level' => $unitLevel,
                    'printed_by' => $userId,
                ]);

                $serials[] = $serialNumber;
            }

            return $serials;
        });
    }

    /**
     * Preview nomor seri berikutnya tanpa menyimpan.
     *
     * @return array<int, string>
     */
    public function peekNextSerials(int $quantity, string $productId, string $unitId, int $unitLevel): array
    {
        if ($quantity < 1) {
            return [];
        }

        $lastSequence = $this->lastSequenceForUnit($productId, $unitId, $unitLevel);

        $serials = [];
        for ($i = 1; $i <= $quantity; $i++) {
            $serials[] = $this->formatSerialNumber($unitLevel, $lastSequence + $i);
        }

        return $serials;
    }

    public function formatExample(int $unitLevel, int $sequence = 1): string
    {
        return $this->formatSerialNumber($unitLevel, $sequence);
    }

    protected function lastSequenceForUnit(string $productId, string $unitId, int $unitLevel): int
    {
        $yearPrefix = date('y');
        $level = max(1, min(9, $unitLevel));
        $serialPrefix = $yearPrefix.$level;
        $expectedLength = strlen($serialPrefix) + 9;

        $lockKey = crc32("product_label_serial_{$yearPrefix}_{$productId}_{$level}");
        DB::select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

        $maxFromSerial = ProductLabelSerial::query()
            ->where('product_id', $productId)
            ->where('serial_number', 'like', $serialPrefix.'%')
            ->whereRaw('LENGTH(serial_number) = ?', [$expectedLength])
            ->pluck('serial_number')
            ->map(function (string $serial) use ($serialPrefix) {
                $suffix = substr($serial, strlen($serialPrefix));

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max();

        if ($maxFromSerial) {
            return (int) $maxFromSerial;
        }

        $lastRecord = ProductLabelSerial::query()
            ->where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->where('year_prefix', $yearPrefix)
            ->where('unit_level', $level)
            ->orderByDesc('sequence')
            ->first();

        return $lastRecord?->sequence ?? 0;
    }

    protected function formatSerialNumber(int $unitLevel, int $sequence): string
    {
        $level = max(1, min(9, $unitLevel));

        return date('y').$level.str_pad((string) $sequence, 9, '0', STR_PAD_LEFT);
    }
}
