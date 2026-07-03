<?php

namespace App\Console\Commands;

use App\Models\BusinessUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ProductionResetCommand extends Command
{
    protected $signature = 'production:reset
                            {--force : Lewati konfirmasi}';

    protected $description = 'Reset fitur Produksi (manufacturing) tanpa menghapus master data';

    /** @var list<string> */
    protected array $manufacturingMigrationFiles = [
        'database/migrations/manufacturing/2026_06_14_000001_create_manufacturing_bom_tables.php',
        'database/migrations/manufacturing/2026_06_14_000002_create_production_orders_tables.php',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Reset data Produksi (BOM, production order, stok WIP/FG terkait produksi)? Master data tidak dihapus.')) {
            $this->comment('Dibatalkan.');

            return self::SUCCESS;
        }

        $this->info('========================================');
        $this->info('   Reset Fitur Produksi');
        $this->info('========================================');
        $this->newLine();

        $warehouseIds = $this->warehouseIds();
        if ($warehouseIds === []) {
            $this->warn('Gudang WH-WIP / WH-FG tidak ditemukan — lanjut tanpa bersihkan stok gudang.');
        }

        DB::transaction(function () use ($warehouseIds) {
            $this->cleanProductionStockArtifacts($warehouseIds);
            $this->resetManufacturingSchema();
        });

        $this->runManufacturingMigrations();

        $this->newLine();
        $this->info('Reset Produksi selesai.');
        $this->comment('Master data (produk, cabang, user, menu) tetap utuh.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function warehouseIds(): array
    {
        return BusinessUnit::query()
            ->where('type_code', 'WAREHOUSE')
            ->whereIn('code', ['WH-WIP', 'WH-FG'])
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<string>  $warehouseIds
     */
    protected function cleanProductionStockArtifacts(array $warehouseIds): void
    {
        $this->info('Step 1: Bersihkan jejak stok produksi...');

        $movementCount = DB::table('product.product_stock_movements')
            ->whereIn('reference_type', ['ProductionConsume', 'ProductionOutput'])
            ->delete();
        $this->line("  - {$movementCount} movement produksi dihapus");

        if ($warehouseIds !== []) {
            $layerCount = DB::table('product.product_cost_layers')
                ->whereIn('branch_id', $warehouseIds)
                ->delete();
            $this->line("  - {$layerCount} cost layer di gudang WIP/FG dihapus");

            $variantStockCount = DB::table('product.product_variant_stock')
                ->whereIn('branch_id', $warehouseIds)
                ->delete();
            $this->line("  - {$variantStockCount} baris product_variant_stock di gudang WIP/FG dihapus");

            $legacyStockCount = DB::table('product.product_stock')
                ->whereIn('branch_id', $warehouseIds)
                ->delete();
            $this->line("  - {$legacyStockCount} baris product_stock (legacy) di gudang WIP/FG dihapus");

            $warehouseMovementCount = DB::table('product.product_stock_movements')
                ->whereIn('branch_id', $warehouseIds)
                ->delete();
            $this->line("  - {$warehouseMovementCount} movement lain di gudang WIP/FG dihapus");
        }

        $this->newLine();
    }

    protected function resetManufacturingSchema(): void
    {
        $this->info('Step 2: Reset schema manufacturing...');

        DB::statement('DROP SCHEMA IF EXISTS manufacturing CASCADE');
        DB::statement('CREATE SCHEMA IF NOT EXISTS manufacturing');

        $migrationNames = [
            '2026_06_14_000001_create_manufacturing_bom_tables',
            '2026_06_14_000002_create_production_orders_tables',
        ];

        $removed = DB::table('migrations')->whereIn('migration', $migrationNames)->delete();
        $this->line("  - Schema manufacturing di-drop & dibuat ulang ({$removed} entri migration dihapus)");
        $this->newLine();
    }

    protected function runManufacturingMigrations(): void
    {
        $this->info('Step 3: Jalankan ulang migration manufacturing...');

        foreach ($this->manufacturingMigrationFiles as $path) {
            $this->line("  Migrating {$path}");
            Artisan::call('migrate', [
                '--path' => $path,
                '--force' => true,
            ]);
            $this->output->write(Artisan::output());
        }

        $this->newLine();
    }
}
