<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS marketing');

        Schema::create('marketing.asset_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->string('name', 150);
            $table->string('color', 7)->nullable();
            $table->string('icon', 60)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
        });

        Schema::create('marketing.assets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->uuid('category_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 20);              // image | video | pdf | text
            $table->string('file_path')->nullable(); // image | pdf
            $table->string('link_url', 500)->nullable(); // video
            $table->text('body_text')->nullable();   // text (WA)
            $table->boolean('usable_in_marketing')->default(true);
            $table->boolean('usable_in_training')->default(false);
            $table->boolean('can_be_thumbnail')->default(false);
            $table->string('status', 20)->default('draft'); // draft | active
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index('category_id');
            $table->index('type');
            $table->index('status');
        });

        Schema::table('marketing.assets', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('marketing.asset_categories')->onDelete('restrict');
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing.assets');
        Schema::dropIfExists('marketing.asset_categories');
    }
};
