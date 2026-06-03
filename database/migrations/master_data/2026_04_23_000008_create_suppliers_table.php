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

        Schema::create('master_data.suppliers', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->uuid('supplier_type_id')->nullable();
            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->string('contact', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('supplier_type_id');
            $table->index('company_id');
            $table->index('branch_id');
            $table->index('is_active');
        });

        Schema::table('master_data.suppliers', function (Blueprint $table) {
            $table->foreign('supplier_type_id')
                ->references('id')
                ->on('public.parameter_details')
                ->onDelete('set null');

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
        Schema::dropIfExists('master_data.suppliers');
    }
};
