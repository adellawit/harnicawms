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

        Schema::create('master_data.shipping_rates', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('origin_city_id');
            $table->uuid('destination_city_id');
            $table->string('courier_code', 30);
            $table->string('service_code', 30);
            $table->string('service_name', 100)->nullable();
            $table->decimal('base_amount', 18, 2)->default(0);
            $table->decimal('per_kg_amount', 18, 2)->default(0);
            $table->unsignedSmallInteger('etd_min_days')->nullable();
            $table->unsignedSmallInteger('etd_max_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('origin_city_id');
            $table->index('destination_city_id');
            $table->index('courier_code');
            $table->index('is_active');
        });

        Schema::table('master_data.shipping_rates', function (Blueprint $table) {
            $table->foreign('origin_city_id')
                ->references('id')->on('public.cities')->onDelete('restrict');
            $table->foreign('destination_city_id')
                ->references('id')->on('public.cities')->onDelete('restrict');
        });

        DB::statement('
            CREATE UNIQUE INDEX shipping_rates_unique_active
            ON master_data.shipping_rates (
                origin_city_id,
                destination_city_id,
                courier_code,
                service_code
            )
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data.shipping_rates');
    }
};
