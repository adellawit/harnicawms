<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Multi-warehouse support
     * Stock per warehouse (warehouse belongs to branch via business_unit_branches)
     */
    public function up(): void
    {
        // product_stock: keep branch_id (will be used for warehouse selection via business_unit_branches)
        // No changes needed - keeping branch_id for now

        // product_stock_movements: keep branch_id
        // No changes needed - keeping branch_id for now
    }

    public function down(): void
    {
        // No changes to rollback
    }
};
