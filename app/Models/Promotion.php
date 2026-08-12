<?php

namespace App\Models;

use App\Models\Partner\Agent;
use App\Models\Partner\Reseller;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promotion extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'product.promotions';

    protected $fillable = [
        'company_id',
        'code',
        'promotion_type',
        'target_type',
        'target_agent_id',
        'target_reseller_id',
        'reactivates_reseller',
        'min_purchase_type',
        'min_purchase_value',
        'discount_type',
        'discount_value',
        'name',
        'description',
        'is_active',
        'starts_at',
        'ends_at',
        'trigger_level',
        'priority',
        'buy_min_qty',
        'buy_product_id',
        'buy_variant_id',
        'get_qty',
        'get_product_mode',
        'get_product_id',
        'get_variant_id',
        'get_unit_id',
        'free_warehouse_type',
        'max_applications_per_line',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'buy_min_qty' => 'float',
        'get_qty' => 'float',
        'min_purchase_value' => 'decimal:4',
        'discount_value' => 'decimal:4',
        'reactivates_reseller' => 'boolean',
        'priority' => 'integer',
        'max_applications_per_line' => 'integer',
    ];

    public function buyProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'buy_product_id');
    }

    public function buyVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'buy_variant_id');
    }

    public function getProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'get_product_id');
    }

    public function getVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'get_variant_id');
    }

    public function getUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'get_unit_id');
    }

    public function targetAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'target_agent_id');
    }

    public function targetReseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'target_reseller_id');
    }

    public function scopeProductType($query)
    {
        return $query->where('promotion_type', 'product');
    }

    public function scopeMarketingType($query)
    {
        return $query->where('promotion_type', 'marketing');
    }

    public function scopeActiveNow($query, ?string $at = null)
    {
        $at = $at ?: now()->toDateTimeString();

        return $query->where('is_active', true)
            ->where(function ($q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }

    /**
     * Auto code: PRM-YYYYMM-XXXX
     */
    public static function generateCode(?string $companyId = null): string
    {
        $prefix = 'PRM-'.date('Ym').'-';

        $last = static::withTrashed()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
