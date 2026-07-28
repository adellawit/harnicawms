<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner.resellers', function (Blueprint $table): void {
            $table->dropForeign(['agent_id']);
        });

        DB::statement('ALTER TABLE partner.resellers ALTER COLUMN agent_id DROP NOT NULL');

        Schema::table('partner.resellers', function (Blueprint $table): void {
            $table->foreign('agent_id')
                ->references('id')
                ->on('partner.agents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Cannot safely restore NOT NULL if null rows exist.
        Schema::table('partner.resellers', function (Blueprint $table): void {
            $table->dropForeign(['agent_id']);
        });

        Schema::table('partner.resellers', function (Blueprint $table): void {
            $table->foreign('agent_id')
                ->references('id')
                ->on('partner.agents')
                ->cascadeOnDelete();
        });
    }
};
