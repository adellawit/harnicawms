<?php

namespace App\Services\Product;

use App\Models\Product;
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

    /**
     * Hapus riwayat nomor seri produk agar alokasi berikutnya mulai dari urutan 1 per level.
     */
    public function resetSerialsForProduct(string $productId, ?string $variantId = null): int
    {
        return DB::transaction(function () use ($productId, $variantId) {
            $query = ProductLabelSerial::query()->where('product_id', $productId);

            if ($variantId !== null && $variantId !== '') {
                $query->where('product_variant_id', $variantId);
            }

            return (int) $query->delete();
        });
    }

    /**
     * @return array<int, array{level: int, unit_label: string, next_serial: string, allocated_count: int}>
     */
    public function serialStatusForProduct(Product $product): array
    {
        $status = [];

        foreach ($product->getBarcodeUnits()->values() as $index => $unit) {
            $level = $index + 1;
            $allocatedCount = ProductLabelSerial::query()
                ->where('product_id', $product->id)
                ->where('unit_level', $level)
                ->count();

            $nextSerial = $this->peekNextSerials(1, $product->id, $unit->id, $level)[0] ?? $this->formatExample($level);

            $status[] = [
                'level' => $level,
                'unit_label' => strtoupper($unit->symbol ?: $unit->name),
                'next_serial' => $nextSerial,
                'allocated_count' => $allocatedCount,
            ];
        }

        return $status;
    }

    protected function lastSequenceForUnit(string $productId, string $unitId, int $unitLevel): int
    {
        $yearPrefix = date('y');
        $level = max(1, min(9, $unitLevel));
        $serialPrefix = $yearPrefix.$level;
        $expectedLength = strlen($serialPrefix) + 9;

        $lockKey = (int) crc32("product_label_serial_{$yearPrefix}_{$productId}_{$level}");
        if ($lockKey < 0) {
            $lockKey += 4294967296;
        }
        DB::select('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

        // Sumber utama: parse nomor seri existing (YY + level + urutan).
        // Tidak filter unit_id karena data lama bisa punya unit_id yang tidak sesuai level.
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

        $lastFromSequence = ProductLabelSerial::query()
            ->where('product_id', $productId)
            ->where('year_prefix', $yearPrefix)
            ->where('unit_level', $level)
            ->max('sequence');

        return max((int) ($maxFromSerial ?? 0), (int) ($lastFromSequence ?? 0));
    }

    protected function formatSerialNumber(int $unitLevel, int $sequence): string
    {
        $level = max(1, min(9, $unitLevel));

        return date('y').$level.str_pad((string) $sequence, 9, '0', STR_PAD_LEFT);
    }
}
