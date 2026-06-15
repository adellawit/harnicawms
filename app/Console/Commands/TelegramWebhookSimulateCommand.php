<?php

namespace App\Console\Commands;

use App\Models\TelegramAccount;
use App\Services\Telegram\TelegramConversationService;
use Illuminate\Console\Command;

class TelegramWebhookSimulateCommand extends Command
{
    protected $signature = 'telegram:webhook-simulate
                            {message : Message text e.g. "/transaksi 3 kopi tunai"}
                            {--telegram-id= : Telegram user ID (default: first seeded account)}
                            {--chat-id= : Chat ID (default: same as telegram-id)}';

    protected $description = 'Simulate Telegram webhook payload locally (requires TELEGRAM_MOCK=true)';

    public function handle(TelegramConversationService $conversation): int
    {
        if (! config('telegram.enabled')) {
            $this->error('Set TELEGRAM_ENABLED=true di .env');

            return self::FAILURE;
        }

        if (! config('telegram.mock')) {
            $this->warn('Disarankan TELEGRAM_MOCK=true agar tidak hit Telegram API.');
        }

        $telegramUserId = (int) ($this->option('telegram-id') ?: TelegramAccount::query()->orderBy('telegram_user_id')->value('telegram_user_id') ?: 900000002);
        $chatId = (int) ($this->option('chat-id') ?: $telegramUserId);
        $text = (string) $this->argument('message');

        $account = TelegramAccount::query()
            ->with('user')
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        if ($account !== null) {
            $this->info('Telegram ID '.$telegramUserId.' → user '.$account->user?->username);
        } else {
            $this->warn('Telegram ID '.$telegramUserId.' belum ter-link. Gunakan /link di bot atau seeded account.');
        }

        $this->line('Simulating: '.$text);
        $this->newLine();

        $update = [
            'update_id' => random_int(100000, 999999),
            'message' => [
                'message_id' => 1,
                'from' => [
                    'id' => $telegramUserId,
                    'is_bot' => false,
                    'first_name' => 'Local',
                    'username' => 'local_test',
                ],
                'chat' => [
                    'id' => $chatId,
                    'type' => 'private',
                ],
                'date' => time(),
                'text' => $text,
            ],
        ];

        try {
            $conversation->handleUpdate($update);
            $this->info('Webhook simulation selesai. Cek output di storage/logs/laravel.log (mode MOCK).');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Simulation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
