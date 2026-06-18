<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE SCHEMA IF NOT EXISTS master_data');

        Schema::create('master_data.warehouses', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id');
            $table->uuid('branch_id')->nullable();
            $table->string('warehouse_type_code', 30)->default('GENERAL');

            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('short_name', 50)->nullable();
            $table->string('legal_name', 200)->nullable();

            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 50)->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_inventory_active')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            // Maps legacy master_data.business_units row (type_code = WAREHOUSE) during transition.
            $table->uuid('legacy_business_unit_id')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->unique('legacy_business_unit_id');

            $table->index('company_id');
            $table->index('branch_id');
            $table->index('warehouse_type_code');
            $table->index('is_default');
            $table->index('is_active');
        });

        Schema::table('master_data.warehouses', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');

            $table->foreign('warehouse_type_code')
                ->references('code')
                ->on('master_data.warehouse_types')
                ->onDelete('restrict');

            $table->foreign('legacy_business_unit_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');
        });

        // Satu gudang default per cabang (gudang milik cabang).
        DB::statement('
            CREATE UNIQUE INDEX warehouses_one_default_per_branch
            ON master_data.warehouses (branch_id)
            WHERE is_default = true
              AND branch_id IS NOT NULL
              AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS master_data.warehouses_one_default_per_branch');
        Schema::dropIfExists('master_data.warehouses');
    }
};
