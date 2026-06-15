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

        Schema::create('auth.telegram_accounts', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('user_id');
            $table->bigInteger('telegram_user_id')->unique();
            $table->string('telegram_username')->nullable();
            $table->bigInteger('chat_id');
            $table->boolean('is_active')->default(true);
            $table->timestamp('linked_at')->useCurrent();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('cascade');

            $table->index('user_id');
            $table->index('chat_id');
        });

        Schema::create('auth.telegram_link_codes', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('user_id');
            $table->string('code', 32)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->bigInteger('used_by_telegram_user_id')->nullable();

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('cascade');

            $table->index(['code', 'expires_at']);
        });

        Schema::create('auth.telegram_sessions', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->bigInteger('telegram_user_id')->unique();
            $table->string('state', 50)->default('idle');
            $table->json('payload')->nullable();
            $table->timestamp('expires_at');

            $table->timestamps();

            $table->index(['telegram_user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth.telegram_sessions');
        Schema::dropIfExists('auth.telegram_link_codes');
        Schema::dropIfExists('auth.telegram_accounts');
    }
};
