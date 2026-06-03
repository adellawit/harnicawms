<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Cost history - track cost changes over time for COGS, reporting
     */
    public function up(): void
    {
        Schema::create('product.product_cost_history', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('product_id');
            $table->uuid('branch_id')->nullable();
            $table->uuid('unit_id')->nullable();

            $table->decimal('cost', 18, 4);
            $table->string('cost_type', 30)->default('average'); // average, fifo, lifo, standard, last_purchase
            $table->date('effective_date');
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();

            $table->uuid('created_by')->nullable();

            $table->timestamps();

            $table->index('product_id');
            $table->index('branch_id');
            $table->index('effective_date');
            $table->index(['product_id', 'effective_date']);
        });

        Schema::table('product.product_cost_history', function (Blueprint $table) {
            $table->foreign('product_id')
                ->references('id')
                ->on('product.products')
                ->onDelete('cascade');

            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');

            $table->foreign('unit_id')
                ->references('id')
                ->on('product.product_units')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product.product_cost_history');
    }
};
