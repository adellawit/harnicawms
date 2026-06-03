<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Pastikan schema configuration ada (PostgreSQL)
        DB::statement('CREATE SCHEMA IF NOT EXISTS configuration');

        Schema::connection('pgsql')->create('configuration.dashboard_configuration_team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('role_id');
            $table->uuid('employee_id');

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['role_id', 'employee_id'], 'dashboard_conf_team_members_role_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('configuration.dashboard_configuration_team_members');
    }
};

