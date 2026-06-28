<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLabelSerial extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'product.product_label_serials';

    protected $fillable = [
        'serial_number',
        'year_prefix',
        'sequence',
        'product_id',
        'product_variant_id',
        'unit_id',
        'unit_level',
        'printed_by',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }
}
