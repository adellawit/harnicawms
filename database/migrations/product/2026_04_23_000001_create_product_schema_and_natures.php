<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Unified Product Master - Schema & Product Natures
     * Schema 'product' untuk menyatukan Finished Goods, Raw Material, Semi-Finished, Service, Bundle.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE SCHEMA IF NOT EXISTS product');

        // === Product Natures (unified, supports hierarchy) ===
        Schema::create('product.product_natures', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('branch_id');
            $table->index('parent_id');
        });

        Schema::table('product.product_natures', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('parent_id')
                ->references('id')
                ->on('product.product_natures')
                ->onDelete('set null');
        });

        // === Product Units (unified) ===
        Schema::create('product.product_units', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('symbol', 20)->nullable();
            $table->text('description')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('branch_id');
        });

        Schema::table('product.product_units', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_natures');
        Schema::dropIfExists('product.product_units');
        DB::statement('DROP SCHEMA IF EXISTS product CASCADE');
    }
};
