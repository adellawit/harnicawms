<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hapus FK usang `product_sales_orders_warehouse_id_foreign` yang masih menunjuk
 * `transaction.sales_orders.warehouse_id` -> `master_data.business_units`.
 *
 * FK ini peninggalan saat tabel masih bernama `product.sales_orders`. Migrasi
 * `2026_06_18_000001_repoint_sales_orders_warehouse_fk` sudah mengarahkan
 * warehouse_id ke `master_data.warehouses`, tetapi hanya menghapus constraint
 * bernama `sales_orders_warehouse_id_foreign`; constraint ber-prefix `product_`
 * tak ikut terhapus, sehingga warehouse_id tetap dipaksa ada di business_units
 * dan memblokir gudang agen (yang hanya terdaftar di master_data.warehouses).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE transaction.sales_orders DROP CONSTRAINT IF EXISTS product_sales_orders_warehouse_id_foreign');
    }

    public function down(): void
    {
        // Pulihkan constraint usang bila di-rollback (menunjuk business_units seperti semula).
        DB::statement('
            ALTER TABLE transaction.sales_orders
            ADD CONSTRAINT product_sales_orders_warehouse_id_foreign
            FOREIGN KEY (warehouse_id) REFERENCES master_data.business_units(id) ON DELETE SET NULL
        ');
    }
};
