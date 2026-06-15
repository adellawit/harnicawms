<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramLinkCode extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'auth.telegram_link_codes';

    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
        'used_at',
        'used_by_telegram_user_id',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'used_by_telegram_user_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function isValid(): bool
    {
        if ($this->used_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
