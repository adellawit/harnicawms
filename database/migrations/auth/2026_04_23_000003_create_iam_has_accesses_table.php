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
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');

        Schema::create('auth.iam_has_accesses', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('iam_access_id');
            $table->uuid('sidebar_menu_id');
            $table->boolean('is_create')->default(false);
            $table->boolean('is_read')->default(false);
            $table->boolean('is_update')->default(false);
            $table->boolean('is_delete')->default(false);
            $table->boolean('is_custom_1')->default(false);
            $table->boolean('is_custom_2')->default(false);
            $table->boolean('is_custom_3')->default(false);
            $table->boolean('is_custom_4')->default(false);
            $table->boolean('is_custom_5')->default(false);

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('iam_access_id');
            $table->index('sidebar_menu_id');
        });

        // === FK LINTAS SCHEMA (EXPLICIT) ===
        Schema::table('auth.iam_has_accesses', function (Blueprint $table) {
            $table->foreign('iam_access_id')
                ->references('id')
                ->on('auth.iam_accesses')
                ->onDelete('cascade');

            $table->foreign('sidebar_menu_id')
                ->references('id')
                ->on('master_data.menus')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth.iam_has_accesses');
    }
};
