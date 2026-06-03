<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move sales_orders, sales_order_items, sales_order_item_modifiers
     * from product schema to transaction schema.
     */
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS transaction');

        // Drop foreign keys first (they reference product.* tables)
        DB::statement('ALTER TABLE product.sales_order_item_modifiers DROP CONSTRAINT IF EXISTS sales_order_item_modifiers_sales_order_item_id_foreign');
        DB::statement('ALTER TABLE product.sales_order_item_modifiers DROP CONSTRAINT IF EXISTS sales_order_item_modifiers_modifier_option_id_foreign');
        DB::statement('ALTER TABLE product.sales_order_items DROP CONSTRAINT IF EXISTS sales_order_items_sales_order_id_foreign');
        DB::statement('ALTER TABLE product.sales_order_items DROP CONSTRAINT IF EXISTS sales_order_items_product_id_foreign');
        DB::statement('ALTER TABLE product.sales_order_items DROP CONSTRAINT IF EXISTS sales_order_items_product_variant_id_foreign');
        DB::statement('ALTER TABLE product.sales_order_items DROP CONSTRAINT IF EXISTS sales_order_items_unit_id_foreign');
        DB::statement('ALTER TABLE product.sales_orders DROP CONSTRAINT IF EXISTS sales_orders_company_id_foreign');
        DB::statement('ALTER TABLE product.sales_orders DROP CONSTRAINT IF EXISTS sales_orders_branch_id_foreign');
        DB::statement('ALTER TABLE product.sales_orders DROP CONSTRAINT IF EXISTS sales_orders_warehouse_id_foreign');

        // Move tables to transaction schema
        DB::statement('ALTER TABLE product.sales_order_item_modifiers SET SCHEMA transaction');
        DB::statement('ALTER TABLE product.sales_order_items SET SCHEMA transaction');
        DB::statement('ALTER TABLE product.sales_orders SET SCHEMA transaction');

        // Re-add foreign keys with new schema references
        DB::statement('ALTER TABLE transaction.sales_orders ADD CONSTRAINT sales_orders_company_id_foreign FOREIGN KEY (company_id) REFERENCES master_data.business_units(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE transaction.sales_orders ADD CONSTRAINT sales_orders_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES master_data.business_units(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE transaction.sales_orders ADD CONSTRAINT sales_orders_warehouse_id_foreign FOREIGN KEY (warehouse_id) REFERENCES master_data.business_units(id) ON DELETE SET NULL');

        DB::statement('ALTER TABLE transaction.sales_order_items ADD CONSTRAINT sales_order_items_sales_order_id_foreign FOREIGN KEY (sales_order_id) REFERENCES transaction.sales_orders(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE transaction.sales_order_items ADD CONSTRAINT sales_order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES product.products(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE transaction.sales_order_items ADD CONSTRAINT sales_order_items_product_variant_id_foreign FOREIGN KEY (product_variant_id) REFERENCES product.product_variants(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE transaction.sales_order_items ADD CONSTRAINT sales_order_items_unit_id_foreign FOREIGN KEY (unit_id) REFERENCES product.product_units(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE transaction.sales_order_item_modifiers ADD CONSTRAINT sales_order_item_modifiers_sales_order_item_id_foreign FOREIGN KEY (sales_order_item_id) REFERENCES transaction.sales_order_items(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transaction.sales_order_item_modifiers DROP CONSTRAINT IF EXISTS sales_order_item_modifiers_sales_order_item_id_foreign');
        DB::statement('ALTER TABLE transaction.sales_order_item_modifiers DROP CONSTRAINT IF EXISTS sales_order_item_modifiers_modifier_option_id_foreign');
        DB::statement('ALTER TABLE transaction.sales_order_items DROP CONSTRAINT IF EXISTS sales_order_items_sales_order_id_foreign');
        DB::statement('ALTER TABLE transaction.sales_order_items DROP CONSTRAINT IF EXISTS sales_order_items_product_id_foreign');
        DB::statement('ALTER TABLE transaction.sales_order_items DROP CONSTRAINT IF EXISTS sales_order_items_product_variant_id_foreign');
        DB::statement('ALTER TABLE transaction.sales_order_items DROP CONSTRAINT IF EXISTS sales_order_items_unit_id_foreign');
        DB::statement('ALTER TABLE transaction.sales_orders DROP CONSTRAINT IF EXISTS sales_orders_company_id_foreign');
        DB::statement('ALTER TABLE transaction.sales_orders DROP CONSTRAINT IF EXISTS sales_orders_branch_id_foreign');
        DB::statement('ALTER TABLE transaction.sales_orders DROP CONSTRAINT IF EXISTS sales_orders_warehouse_id_foreign');

        DB::statement('ALTER TABLE transaction.sales_order_item_modifiers SET SCHEMA product');
        DB::statement('ALTER TABLE transaction.sales_order_items SET SCHEMA product');
        DB::statement('ALTER TABLE transaction.sales_orders SET SCHEMA product');

        DB::statement('ALTER TABLE product.sales_orders ADD CONSTRAINT sales_orders_company_id_foreign FOREIGN KEY (company_id) REFERENCES master_data.business_units(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE product.sales_orders ADD CONSTRAINT sales_orders_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES master_data.business_units(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE product.sales_orders ADD CONSTRAINT sales_orders_warehouse_id_foreign FOREIGN KEY (warehouse_id) REFERENCES master_data.business_units(id) ON DELETE SET NULL');

        DB::statement('ALTER TABLE product.sales_order_items ADD CONSTRAINT sales_order_items_sales_order_id_foreign FOREIGN KEY (sales_order_id) REFERENCES product.sales_orders(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE product.sales_order_items ADD CONSTRAINT sales_order_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES product.products(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE product.sales_order_items ADD CONSTRAINT sales_order_items_product_variant_id_foreign FOREIGN KEY (product_variant_id) REFERENCES product.product_variants(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE product.sales_order_items ADD CONSTRAINT sales_order_items_unit_id_foreign FOREIGN KEY (unit_id) REFERENCES product.product_units(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE product.sales_order_item_modifiers ADD CONSTRAINT sales_order_item_modifiers_sales_order_item_id_foreign FOREIGN KEY (sales_order_item_id) REFERENCES product.sales_order_items(id) ON DELETE CASCADE');
    }
};
