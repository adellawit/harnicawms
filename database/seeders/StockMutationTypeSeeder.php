<?php

namespace Database\Seeders;

use App\Models\StockMutationType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockMutationTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('TRUNCATE TABLE public.stock_mutation_types RESTART IDENTITY CASCADE');

        $types = [
            // === Mutasi Stok Masuk ===
            [
                'id' => 'a1000001-0001-4000-8000-000000000001',
                'code' => 'PURCHASE_RECEIPT',
                'name' => 'Pembelian Masuk',
                'direction' => 'in',
                'description' => 'Barang diterima dari supplier',
            ],
            [
                'id' => 'a1000001-0001-4000-8000-000000000002',
                'code' => 'TRANSFER_IN',
                'name' => 'Transfer Masuk',
                'direction' => 'in',
                'description' => 'Barang diterima dari warehouse / branch lain',
            ],
            [
                'id' => 'a1000001-0001-4000-8000-000000000003',
                'code' => 'PRODUCTION_RESULT',
                'name' => 'Hasil Produksi',
                'direction' => 'in',
                'description' => 'Barang jadi masuk dari proses produksi',
            ],
            [
                'id' => 'a1000001-0001-4000-8000-000000000004',
                'code' => 'RETURN_FROM_CUSTOMER',
                'name' => 'Retur Customer',
                'direction' => 'in',
                'description' => 'Barang dikembalikan oleh customer',
            ],
            [
                'id' => 'a1000001-0001-4000-8000-000000000005',
                'code' => 'STOCK_ADJUSTMENT_IN',
                'name' => 'Adjustment Lebih',
                'direction' => 'in',
                'description' => 'Selisih opname (fisik lebih banyak)',
            ],
            [
                'id' => 'a1000001-0001-4000-8000-000000000006',
                'code' => 'CONVERSION_IN',
                'name' => 'Konversi Masuk',
                'direction' => 'in',
                'description' => 'Hasil pecah kemasan / repack',
            ],
            [
                'id' => 'a1000001-0001-4000-8000-000000000007',
                'code' => 'INITIAL_BALANCE',
                'name' => 'Saldo Awal',
                'direction' => 'in',
                'description' => 'Input stok awal saat sistem go-live',
            ],

            // === Mutasi Stok Keluar ===
            [
                'id' => 'a1000001-0002-4000-8000-000000000001',
                'code' => 'SALES',
                'name' => 'Penjualan',
                'direction' => 'out',
                'description' => 'Barang keluar karena transaksi penjualan',
            ],
            [
                'id' => 'a1000001-0002-4000-8000-000000000002',
                'code' => 'TRANSFER_OUT',
                'name' => 'Transfer Keluar',
                'direction' => 'out',
                'description' => 'Kirim ke warehouse / branch lain',
            ],
            [
                'id' => 'a1000001-0002-4000-8000-000000000003',
                'code' => 'PRODUCTION_USAGE',
                'name' => 'Pemakaian Produksi',
                'direction' => 'out',
                'description' => 'Product dipakai produksi',
            ],
            [
                'id' => 'a1000001-0002-4000-8000-000000000004',
                'code' => 'RETURN_TO_SUPPLIER',
                'name' => 'Retur Supplier',
                'direction' => 'out',
                'description' => 'Barang dikembalikan ke supplier',
            ],
            [
                'id' => 'a1000001-0002-4000-8000-000000000005',
                'code' => 'STOCK_ADJUSTMENT_OUT',
                'name' => 'Adjustment Kurang',
                'direction' => 'out',
                'description' => 'Selisih opname (fisik lebih sedikit)',
            ],
            [
                'id' => 'a1000001-0002-4000-8000-000000000006',
                'code' => 'SCRAP',
                'name' => 'Barang Rusak',
                'direction' => 'out',
                'description' => 'Barang rusak / tidak layak',
            ],
            [
                'id' => 'a1000001-0002-4000-8000-000000000007',
                'code' => 'EXPIRED',
                'name' => 'Kadaluarsa',
                'direction' => 'out',
                'description' => 'Barang expired',
            ],
            [
                'id' => 'a1000001-0002-4000-8000-000000000008',
                'code' => 'CONVERSION_OUT',
                'name' => 'Konversi Keluar',
                'direction' => 'out',
                'description' => 'Bahan utama keluar untuk repack',
            ],
        ];

        foreach ($types as $type) {
            StockMutationType::create(array_merge($type, [
                'is_active' => true,
            ]));
        }

        $this->command->info('Stock Mutation Types seeded successfully! (7 incoming + 8 outgoing = 15 types)');
    }
}
