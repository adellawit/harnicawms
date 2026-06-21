<?php

namespace App\Models\Partner;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerFollowup extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'partner.partner_followups';

    protected $fillable = [
        'application_id',
        'followup_by',
        'followup_type',
        'status',
        'notes',
        'next_followup_at',
    ];

    protected $casts = [
        'next_followup_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(PartnerApplication::class, 'application_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'followup_by', 'id');
    }
}
