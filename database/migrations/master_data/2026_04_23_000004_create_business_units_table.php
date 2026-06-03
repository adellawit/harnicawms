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

        Schema::create('master_data.business_units', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('parent_id')->nullable();
            $table->string('type_code', 30);
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('brand_name', 150)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('legal_name', 200)->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('nib', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 50)->nullable();
            $table->boolean('is_pos_active')->default(false);
            $table->boolean('is_inventory_active')->default(false);
            $table->string('tax_type', 30)->nullable()->default('NONE');
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('service_charge_percentage', 5, 2)->default(0);
            $table->string('timezone', 50)->nullable();
            $table->string('currency', 10)->nullable();
            $table->date('opening_date')->nullable();
            $table->boolean('is_active')->default(true);

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['code', 'type_code']);
            $table->index('parent_id');
            $table->index('type_code');
            $table->index('is_active');
        });

        // === FK LINTAS SCHEMA (EXPLICIT) ===
        Schema::table('master_data.business_units', function (Blueprint $table) {
            $table->foreign('type_code')
                ->references('code')
                ->on('master_data.business_unit_types')
                ->onDelete('restrict');

            $table->foreign('parent_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data.business_units');
    }
};
