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
        ?string $userId = null,
        ?string $sourceType = null,
        ?string $sourceId = null
    ): array {
        if ($quantity < 1) {
            return [];
        }

        return DB::transaction(function () use ($quantity, $productId, $unitId, $unitLevel, $variantId, $userId, $sourceType, $sourceId) {
            // Jika sudah pernah dialokasi untuk source yang sama, jangan generate ulang.
            if ($sourceType && $sourceId) {
                $existing = $this->serialsForSource($sourceType, $sourceId, $unitLevel, $unitId);
                if ($existing !== []) {
                    return $existing;
                }
            }

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
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ]);

                $serials[] = $serialNumber;
            }

            return $serials;
        });
    }

    /**
     * Allocate hierarchy serials for a source (e.g. production receive).
     * Idempotent: if source already has serials, returns existing counts per unit.
     *
     * @return array{locked: bool, breakdown: list<array{unit_id: string, level: int, qty: int, label: string, serials: list<string>}>}
     */
    public function allocateHierarchyForSource(
        Product $product,
        string $parentUnitId,
        int $parentQuantity,
        ?string $variantId = null,
        ?string $userId = null,
        ?string $sourceType = null,
        ?string $sourceId = null,
        bool $includeSmallestUnit = false
    ): array {
        if ($parentQuantity < 1) {
            return ['locked' => false, 'breakdown' => []];
        }

        $alreadyLocked = $sourceType && $sourceId && $this->hasSourceAllocation($sourceType, $sourceId);
        $breakdown = $product->getBarcodeQuantityBreakdown($parentQuantity, $parentUnitId, $includeSmallestUnit);
        $rows = [];

        foreach ($breakdown as $item) {
            $levelUnit = $product->getBarcodeUnits()->firstWhere('id', $item['unit_id']);
            if (! $levelUnit) {
                continue;
            }

            $serials = $this->allocateSerials(
                (int) $item['qty'],
                $product->id,
                $levelUnit->id,
                (int) $item['level'],
                $variantId,
                $userId,
                $sourceType,
                $sourceId
            );

            $rows[] = [
                'unit_id' => $levelUnit->id,
                'level' => (int) $item['level'],
                'qty' => count($serials),
                'label' => (string) ($item['label'] ?? strtoupper($levelUnit->symbol ?: $levelUnit->name)),
                'serials' => $serials,
            ];
        }

        return [
            'locked' => $alreadyLocked || ($sourceType && $sourceId && $this->hasSourceAllocation($sourceType, $sourceId)),
            'breakdown' => $rows,
        ];
    }

    /**
     * Ambil nomor yang sudah terkunci untuk source, atau peek nomor berikutnya (belum persist).
     *
     * @return array{serials: array<int, string>, locked: bool}
     */
    public function resolveSerialsForPreview(
        int $quantity,
        string $productId,
        string $unitId,
        int $unitLevel,
        ?string $sourceType = null,
        ?string $sourceId = null
    ): array {
        if ($sourceType && $sourceId) {
            $existing = $this->serialsForSource($sourceType, $sourceId, $unitLevel, $unitId);
            if ($existing !== []) {
                return ['serials' => $existing, 'locked' => true];
            }
        }

        return [
            'serials' => $this->peekNextSerials($quantity, $productId, $unitId, $unitLevel),
            'locked' => false,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function serialsForSource(
        string $sourceType,
        string $sourceId,
        int $unitLevel,
        ?string $unitId = null
    ): array {
        $query = ProductLabelSerial::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('unit_level', $unitLevel)
            ->orderBy('sequence');

        if ($unitId) {
            $query->where('unit_id', $unitId);
        }

        return $query->pluck('serial_number')->all();
    }

    public function hasSourceAllocation(string $sourceType, string $sourceId): bool
    {
        return ProductLabelSerial::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
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
