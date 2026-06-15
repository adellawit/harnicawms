<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Telegram\TelegramCheckoutService;
use App\Services\Telegram\TelegramDraftResolver;
use Illuminate\Console\Command;

class TelegramSimulateCommand extends Command
{
    protected $signature = 'telegram:simulate
                            {user : Username/email/UUID from auth.users}
                            {message : Natural language transaction message}
                            {--submit : Submit transaksi ke database (CASH)}
                            {--payment=cash : Payment hint: cash|transfer|qris}';

    protected $description = 'Simulate Telegram POS flow locally (parse → draft → optional submit)';

    public function handle(
        TelegramDraftResolver $draftResolver,
        TelegramCheckoutService $checkout,
    ): int {
        $user = $this->resolveUser((string) $this->argument('user'));

        if ($user === null) {
            $this->error('User tidak ditemukan. Gunakan: php artisan telegram:generate-link-code --list');

            return self::FAILURE;
        }

        $branchId = $user->getBranchIdForTransaction();

        $this->info('Kasir: '.$user->username.' ('.trim($user->first_name.' '.$user->last_name).')');
        $this->line('Branch ID: '.($branchId ?: '-'));
        $this->line('Pesan: '.$this->argument('message'));
        $this->newLine();

        $result = $draftResolver->buildFromNaturalLanguage((string) $this->argument('message'), $user);

        if (($result['variant_choices'] ?? []) !== []) {
            $this->warn('Produk ambigu — di Telegram user pilih via inline keyboard.');
            $this->displayVariantChoices($result['variant_choices']);

            return self::FAILURE;
        }

        if (($result['customer_choices'] ?? []) !== []) {
            $this->warn('Customer ambigu — di Telegram user pilih via inline keyboard.');
            $this->table(['Nama', 'Code', 'ID'], collect($result['customer_choices'])->map(fn ($c) => [
                $c['name'], $c['code'], $c['id'],
            ])->all());

            return self::FAILURE;
        }

        if (! ($result['success'] ?? false)) {
            $this->error($result['message'] ?? 'Gagal memproses draft.');

            return self::FAILURE;
        }

        $draft = $result['draft'] ?? [];

        if (($result['needs_payment'] ?? false) || empty($draft['payment_method_id'])) {
            $method = $draftResolver->resolvePaymentMethod(
                (string) $draft['branch_id'],
                $this->option('payment') ?: ($draft['payment_hint'] ?? 'cash')
            );

            if ($method === null) {
                $this->error('Metode pembayaran tidak ditemukan untuk cabang ini.');

                return self::FAILURE;
            }

            $draft['payment_method_id'] = $method->id;
            $draft['payment_method_name'] = $method->name;
        }

        $this->info('Draft transaksi:');
        $this->displayDraft($draft, $draftResolver);

        if (! $this->option('submit')) {
            $this->newLine();
            $this->comment('Dry-run only. Tambahkan --submit untuk simpan ke DB.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Submit transaksi ke database?', false)) {
            $this->comment('Dibatalkan.');

            return self::SUCCESS;
        }

        $submitResult = $checkout->submit($draft, $user);

        if (! ($submitResult['success'] ?? false)) {
            $this->error($submitResult['message'] ?? 'Submit gagal.');

            return self::FAILURE;
        }

        $data = $submitResult['data'] ?? [];
        $this->newLine();
        $this->info('✅ Transaksi berhasil');
        $this->line('No      : '.($data['sales_number'] ?? '-'));
        $this->line('Customer: '.($data['customer_name'] ?? '-'));
        $this->line('Total   : Rp '.number_format((float) ($data['total'] ?? 0), 0, ',', '.'));
        $this->line('Payment : '.($data['payment_method'] ?? '-'));

        return self::SUCCESS;
    }

    protected function resolveUser(string $identifier): ?User
    {
        $query = User::query()->whereNull('deleted_at');

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where(function ($q) use ($identifier) {
                $q->where('username', $identifier)
                    ->orWhere('email', $identifier);
            });
        }

        return $query->first();
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function displayDraft(array $draft, TelegramDraftResolver $draftResolver): void
    {
        $this->line('Customer: '.($draft['customer_name'] ?? 'Walk-in Customer'));
        $this->table(
            ['Produk', 'Qty', 'Harga', 'Subtotal'],
            collect($draft['items'] ?? [])->map(fn ($item) => [
                $item['label'] ?? '-',
                $item['quantity'] ?? 0,
                number_format((float) ($item['unit_price'] ?? 0), 0, ',', '.'),
                number_format((float) ($item['line_total'] ?? 0), 0, ',', '.'),
            ])->all()
        );
        $this->line('Total   : Rp '.number_format($draftResolver->calculateDraftTotal($draft), 0, ',', '.'));
        $this->line('Payment : '.($draft['payment_method_name'] ?? '-'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $choices
     */
    protected function displayVariantChoices(array $choices): void
    {
        foreach ($choices as $choice) {
            $this->line('Query: '.($choice['query'] ?? '-').' x'.($choice['quantity'] ?? 1));
            $this->table(
                ['#', 'Produk', 'Harga'],
                collect($choice['options'] ?? [])->values()->map(fn ($opt, $i) => [
                    $i,
                    $opt['label'] ?? '-',
                    number_format((float) ($opt['unit_price'] ?? 0), 0, ',', '.'),
                ])->all()
            );
        }
    }
}
