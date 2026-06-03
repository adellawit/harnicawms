<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');

        Schema::create('auth.users', function (Blueprint $table) {

            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('employee_id')->nullable();
            $table->uuid('role_id');
            $table->uuid('current_business_unit_id')->nullable();

            // === AUTH DATA ===
            $table->boolean('is_super_admin')->default(false);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('username')->unique();
            $table->string('password');
            $table->boolean('need_update_password')->default(true);
            $table->text('url_image')->nullable();

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('current_business_unit_id');
        });

        // === FK LINTAS SCHEMA (EXPLICIT) ===
        Schema::table('auth.users', function (Blueprint $table) {
            $table->foreign('employee_id')
                ->references('id')
                ->on('human_resources.employees')
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on('master_data.roles')
                ->onDelete('cascade');

            $table->foreign('current_business_unit_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth.users');
    }
};
