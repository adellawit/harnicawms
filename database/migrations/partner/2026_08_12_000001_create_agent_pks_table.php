<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner.agents')) {
            return;
        }

        Schema::create('partner.agent_pks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('agent_id');
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('file_mime', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('agent_id');
            $table->index(['status', 'end_date']);
            $table->foreign('agent_id')->references('id')->on('partner.agents')->onDelete('cascade');
        });

        // At most one active PKS per agent (soft-deleted excluded).
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS agent_pks_one_active_per_agent
            ON partner.agent_pks (agent_id)
            WHERE status = 'active' AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS partner.agent_pks_one_active_per_agent');
        Schema::dropIfExists('partner.agent_pks');
    }
};
