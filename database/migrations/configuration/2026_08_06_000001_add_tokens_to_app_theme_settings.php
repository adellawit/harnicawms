<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration.app_theme_settings', function (Blueprint $table) {
            // Keep independent of surface-color migration order (Postgres ignores after()).
            $table->jsonb('tokens_light')->nullable();
            $table->jsonb('tokens_dark')->nullable();
            $table->string('preview_mode', 10)->default('light');
        });
    }

    public function down(): void
    {
        Schema::table('configuration.app_theme_settings', function (Blueprint $table) {
            $table->dropColumn(['tokens_light', 'tokens_dark', 'preview_mode']);
        });
    }
};
