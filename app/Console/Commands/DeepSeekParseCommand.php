<?php

namespace App\Console\Commands;

use App\Models\Telegram\TelegramAccount;
use App\Models\User;
use App\Services\DeepSeek\DeepSeekTransactionParser;
use App\Services\Telegram\TelegramCheckoutService;
use App\Services\Telegram\TelegramDraftResolver;
use Illuminate\Console\Command;

class DeepSeekParseCommand extends Command
{
    protected $signature = 'deepseek:parse {message : Natural language transaction message}';

    protected $description = 'Test DeepSeek transaction parser (same as Telegram POS uses)';

    public function handle(DeepSeekTransactionParser $parser): int
    {
        if (! config('deepseek.enabled') || ! config('deepseek.api_key')) {
            $this->error('DEEPSEEK_API_KEY belum diset.');

            return self::FAILURE;
        }

        try {
            $parsed = $parser->parse($this->argument('message'));
            $this->info('Parse OK');
            $this->line(json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Parse failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
