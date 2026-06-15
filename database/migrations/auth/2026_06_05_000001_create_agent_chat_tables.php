<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');

        Schema::create('auth.agent_conversations', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('user_id');
            $table->uuid('branch_id')->nullable();
            $table->string('title', 120)->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('cascade');

            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('auth.agent_messages', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('conversation_id');
            $table->string('role', 20);
            $table->text('content')->nullable();
            $table->string('tool_name', 80)->nullable();
            $table->json('tool_payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('auth.agent_conversations')
                ->onDelete('cascade');

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('auth.agent_tool_logs', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('conversation_id');
            $table->uuid('user_id');
            $table->string('tool_name', 80);
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('auth.agent_conversations')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('cascade');

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth.agent_tool_logs');
        Schema::dropIfExists('auth.agent_messages');
        Schema::dropIfExists('auth.agent_conversations');
    }
};
