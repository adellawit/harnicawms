<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'product.products';

    protected $fillable = [
        'company_id',
        'branch_id',
        'nature_id',
        'category_id',
        'item_type_id',
        'product_nature_id',
        'procurement_type_id',
        'default_unit_id',
        'name',
        'code',
        'sku',
        'description',
        'is_stock_item',
        'is_sale_item',
        'is_purchase_item',
        'min_stock',
        'max_stock',
        'cogs_account_code',
        'revenue_account_code',
        'created_by',
        'updated_by',
    ];

    /** @deprecated Use item_type_id - alias for view backward compatibility */
    public function getTypeIdAttribute(): ?string
    {
        return $this->item_type_id;
    }

    protected $casts = [
        'is_stock_item' => 'boolean',
        'is_sale_item' => 'boolean',
        'is_purchase_item' => 'boolean',
        'min_stock' => 'decimal:4',
        'max_stock' => 'decimal:4',
    ];

    public function nature(): BelongsTo
    {
        return $this->belongsTo(ProductNature::class, 'nature_id', 'id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id', 'id');
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ParameterDetail::class, 'item_type_id', 'id');
    }

    public function productNature(): BelongsTo
    {
        return $this->belongsTo(ParameterDetail::class, 'product_nature_id', 'id');
    }

    public function procurementType(): BelongsTo
    {
        return $this->belongsTo(ParameterDetail::class, 'procurement_type_id', 'id');
    }

    public function defaultUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'default_unit_id', 'id');
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(ProductUnitConversion::class, 'product_id', 'id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id', 'id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductVariantStock::class, 'product_id', 'id');
    }

    public function variantStocks(): HasManyThrough
    {
        return $this->hasManyThrough(ProductVariantStock::class, ProductVariant::class, 'product_id', 'product_variant_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'product_id', 'id');
    }

    public function scopeStockItems($query)
    {
        return $query->where('is_stock_item', true);
    }

    public function scopeSaleItems($query)
    {
        return $query->where('is_sale_item', true);
    }

    public function scopePurchaseItems($query)
    {
        return $query->where('is_purchase_item', true);
    }

    public function getSmallestUnitId(): string
    {
        $this->loadMissing('unitConversions');

        if (! $this->default_unit_id) {
            return '';
        }

        $currentUnitId = $this->default_unit_id;
        $visited = [];

        while (true) {
            $next = $this->unitConversions
                ->sortBy('conversion_level')
                ->first(fn ($conv) => $conv->from_unit_id === $currentUnitId && ! isset($visited[$conv->id]));

            if (! $next) {
                break;
            }

            $visited[$next->id] = true;
            $currentUnitId = $next->to_unit_id;
        }

        return $currentUnitId;
    }

    public function getFactorToSmallest(): float
    {
        $this->loadMissing('unitConversions');

        if (! $this->default_unit_id) {
            return 1.0;
        }

        $factor = 1.0;
        $currentUnitId = $this->default_unit_id;
        $visited = [];

        while (true) {
            $next = $this->unitConversions
                ->sortBy('conversion_level')
                ->first(fn ($conv) => $conv->from_unit_id === $currentUnitId && ! isset($visited[$conv->id]));

            if (! $next) {
                break;
            }

            $visited[$next->id] = true;
            $factor *= (float) $next->conversion_factor;
            $currentUnitId = $next->to_unit_id;
        }

        return $factor;
    }

    public function convertQuantity(float $quantity, string $fromUnitId, string $toUnitId): ?float
    {
        if ($fromUnitId === $toUnitId) {
            return $quantity;
        }

        $this->loadMissing('unitConversions');
        $conversions = $this->unitConversions;

        $conv = $conversions->first(
            fn ($row) => $row->from_unit_id === $fromUnitId && $row->to_unit_id === $toUnitId
        );
        if ($conv) {
            // Presisi tinggi di sini sengaja BUKAN presisi final — pembulatan ke 6 desimal
            // terlalu dini menggeser hasil kalau nilai ini masih dikalikan lagi oleh caller
            // (mis. skala BOM). Presisi akhir tetap dijaga oleh cast decimal:6 di kolom DB
            // dan pembulatan tampilan di view.
            return round($quantity * (float) $conv->conversion_factor, 10);
        }

        $convReverse = $conversions->first(
            fn ($row) => $row->from_unit_id === $toUnitId && $row->to_unit_id === $fromUnitId
        );
        if ($convReverse) {
            return round($quantity / (float) $convReverse->conversion_factor, 10);
        }

        return $this->convertQuantityMultiHop($quantity, $fromUnitId, $toUnitId);
    }

    /**
     * Konversi multi-hop melalui rantai product_unit_conversions (BFS).
     */
    protected function convertQuantityMultiHop(float $quantity, string $fromUnitId, string $toUnitId): ?float
    {
        $visited = [];
        $queue = [[$fromUnitId, $quantity]];

        while (! empty($queue)) {
            [$unitId, $qty] = array_shift($queue);

            if ($unitId === $toUnitId) {
                // Sama seperti convertQuantity(): jangan bulatkan tajam di sini, nilai ini
                // masih akan dikalikan lagi oleh caller (skala BOM dsb).
                return round($qty, 10);
            }

            if (isset($visited[$unitId])) {
                continue;
            }
            $visited[$unitId] = true;

            foreach ($this->unitConversions as $conv) {
                $factor = (float) $conv->conversion_factor;
                if ($factor <= 0) {
                    continue;
                }

                if ($conv->from_unit_id === $unitId) {
                    $queue[] = [$conv->to_unit_id, $qty * $factor];
                }
                if ($conv->to_unit_id === $unitId) {
                    $queue[] = [$conv->from_unit_id, $qty / $factor];
                }
            }
        }

        return null;
    }

    /**
     * Satuan yang tersedia untuk cetak barcode (default + rantai konversi).
     */
    public function getBarcodeUnits(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['defaultUnit', 'unitConversions.fromUnit', 'unitConversions.toUnit']);

        $ordered = collect();

        if ($this->defaultUnit) {
            $ordered->push($this->defaultUnit);
        }

        $currentUnitId = $this->default_unit_id;
        $visited = [];

        while (true) {
            $next = $this->unitConversions
                ->sortBy('conversion_level')
                ->first(fn ($conv) => $conv->from_unit_id === $currentUnitId && ! isset($visited[$conv->id]));

            if (! $next?->toUnit) {
                break;
            }

            $visited[$next->id] = true;
            $ordered->push($next->toUnit);
            $currentUnitId = $next->to_unit_id;
        }

        foreach ($this->unitConversions as $conv) {
            foreach ([$conv->fromUnit, $conv->toUnit] as $unit) {
                if ($unit && ! $ordered->contains('id', $unit->id)) {
                    $ordered->push($unit);
                }
            }
        }

        return $ordered->unique('id')->values();
    }

    public function hasBarcodeUnit(string $unitId): bool
    {
        return $this->getBarcodeUnits()->contains('id', $unitId);
    }

    /**
     * Level satuan dalam rantai konversi (1 = terbesar/Dus, 2 = Box, 3 = Pcs, ...).
     */
    public function getBarcodeUnitLevel(string $unitId): int
    {
        $index = $this->getBarcodeUnits()->search(fn (ProductUnit $unit) => $unit->id === $unitId);

        return $index !== false ? $index + 1 : 1;
    }

    /**
     * Keterangan konversi untuk satuan terpilih, mis. "1 Dus = 300 Box".
     */
    public function getBarcodeUnitConversionHint(string $unitId): ?string
    {
        $this->loadMissing(['unitConversions.fromUnit', 'unitConversions.toUnit']);

        $conv = $this->unitConversions->firstWhere('from_unit_id', $unitId);
        if ($conv && $conv->toUnit) {
            $from = $conv->fromUnit?->symbol ?: $conv->fromUnit?->name;
            $to = $conv->toUnit?->symbol ?: $conv->toUnit?->name;
            $factor = (float) $conv->conversion_factor;
            $factorLabel = $factor == (int) $factor ? (string) (int) $factor : rtrim(rtrim(number_format($factor, 6, '.', ''), '0'), '.');

            return "1 {$from} = {$factorLabel} {$to}";
        }

        $parentConv = $this->unitConversions->firstWhere('to_unit_id', $unitId);
        if ($parentConv && $parentConv->fromUnit) {
            $from = $parentConv->fromUnit?->symbol ?: $parentConv->fromUnit?->name;
            $to = $parentConv->toUnit?->symbol ?: $parentConv->toUnit?->name;
            $factor = (float) $parentConv->conversion_factor;
            $factorLabel = $factor == (int) $factor ? (string) (int) $factor : rtrim(rtrim(number_format($factor, 6, '.', ''), '0'), '.');

            return "1 {$from} = {$factorLabel} {$to}";
        }

        return null;
    }

    /**
     * Estimasi jumlah label satuan anak per 1 satuan induk (untuk panduan qty).
     */
    public function getChildLabelsPerParent(string $unitId): ?int
    {
        $this->loadMissing('unitConversions');

        $conv = $this->unitConversions->firstWhere('from_unit_id', $unitId);
        if (! $conv) {
            return null;
        }

        $total = (int) (float) $conv->conversion_factor;
        $currentUnitId = $conv->to_unit_id;

        while ($next = $this->unitConversions->firstWhere('from_unit_id', $currentUnitId)) {
            $total *= (int) (float) $next->conversion_factor;
            $currentUnitId = $next->to_unit_id;
        }

        return $total > 0 ? $total : null;
    }

    /**
     * Rantai satuan berurutan beserta faktor konversi ke level berikutnya.
     *
     * @return \Illuminate\Support\Collection<int, array{unit: ProductUnit, level: int, factor_to_next: float|null}>
     */
    public function getBarcodeUnitChain(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['unitConversions']);

        return $this->getBarcodeUnits()->values()->map(function (ProductUnit $unit, int $index) {
            $conv = $this->unitConversions->firstWhere('from_unit_id', $unit->id);

            return [
                'unit' => $unit,
                'level' => $index + 1,
                'factor_to_next' => $conv ? (float) $conv->conversion_factor : null,
            ];
        });
    }

    /**
     * Hitung qty label per level dari qty satuan induk (mis. 1 Karton → 30 Pack, 300 Box).
     *
     * @return array<int, array{unit_id: string, level: int, qty: int, label: string, content_summary: string|null}>
     */
    public function getBarcodeQuantityBreakdown(int $parentQty, ?string $parentUnitId = null, bool $includeSmallestUnit = true): array
    {
        $chain = $this->getBarcodeUnitChain();
        if ($chain->isEmpty() || $parentQty < 1) {
            return [];
        }

        $startIndex = 0;
        if ($parentUnitId) {
            $found = $chain->search(fn (array $item) => $item['unit']->id === $parentUnitId);
            if ($found !== false) {
                $startIndex = $found;
            }
        }

        $lastIndex = $chain->count() - 1;
        // Satuan terkecil (mis. sachet) biasanya tidak punya label fisik sendiri —
        // dikecualikan secara default. Kalau starting unit sudah satuan terkecil
        // itu sendiri, tidak ada yang bisa dikecualikan lagi.
        $endIndex = (! $includeSmallestUnit && $lastIndex > $startIndex) ? $lastIndex - 1 : $lastIndex;

        $breakdown = [];
        $qty = $parentQty;

        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $item = $chain[$i];
            $unit = $item['unit'];

            $breakdown[] = [
                'unit_id' => $unit->id,
                'level' => $item['level'],
                'qty' => $qty,
                'label' => $unit->symbol ?: $unit->name,
                'content_summary' => $this->getBarcodeUnitLabelContent($unit->id),
            ];

            if ($i + 1 < $chain->count() && $item['factor_to_next']) {
                $qty = (int) round($qty * $item['factor_to_next']);
            }
        }

        return $breakdown;
    }

    /**
     * Total label untuk mode hierarki (jumlah semua level).
     */
    public function getBarcodeHierarchyTotalLabels(int $parentQty, ?string $parentUnitId = null, bool $includeSmallestUnit = true): int
    {
        return (int) collect($this->getBarcodeQuantityBreakdown($parentQty, $parentUnitId, $includeSmallestUnit))->sum('qty');
    }

    /**
     * Qty maksimum satuan induk agar total label hierarki tidak melebihi batas.
     */
    public function getMaxHierarchyParentQty(int $maxTotalLabels, ?string $parentUnitId = null, bool $includeSmallestUnit = true): int
    {
        $perParent = $this->getBarcodeHierarchyTotalLabels(1, $parentUnitId, $includeSmallestUnit);
        if ($perParent < 1) {
            return max(1, $maxTotalLabels);
        }

        return max(1, (int) floor($maxTotalLabels / $perParent));
    }

    /**
     * Teks isi label sesuai level (mis. "ISI = 30 pack (300 pcs)" atau "1 Pack = 10 box").
     */
    public function getBarcodeUnitLabelContent(string $unitId): ?string
    {
        $units = $this->getBarcodeUnits();
        $index = $units->search(fn (ProductUnit $unit) => $unit->id === $unitId);
        if ($index === false) {
            return null;
        }

        $level = $index + 1;
        $unit = $units[$index];
        $unitLabel = $unit->symbol ?: $unit->name;

        if ($level === 1 && $units->count() > 1) {
            $conv = $this->unitConversions->firstWhere('from_unit_id', $unitId);
            if (! $conv?->toUnit) {
                return null;
            }

            $middleLabel = strtolower($conv->toUnit->symbol ?: $conv->toUnit->name);
            $middleFactor = $this->formatConversionFactor((float) $conv->conversion_factor);
            $totalSmallest = $this->getChildLabelsPerParent($unitId);
            $smallest = $units->last();
            $smallestLabel = strtolower($smallest->symbol ?: $smallest->name);

            return "ISI = {$middleFactor} {$middleLabel} ({$totalSmallest} {$smallestLabel})";
        }

        if ($level < $units->count()) {
            $conv = $this->unitConversions->firstWhere('from_unit_id', $unitId);
            if (! $conv?->toUnit) {
                return null;
            }

            $childLabel = $conv->toUnit->symbol ?: $conv->toUnit->name;
            $factor = $this->formatConversionFactor((float) $conv->conversion_factor);

            return "1 {$unitLabel} = {$factor} {$childLabel}";
        }

        return null;
    }

    protected function formatConversionFactor(float $factor): string
    {
        return $factor == (int) $factor
            ? (string) (int) $factor
            : rtrim(rtrim(number_format($factor, 6, '.', ''), '0'), '.');
    }
}
