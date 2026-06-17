<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InventoryResetCommand extends Command
{
    protected $signature = 'inventory:reset
                            {--force : Lewati konfirmasi}';

    protected $description = 'Reset stok, PO, dan transaksi item tanpa menghapus master produk';

    /** @var list<string> */
    protected array $tables = [
        // Sales / POS
        'transaction.sales_order_item_modifiers',
        'transaction.sales_order_payments',
        'transaction.sales_order_items',
        'transaction.payment_gateway_callbacks',
        'transaction.sales_orders',
        // Distribusi (jejak stok antar gudang/agen)
        'distribution.return_items',
        'distribution.returns',
        'distribution.receipt_items',
        'distribution.receipts',
        'distribution.shipment_items',
        'distribution.shipments',
        'distribution.replenishment_order_items',
        'distribution.replenishment_orders',
        // Produksi (transaksi, BOM tetap)
        'manufacturing.production_order_materials',
        'manufacturing.production_order_outputs',
        'manufacturing.production_orders',
        // Purchase Order
        'product.purchase_order_receive_items',
        'product.purchase_order_receives',
        'product.purchase_order_items',
        'product.purchase_orders',
        // Stok & HPP
        'product.product_stock_movements',
        'product.product_batch_stock',
        'product.product_cost_layers',
        'product.product_cost_history',
        'product.product_batches',
        'product.product_variant_stock',
        'product.product_stock',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Reset stok, PO, sales order, dan transaksi produksi? Master produk TIDAK dihapus.')) {
            $this->comment('Dibatalkan.');

            return self::SUCCESS;
        }

        $this->info('========================================');
        $this->info('   Reset Stok & Transaksi Inventory');
        $this->info('========================================');
        $this->newLine();

        $productCount = DB::table('product.products')->whereNull('deleted_at')->count();

        DB::transaction(function () {
            $existing = $this->existingTables();
            if ($existing === []) {
                $this->warn('Tidak ada tabel transaksi yang ditemukan.');

                return;
            }

            $list = implode(', ', $existing);
            DB::statement("TRUNCATE TABLE {$list} RESTART IDENTITY CASCADE");
            $this->info('Tabel yang di-reset:');
            foreach ($existing as $table) {
                $this->line("  - {$table}");
            }
        });

        $productAfter = DB::table('product.products')->whereNull('deleted_at')->count();

        $this->newLine();
        $this->info("Selesai. Master produk tetap: {$productAfter} produk (sebelum: {$productCount}).");
        $this->comment('Yang dipertahankan: products, variants, kategori, harga master, BOM, supplier, master data.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function existingTables(): array
    {
        return array_values(array_filter($this->tables, function (string $qualified) {
            [$schema, $table] = explode('.', $qualified, 2);

            return DB::table('information_schema.tables')
                ->where('table_schema', $schema)
                ->where('table_name', $table)
                ->exists();
        }));
    }
}
