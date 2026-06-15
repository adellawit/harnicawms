<?php

namespace App\Services\Telegram;

use App\Models\TelegramSession;
use Carbon\Carbon;

class TelegramSessionService
{
    public function get(int $telegramUserId): TelegramSession
    {
        $session = TelegramSession::query()
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        if ($session === null || $session->expires_at->isPast()) {
            return $this->reset($telegramUserId);
        }

        return $session;
    }

    public function reset(int $telegramUserId): TelegramSession
    {
        $ttl = config('telegram.session_ttl_minutes', 30);

        return TelegramSession::query()->updateOrCreate(
            ['telegram_user_id' => $telegramUserId],
            [
                'state' => 'idle',
                'payload' => [],
                'expires_at' => Carbon::now()->addMinutes($ttl),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function setState(int $telegramUserId, string $state, array $payload = []): TelegramSession
    {
        $ttl = config('telegram.session_ttl_minutes', 30);

        return TelegramSession::query()->updateOrCreate(
            ['telegram_user_id' => $telegramUserId],
            [
                'state' => $state,
                'payload' => $payload,
                'expires_at' => Carbon::now()->addMinutes($ttl),
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $merge
     */
    public function mergePayload(int $telegramUserId, array $merge, ?string $state = null): TelegramSession
    {
        $session = $this->get($telegramUserId);
        $payload = array_merge($session->payload ?? [], $merge);

        return $this->setState(
            $telegramUserId,
            $state ?? $session->state,
            $payload
        );
    }

    public function clear(int $telegramUserId): TelegramSession
    {
        return $this->reset($telegramUserId);
    }
}
