<?php

namespace App\Console\Commands;

use App\Services\Partner\AgentPksService;
use Illuminate\Console\Command;

class RemindAgentPksExpiringCommand extends Command
{
    protected $signature = 'partners:remind-pks-expiring';

    protected $description = 'Send in-app reminders for Agent PKS ending within 30 days';

    public function handle(AgentPksService $service): int
    {
        $result = $service->sendExpiringReminders();

        $this->info(sprintf(
            'PKS reminders: candidates=%d, notified=%d',
            $result['candidates'],
            $result['notified']
        ));

        return self::SUCCESS;
    }
}
