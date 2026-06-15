<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TelegramSession extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'auth.telegram_sessions';

    protected $fillable = [
        'telegram_user_id',
        'state',
        'payload',
        'expires_at',
    ];

    protected $casts = [
        'telegram_user_id' => 'integer',
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];
}
