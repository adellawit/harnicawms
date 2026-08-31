<?php

namespace App\Services\Ai\Actions;

use Illuminate\Support\Facades\Cache;

class AgentTourStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $conversationId): ?array
    {
        $state = Cache::get($this->key($conversationId));

        return is_array($state) ? $state : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function put(string $conversationId, array $state): void
    {
        $ttl = (int) config('agent.draft_ttl_minutes', 60);

        Cache::put($this->key($conversationId), $state, now()->addMinutes($ttl));
    }

    public function forget(string $conversationId): void
    {
        Cache::forget($this->key($conversationId));
    }

    /**
     * @param  array<int, string>  $keys
     * @param  list<array<string, mixed>>  $steps
     * @return array<string, mixed>
     */
    public function start(array $keys, int $index, string $mode = 'overview', array $steps = []): array
    {
        return [
            'active' => true,
            'index' => max(0, $index),
            'keys' => array_values($keys),
            'mode' => $mode,
            'steps' => array_values($steps),
        ];
    }

    protected function key(string $conversationId): string
    {
        return 'agent:tour:'.$conversationId;
    }
}
