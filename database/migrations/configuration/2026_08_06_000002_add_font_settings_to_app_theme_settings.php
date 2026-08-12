<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration.app_theme_settings', function (Blueprint $table) {
            $table->string('font_source', 20)->default('preset');
            $table->string('font_preset', 50)->default('dm-sans');
            $table->string('font_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('configuration.app_theme_settings', function (Blueprint $table) {
            $table->dropColumn(['font_source', 'font_preset', 'font_path']);
        });
    }
};
