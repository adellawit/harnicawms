<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'pgsql';

    protected $table = 'auth.agent_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_name',
        'tool_payload',
        'created_at',
    ];

    protected $casts = [
        'tool_payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'conversation_id', 'id');
    }
}
