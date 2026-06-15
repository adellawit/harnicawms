<?php

namespace App\Models\Ai;

use App\Models\BusinessUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentConversation extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'auth.agent_conversations';

    protected $fillable = [
        'user_id',
        'branch_id',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'branch_id', 'id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'conversation_id', 'id')
            ->orderBy('created_at');
    }
}
