<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Payment methods per branch (Cash, Transfer, E-Wallet, etc.)
     */
    public function up(): void
    {
        Schema::create('master_data.method_payments', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('branch_id');
            $table->string('code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('branch_id');
            $table->index('is_active');
            $table->index('sort_order');
            $table->unique(['branch_id', 'code']);
        });

        Schema::table('master_data.method_payments', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data.method_payments');
    }
};
