<?php

namespace App\Console\Commands\Telegram;

use App\Models\User;
use App\Services\Telegram\TelegramAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GenerateLinkCodeCommand extends Command
{
    protected $signature = 'telegram:generate-link-code
                            {user? : User ID, username, email, phone, or name from auth.users}
                            {--list : Show users from database}
                            {--limit=20 : Max users shown with --list}
                            {--created-by= : Admin user ID for audit}';

    protected $description = 'Generate Telegram link code for a cashier user (lookup from auth.users)';

    public function handle(TelegramAuthService $auth): int
    {
        if ($this->option('list')) {
            return $this->listUsers();
        }

        $identifier = trim((string) $this->argument('user'));

        if ($identifier === '') {
            $this->error('Masukkan identifier user atau gunakan --list untuk melihat data users.');
            $this->line('Contoh: php artisan telegram:generate-link-code budi.santoso');
            $this->line('       php artisan telegram:generate-link-code --list');

            return self::FAILURE;
        }

        $matches = $this->findUsers($identifier);

        if ($matches->isEmpty()) {
            $this->error('User tidak ditemukan di auth.users.');
            $this->comment('Gunakan --list untuk melihat username/email/nama yang tersedia.');

            return self::FAILURE;
        }

        if ($matches->count() > 1) {
            $this->warn('Ditemukan lebih dari 1 user. Gunakan identifier yang lebih spesifik (username / email / UUID):');
            $this->displayUsersTable($matches);

            return self::FAILURE;
        }

        $user = $matches->first();
        $linkCode = $auth->generateLinkCode($user, $this->option('created-by'));

        $this->info('Link code generated:');
        $this->line('  ID      : '.$user->id);
        $this->line('  Username: '.$user->username);
        $this->line('  Nama    : '.trim($user->first_name.' '.$user->last_name));
        $this->line('  Email   : '.($user->email ?: '-'));
        $this->line('  Phone   : '.($user->phone ?: '-'));
        $this->line('  Code    : '.$linkCode->code);
        $expiresLabel = $linkCode->expires_at?->toDateTimeString() ?? 'Tidak expired (sampai dipakai)';
        $this->line('  Expires : '.$expiresLabel);
        $this->newLine();
        $this->comment('Kasir kirim ke bot: /link '.$linkCode->code);

        return self::SUCCESS;
    }

    protected function listUsers(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $users = User::query()
            ->with('businessUnit:id,code,name,type_code')
            ->whereNull('deleted_at')
            ->orderBy('username')
            ->limit($limit)
            ->get();

        if ($users->isEmpty()) {
            $this->warn('Tidak ada user aktif di auth.users.');

            return self::SUCCESS;
        }

        $this->info("Users dari database (max {$limit}):");
        $this->displayUsersTable($users);
        $this->newLine();
        $this->comment('Generate link code: php artisan telegram:generate-link-code {username|email|uuid}');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    protected function findUsers(string $identifier): Collection
    {
        $isUuid = preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $identifier
        ) === 1;

        if ($isUuid) {
            $user = User::query()
                ->whereNull('deleted_at')
                ->where('id', $identifier)
                ->first();

            return $user !== null ? collect([$user]) : collect();
        }

        $exact = User::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($identifier) {
                $q->where('username', $identifier)
                    ->orWhere('email', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->get();

        if ($exact->isNotEmpty()) {
            return $exact;
        }

        return User::query()
            ->whereNull('deleted_at')
            ->where(function ($q) use ($identifier) {
                $q->where('username', 'ilike', $identifier)
                    ->orWhere('email', 'ilike', $identifier)
                    ->orWhere('phone', 'ilike', $identifier)
                    ->orWhere('first_name', 'ilike', "%{$identifier}%")
                    ->orWhere('last_name', 'ilike', "%{$identifier}%")
                    ->orWhereRaw("concat(first_name, ' ', last_name) ilike ?", ["%{$identifier}%"]);
            })
            ->orderBy('username')
            ->limit(10)
            ->get();
    }

    /**
     * @param  Collection<int, User>  $users
     */
    protected function displayUsersTable(Collection $users): void
    {
        $this->table(
            ['Username', 'Nama', 'Email', 'Phone', 'Branch', 'UUID'],
            $users->map(fn (User $user) => [
                $user->username,
                trim($user->first_name.' '.$user->last_name),
                $user->email ?: '-',
                $user->phone ?: '-',
                $user->businessUnit?->name ?: '-',
                $user->id,
            ])->all()
        );
    }
}
