<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth.telegram_link_codes', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('auth.telegram_link_codes', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }
};
