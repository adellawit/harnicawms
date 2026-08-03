<?php

namespace App\Models\Marketing;

use App\Models\Partner\Reseller;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignParticipant extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'marketing.campaign_participants';

    public $timestamps = true;

    protected $fillable = [
        'campaign_id',
        'reseller_id',
        'sales_order_id',
        'joined_at',
        'created_by',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'reseller_id');
    }
}
