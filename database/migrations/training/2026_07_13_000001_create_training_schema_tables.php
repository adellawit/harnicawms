<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS training');

        Schema::create('training.course_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->string('name', 150);
            $table->string('color', 7)->nullable();   // hex e.g. #5C9E84
            $table->string('icon', 60)->nullable();    // e.g. ti-briefcase
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
        });

        Schema::create('training.courses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->uuid('category_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('status', 20)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index('category_id');
            $table->index('status');
        });

        Schema::create('training.course_modules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->uuid('course_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('course_id');
        });

        Schema::create('training.course_materials', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->uuid('module_id');
            $table->string('title', 200);
            $table->string('type', 20); // pdf | image | youtube
            $table->string('file_path')->nullable();
            $table->string('youtube_url')->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('module_id');
        });

        Schema::create('training.material_progress', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('user_id');
            $table->uuid('material_id');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'material_id']);
            $table->index('material_id');
        });

        Schema::create('training.course_access', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('user_id');
            $table->uuid('course_id');
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->uuid('last_material_id')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
            $table->index('course_id');
        });

        Schema::table('training.courses', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('training.course_categories')->onDelete('restrict');
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('set null');
        });
        Schema::table('training.course_modules', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('training.courses')->onDelete('cascade');
        });
        Schema::table('training.course_materials', function (Blueprint $table) {
            $table->foreign('module_id')->references('id')->on('training.course_modules')->onDelete('cascade');
        });
        Schema::table('training.material_progress', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');
            $table->foreign('material_id')->references('id')->on('training.course_materials')->onDelete('cascade');
        });
        Schema::table('training.course_access', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('training.courses')->onDelete('cascade');
            $table->foreign('last_material_id')->references('id')->on('training.course_materials')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training.course_access');
        Schema::dropIfExists('training.material_progress');
        Schema::dropIfExists('training.course_materials');
        Schema::dropIfExists('training.course_modules');
        Schema::dropIfExists('training.courses');
        Schema::dropIfExists('training.course_categories');
    }
};
