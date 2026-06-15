<?php

namespace Database\Seeders;

use App\Models\TelegramAccount;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TelegramAccountSeeder extends Seeder
{
    /**
     * Seed auth.telegram_accounts dari auth.users (dev/staging).
     *
     * telegram_user_id & chat_id dummy deterministik agar konsisten tiap seed.
     * Base ID: 900000001 (user ke-1), 900000002 (user ke-2), dst.
     */
    public function run(): void
    {
        $users = User::query()
            ->whereNull('deleted_at')
            ->orderBy('username')
            ->get(['id', 'username', 'first_name', 'last_name']);

        if ($users->isEmpty()) {
            $this->command?->warn('Skip TelegramAccountSeeder: auth.users kosong. Jalankan UserSeeder dulu.');

            return;
        }

        $baseTelegramId = (int) config('telegram.seed_base_telegram_id', 900000001);
        $seeded = 0;

        foreach ($users as $index => $user) {
            $telegramUserId = $baseTelegramId + $index;
            $telegramUsername = $this->buildTelegramUsername($user->username, $user->first_name);

            TelegramAccount::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'telegram_user_id' => $telegramUserId,
                    'telegram_username' => $telegramUsername,
                    'chat_id' => $telegramUserId,
                    'is_active' => true,
                    'linked_at' => now(),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'deleted_at' => null,
                ]
            );

            $seeded++;
        }

        $this->command?->info("Telegram accounts seeded: {$seeded} record(s) from auth.users.");
        $this->command?->comment('Dev telegram_user_id range: '.$baseTelegramId.' - '.($baseTelegramId + $seeded - 1));
    }

    protected function buildTelegramUsername(string $username, string $firstName): string
    {
        $fromEmail = Str::of($username)
            ->before('@')
            ->lower()
            ->replace('.', '_')
            ->replace('-', '_')
            ->substr(0, 32)
            ->toString();

        if ($fromEmail !== '') {
            return $fromEmail;
        }

        return Str::of($firstName)->lower()->slug('_')->substr(0, 32)->toString() ?: 'kasir';
    }
}
