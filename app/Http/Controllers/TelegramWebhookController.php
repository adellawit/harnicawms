<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramConversationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramConversationService $conversation,
        protected TelegramBotService $bot,
    ) {}

    public function handle(Request $request)
    {
        if (! config('telegram.enabled')) {
            return response()->json(['message' => 'Telegram disabled'], 503);
        }

        $secret = config('telegram.webhook_secret');

        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            Log::warning('Telegram webhook rejected: invalid secret token');

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $update = $request->all();

        try {
            $this->conversation->handleUpdate($update);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook handler error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
