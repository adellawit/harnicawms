<?php

namespace App\Models\Ai;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentToolLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'pgsql';

    protected $table = 'auth.agent_tool_logs';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'tool_name',
        'input',
        'output',
        'duration_ms',
        'created_at',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
