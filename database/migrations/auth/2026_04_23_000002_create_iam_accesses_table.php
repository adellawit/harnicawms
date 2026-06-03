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

        Schema::create('auth.iam_accesses', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('role_id');
            $table->boolean('is_notification')->default(false);

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('role_id');
        });

        // === FK LINTAS SCHEMA (EXPLICIT) ===
        Schema::table('auth.iam_accesses', function (Blueprint $table) {
            $table->foreign('role_id')
                ->references('id')
                ->on('master_data.roles')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth.iam_accesses');
    }
};
