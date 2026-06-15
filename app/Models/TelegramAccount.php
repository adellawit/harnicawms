<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TelegramAccount extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'auth.telegram_accounts';

    protected $fillable = [
        'user_id',
        'telegram_user_id',
        'telegram_username',
        'chat_id',
        'is_active',
        'linked_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'telegram_user_id' => 'integer',
        'chat_id' => 'integer',
        'is_active' => 'boolean',
        'linked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
