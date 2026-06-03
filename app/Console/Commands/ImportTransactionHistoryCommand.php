<?php

namespace App\Console\Commands;

use Database\Seeders\TransactionHistorySeeder;
use Illuminate\Console\Command;

class ImportTransactionHistoryCommand extends Command
{
    protected $signature = 'transactions:import-history
                            {--file=docs/Transaksi 1 October 2024 - 2 June 2026.xlsx : Path ke file Excel transaksi}
                            {--reset : Hapus semua data sales_orders dan relasinya sebelum impor}
                            {--export-json : Simpan hasil parse ke database/seeders/data/transactions_history.json}';

    protected $description = 'Reset (opsional) dan impor histori transaksi POS dari Excel';

    public function handle(): int
    {
        $file = (string) $this->option('file');

        if (! is_file(base_path($file))) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $seeder = new TransactionHistorySeeder;
        $seeder->setCommand($this);

        $result = $seeder->import(
            base_path($file),
            reset: (bool) $this->option('reset'),
            exportJson: (bool) $this->option('export-json'),
        );

        if ($result === false) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
