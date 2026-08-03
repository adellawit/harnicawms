<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuration.app_theme_settings', function (Blueprint $table) {
            $table->string('navbar_color', 7)->nullable()->after('secondary_color');
            $table->string('sidebar_color', 7)->nullable()->after('navbar_color');
            $table->string('background_color', 7)->nullable()->after('sidebar_color');
        });
    }

    public function down(): void
    {
        Schema::table('configuration.app_theme_settings', function (Blueprint $table) {
            $table->dropColumn(['navbar_color', 'sidebar_color', 'background_color']);
        });
    }
};
