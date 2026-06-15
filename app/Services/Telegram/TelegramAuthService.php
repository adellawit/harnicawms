<?php

namespace App\Services\Telegram;

use App\Models\Telegram\TelegramAccount;
use App\Models\Telegram\TelegramLinkCode;
use App\Models\User;
use Illuminate\Support\Str;

class TelegramAuthService
{
    public function findActiveAccount(int $telegramUserId): ?TelegramAccount
    {
        return TelegramAccount::query()
            ->with('user.businessUnit')
            ->where('telegram_user_id', $telegramUserId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->first();
    }

    public function generateLinkCode(User $user, ?string $createdBy = null): TelegramLinkCode
    {
        TelegramLinkCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->delete();

        $ttl = (int) config('telegram.link_code_ttl_minutes', 60);

        return TelegramLinkCode::create([
            'user_id' => $user->id,
            'code' => Str::upper(Str::random(8)),
            'expires_at' => $ttl > 0 ? now()->addMinutes($ttl) : null,
            'created_by' => $createdBy ?? $user->id,
        ]);
    }

    /**
     * @return array{success: bool, message: string, account?: TelegramAccount}
     */
    public function linkAccount(string $code, int $telegramUserId, ?string $telegramUsername, int $chatId): array
    {
        $linkCode = TelegramLinkCode::query()
            ->with('user')
            ->whereRaw('UPPER(code) = ?', [Str::upper(trim($code))])
            ->first();

        if ($linkCode === null || ! $linkCode->isValid()) {
            return [
                'success' => false,
                'message' => 'Kode link tidak valid atau sudah expired.',
            ];
        }

        $existing = TelegramAccount::query()
            ->where('telegram_user_id', $telegramUserId)
            ->whereNull('deleted_at')
            ->first();

        if ($existing !== null && $existing->user_id !== $linkCode->user_id) {
            return [
                'success' => false,
                'message' => 'Akun Telegram ini sudah terhubung ke user lain.',
            ];
        }

        $account = TelegramAccount::query()->updateOrCreate(
            ['telegram_user_id' => $telegramUserId],
            [
                'user_id' => $linkCode->user_id,
                'telegram_username' => $telegramUsername,
                'chat_id' => $chatId,
                'is_active' => true,
                'linked_at' => now(),
                'deleted_at' => null,
                'updated_by' => $linkCode->user_id,
            ]
        );

        $linkCode->update([
            'used_at' => now(),
            'used_by_telegram_user_id' => $telegramUserId,
        ]);

        $userName = trim($linkCode->user->first_name.' '.$linkCode->user->last_name);

        return [
            'success' => true,
            'message' => "Akun berhasil dihubungkan ke {$userName}.",
            'account' => $account->load('user'),
        ];
    }
}
