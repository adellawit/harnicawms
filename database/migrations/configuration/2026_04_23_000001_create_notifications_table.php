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
        DB::statement('CREATE SCHEMA IF NOT EXISTS configuration');

        Schema::create('configuration.notifications', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('user_id');
            $table->string('type')->default('info'); // info, warning, error, success
            $table->string('title');
            $table->text('message')->nullable();

            // Module untuk grouping (misal: reimbursement, task, mom, dll)
            $table->string('module')->nullable();

            // Polymorphic relation untuk related model
            $table->string('related_id')->nullable();
            $table->string('related_type')->nullable();

            $table->string('url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('type');
            $table->index('module');
            $table->index('is_read');
            $table->index(['related_id', 'related_type']);
        });

        // === FK LINTAS SCHEMA (EXPLICIT) ===
        Schema::table('configuration.notifications', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration.notifications');
    }
};
