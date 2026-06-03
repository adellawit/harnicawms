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
        DB::statement('CREATE SCHEMA IF NOT EXISTS public');

        Schema::create('public.cities', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('province_id')->nullable();
            $table->string('name');
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('province_id');
        });

        // === FK LINTAS SCHEMA (EXPLICIT) ===
        Schema::table('public.cities', function (Blueprint $table) {
            $table->foreign('province_id')
                ->references('id')
                ->on('public.provinces')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public.cities');
    }
};
