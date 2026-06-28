<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product.product_label_serials', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->string('serial_number', 20)->unique();
            $table->string('year_prefix', 2);
            $table->unsignedBigInteger('sequence');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('printed_by')->nullable();

            $table->timestamps();

            $table->index(['year_prefix', 'sequence']);
            $table->index('product_id');
        });

        Schema::table('product.product_label_serials', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('cascade');

            $table->foreign('product_variant_id')
                ->references('id')
                ->on('product.product_variants')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_label_serials');
    }
};
