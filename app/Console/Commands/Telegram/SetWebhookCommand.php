<?php

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
                            {url? : Public HTTPS webhook URL (default: APP_URL/webhooks/telegram)}
                            {--delete : Delete webhook instead}';

    protected $description = 'Register or delete Telegram bot webhook';

    public function handle(TelegramBotService $bot): int
    {
        if (! $bot->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN belum diset.');

            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $bot->request('deleteWebhook');
            $this->info('Webhook deleted.');

            return self::SUCCESS;
        }

        $url = $this->argument('url') ?? rtrim(config('app.url'), '/').'/webhooks/telegram';

        $payload = [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query', 'edited_message'],
        ];

        if ($secret = config('telegram.webhook_secret')) {
            $payload['secret_token'] = $secret;
        }

        $bot->request('setWebhook', $payload);

        $info = Http::timeout(20)
            ->acceptJson()
            ->get(
                rtrim(config('telegram.api_base_url'), '/').'/bot'.config('telegram.bot_token').'/getWebhookInfo'
            )
            ->json();

        $this->info('Webhook registered: '.$url);
        $this->line('Pending updates: '.data_get($info, 'result.pending_update_count', 0));

        return self::SUCCESS;
    }
}
