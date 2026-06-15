<?php

namespace App\Console\Commands;

use App\Services\DeepSeek\DeepSeekService;
use Illuminate\Console\Command;

class DeepSeekTestCommand extends Command
{
    protected $signature = 'deepseek:test {message? : Sample transaction message}';

    protected $description = 'Test DeepSeek API connectivity';

    public function handle(DeepSeekService $deepSeek): int
    {
        if (! $deepSeek->isConfigured()) {
            $this->error('DEEPSEEK_API_KEY belum diset atau DEEPSEEK_ENABLED=false.');

            return self::FAILURE;
        }

        $message = $this->argument('message') ?? 'Balas dengan json: {"status":"ok","service":"deepseek"}';

        try {
            $response = $deepSeek->chat([
                ['role' => 'system', 'content' => 'You must respond with valid json only. Include the word json in your thinking.'],
                ['role' => 'user', 'content' => $message],
            ]);

            $content = data_get($response, 'choices.0.message.content');

            $this->info('DeepSeek OK');
            $this->line(is_string($content) ? $content : json_encode($response, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('DeepSeek test failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
