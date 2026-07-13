<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS configuration');

        Schema::create('configuration.app_theme_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('primary_color', 7)->default('#5C9E84');
            $table->string('secondary_color', 7)->default('#7BB5A0');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('color_mode', 20)->default('logo_extract'); // logo_extract | custom
            $table->boolean('glass_enabled')->default(true);
            $table->boolean('motion_enabled')->default(true);
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        DB::table('configuration.app_theme_settings')->insert([
            'id' => (string) Str::uuid(),
            'primary_color' => '#5C9E84',
            'secondary_color' => '#7BB5A0',
            'logo_path' => null,
            'favicon_path' => null,
            'color_mode' => 'logo_extract',
            'glass_enabled' => true,
            'motion_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration.app_theme_settings');
    }
};
