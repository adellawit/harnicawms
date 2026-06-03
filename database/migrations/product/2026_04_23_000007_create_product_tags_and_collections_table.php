<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Product tags (best_seller, product_focus, new_arrival) and collections
     */
    public function up(): void
    {
        Schema::create('product.product_tags', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();

            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('type', 30)->default('general'); // general, best_seller, product_focus, featured
            $table->string('color', 20)->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('branch_id');
            $table->index('type');
        });

        Schema::table('product.product_tags', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');
        });

        Schema::create('product.product_tag_assignments', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_id');
            $table->uuid('product_tag_id');
            $table->integer('sort_order')->default(0);

            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['product_id', 'product_tag_id']);
            $table->index('product_id');
            $table->index('product_tag_id');
        });

        Schema::table('product.product_tag_assignments', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('cascade');

            $table->foreign('product_tag_id')
                ->references('id')
                ->on('product.product_tags')
                ->onDelete('cascade');
        });

        Schema::create('product.product_collections', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('parent_id')->nullable();

            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('slug', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('sort_order')->default(0);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('branch_id');
            $table->index('parent_id');
            $table->index('slug');
        });

        Schema::table('product.product_collections', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('parent_id')
                ->references('id')
                ->on('product.product_collections')
                ->onDelete('set null');
        });

        Schema::create('product.product_collection_assignments', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_id');
            $table->uuid('product_collection_id');
            $table->integer('sort_order')->default(0);

            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['product_id', 'product_collection_id']);
            $table->index('product_id');
            $table->index('product_collection_id');
        });

        Schema::table('product.product_collection_assignments', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('cascade');

            $table->foreign('product_collection_id')
                ->references('id')
                ->on('product.product_collections')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_collection_assignments');
        Schema::dropIfExists('product.product_collections');
        Schema::dropIfExists('product.product_tag_assignments');
        Schema::dropIfExists('product.product_tags');
    }
};
