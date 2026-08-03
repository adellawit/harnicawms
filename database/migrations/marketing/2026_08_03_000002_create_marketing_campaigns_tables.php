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

        Schema::create('marketing.campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('banner_path')->nullable();
            $table->uuid('promotion_id')->nullable();
            $table->boolean('reactivates_reseller')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('priority')->default(0);
            $table->string('status', 20)->default('active');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('promotion_id')->references('id')->on('product.promotions')->nullOnDelete();
            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index('company_id');
        });

        Schema::create('marketing.campaign_participants', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('campaign_id');
            $table->uuid('reseller_id');
            $table->uuid('sales_order_id')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->foreign('campaign_id')->references('id')->on('marketing.campaigns')->cascadeOnDelete();
            $table->foreign('reseller_id')->references('id')->on('partner.resellers')->cascadeOnDelete();
            $table->index(['campaign_id', 'reseller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing.campaign_participants');
        Schema::dropIfExists('marketing.campaigns');
    }
};
