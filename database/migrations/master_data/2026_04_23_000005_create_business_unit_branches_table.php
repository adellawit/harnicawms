<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE SCHEMA IF NOT EXISTS master_data');

        Schema::create('master_data.business_unit_branches', function (Blueprint $table) {
            $table->uuid('branch_id');
            $table->uuid('warehouse_id');
            $table->boolean('is_default')->default(false);
            $table->primary(['branch_id', 'warehouse_id']);

            $table->index('branch_id');
            $table->index('warehouse_id');
            $table->index('is_default');
        });

        // === FK LINTAS SCHEMA (EXPLICIT) ===
        Schema::table('master_data.business_unit_branches', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data.business_unit_branches');
    }
};
