<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training.course_materials', function (Blueprint $table) {
            $table->uuid('marketing_asset_id')->nullable()->after('youtube_url');
            $table->index('marketing_asset_id');
            $table->foreign('marketing_asset_id')->references('id')->on('marketing.assets')->onDelete('restrict');
        });

        Schema::table('training.courses', function (Blueprint $table) {
            $table->uuid('thumbnail_asset_id')->nullable()->after('thumbnail_path');
            $table->index('thumbnail_asset_id');
            $table->foreign('thumbnail_asset_id')->references('id')->on('marketing.assets')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('training.course_materials', function (Blueprint $table) {
            $table->dropForeign(['marketing_asset_id']);
            $table->dropColumn('marketing_asset_id');
        });
        Schema::table('training.courses', function (Blueprint $table) {
            $table->dropForeign(['thumbnail_asset_id']);
            $table->dropColumn('thumbnail_asset_id');
        });
    }
};
