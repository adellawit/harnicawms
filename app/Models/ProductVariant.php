<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'product.product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'purchase_price',
        'selling_price',
        'weight',
        'image',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:4',
        'selling_price' => 'decimal:4',
        'weight' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($variant) {
            if (empty($variant->barcode)) {
                $variant->barcode = self::generateBarcode();
            }
            if (empty($variant->sku)) {
                $variant->sku = self::generateSku($variant->product_id);
            }
        });
    }

    public static function generateBarcode(): string
    {
        // Format: YYMMDDXXXXXXX (7-digit sequence)
        $datePrefix = date('ymd');
        $lastBarcode = self::where('barcode', 'like', $datePrefix.'%')
            ->orderBy('barcode', 'desc')
            ->value('barcode');
        if ($lastBarcode) {
            $sequence = (int) substr($lastBarcode, 6) + 1;
        } else {
            $sequence = 1;
        }

        return $datePrefix.str_pad($sequence, 7, '0', STR_PAD_LEFT);
    }

    public static function generateSku($productId): string
    {
        // Format: V-YY-XXXXX (5-digit sequence per product)
        $prefix = 'V-'.date('y');
        $last = self::withTrashed()
            ->where('product_id', $productId)
            ->where('sku', 'like', $prefix.'%')
            ->orderBy('sku', 'desc')
            ->value('sku');
        if ($last) {
            $seq = (int) substr($last, strrpos($last, '-') + 1) + 1;
        } else {
            $seq = 1;
        }

        return $prefix.'-'.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function variantAttributes(): HasMany
    {
        return $this->hasMany(ProductVariantAttribute::class, 'product_variant_id', 'id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductVariantPrice::class, 'variant_id', 'id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductVariantStock::class, 'product_variant_id', 'id');
    }

    /**
     * Get variant display name: "Product Name - Attribute Values" or just "Product Name (SKU)"
     */
    public function getDisplayNameAttribute(): string
    {
        $productName = $this->product?->name ?? '';
        $attrs = $this->variantAttributes->map(function ($va) {
            return $va->attributeValue?->value ?? '';
        })->filter()->implode(' / ');

        if ($attrs) {
            return $productName.' - '.$attrs;
        }

        return $productName.($this->sku ? ' ('.$this->sku.')' : '');
    }

    /**
     * Resolve variant for stock mutations — matches ProductStockController behaviour.
     */
    public static function resolveForStock(string $productId, ?string $variantId = null, ?string $userId = null): ?self
    {
        if ($variantId) {
            return static::query()
                ->whereKey($variantId)
                ->where('product_id', $productId)
                ->whereNull('deleted_at')
                ->first();
        }

        $variant = static::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->first();

        if ($variant) {
            return $variant;
        }

        $product = Product::query()->find($productId);
        if (! $product) {
            return null;
        }

        return static::create([
            'product_id' => $productId,
            'sku' => $product->sku ?? 'PROD-' . substr($productId, 0, 8),
            'barcode' => $product->barcode ?? substr($productId, 0, 13),
            'purchase_price' => 0,
            'selling_price' => 0,
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
