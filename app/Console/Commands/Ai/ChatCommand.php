<?php

namespace App\Console\Commands\Ai;

use App\Models\User;
use App\Services\Ai\WmsAgentService;
use Illuminate\Console\Command;

class ChatCommand extends Command
{
    protected $signature = 'agent:chat
        {message : Natural language message}
        {--user-id= : Auth user UUID}
        {--conversation-id= : Existing conversation UUID}';

    protected $description = 'Test WMS agent chat from CLI (same service as web widget)';

    public function handle(WmsAgentService $agent): int
    {
        if (! config('agent.enabled')) {
            $this->error('AGENT_ENABLED=false');

            return self::FAILURE;
        }

        $userId = $this->option('user-id');

        if (! $userId) {
            $this->error('--user-id is required');

            return self::FAILURE;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            $this->error("User not found: {$userId}");

            return self::FAILURE;
        }

        $result = $agent->handle(
            $user,
            (string) $this->argument('message'),
            $this->option('conversation-id'),
        );

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
