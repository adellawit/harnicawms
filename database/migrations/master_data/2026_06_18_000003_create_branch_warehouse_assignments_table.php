<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menghubungkan cabang ke gudang pusat (warehouses.branch_id IS NULL)
     * atau gudang shared yang melayani lebih dari satu cabang.
     */
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS master_data');

        Schema::create('master_data.branch_warehouse_assignments', function (Blueprint $table) {
            $table->uuid('branch_id');
            $table->uuid('warehouse_id');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('priority')->default(0);

            $table->primary(['branch_id', 'warehouse_id']);

            $table->index('branch_id');
            $table->index('warehouse_id');
            $table->index('is_default');
            $table->index('priority');
        });

        Schema::table('master_data.branch_warehouse_assignments', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('master_data.warehouses')
                ->onDelete('cascade');
        });

        // Satu gudang default per cabang (via assignment, untuk gudang pusat/shared).
        DB::statement('
            CREATE UNIQUE INDEX branch_warehouse_assignments_one_default_per_branch
            ON master_data.branch_warehouse_assignments (branch_id)
            WHERE is_default = true
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS master_data.branch_warehouse_assignments_one_default_per_branch');
        Schema::dropIfExists('master_data.branch_warehouse_assignments');
    }
};
