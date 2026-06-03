<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE SCHEMA IF NOT EXISTS master_data');

        Schema::create('master_data.menus', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('code');
            $table->string('text_sidebar');
            $table->string('icon')->nullable();
            $table->boolean('has_page')->default(false);
            $table->string('url_path')->nullable();
            $table->string('route_name')->nullable();
            $table->string('slug')->nullable();
            $table->integer('level_sidebar')->default(1);
            $table->integer('order_number')->default(0);
            $table->boolean('is_label')->default(false);
            $table->boolean('has_create')->default(false);
            $table->boolean('has_update')->default(false);
            $table->boolean('has_read')->default(false);
            $table->boolean('has_delete')->default(false);
            $table->boolean('has_custom1')->default(false);
            $table->boolean('has_custom2')->default(false);
            $table->boolean('has_custom3')->default(false);
            $table->boolean('has_custom4')->default(false);
            $table->boolean('has_custom5')->default(false);

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('slug');
        });

        // === FK LINTAS SCHEMA (EXPLICIT) ===
        // Self-referencing foreign key (dibuat setelah tabel)
        Schema::table('master_data.menus', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('master_data.menus')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data.menus');
    }
};
