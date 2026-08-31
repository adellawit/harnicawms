<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product.promotion_target_agents', function (Blueprint $table) {
            $table->uuid('promotion_id');
            $table->uuid('agent_id');
            $table->primary(['promotion_id', 'agent_id']);
            $table->foreign('promotion_id')->references('id')->on('product.promotions')->cascadeOnDelete();
            $table->foreign('agent_id')->references('id')->on('partner.agents')->cascadeOnDelete();
        });

        Schema::create('product.promotion_target_resellers', function (Blueprint $table) {
            $table->uuid('promotion_id');
            $table->uuid('reseller_id');
            $table->primary(['promotion_id', 'reseller_id']);
            $table->foreign('promotion_id')->references('id')->on('product.promotions')->cascadeOnDelete();
            $table->foreign('reseller_id')->references('id')->on('partner.resellers')->cascadeOnDelete();
        });

        // Migrasi data target lama (single) -> pivot.
        DB::statement('INSERT INTO product.promotion_target_agents (promotion_id, agent_id)
                       SELECT id, target_agent_id FROM product.promotions WHERE target_agent_id IS NOT NULL');
        DB::statement('INSERT INTO product.promotion_target_resellers (promotion_id, reseller_id)
                       SELECT id, target_reseller_id FROM product.promotions WHERE target_reseller_id IS NOT NULL');

        // Buang kolom single (FK dulu).
        Schema::table('product.promotions', function (Blueprint $table) {
            $table->dropForeign(['target_agent_id']);
            $table->dropForeign(['target_reseller_id']);
            $table->dropColumn(['target_agent_id', 'target_reseller_id']);
        });
    }

    public function down(): void
    {
        Schema::table('product.promotions', function (Blueprint $table) {
            $table->uuid('target_agent_id')->nullable()->after('target_type');
            $table->uuid('target_reseller_id')->nullable()->after('target_agent_id');
            $table->foreign('target_agent_id')->references('id')->on('partner.agents')->nullOnDelete();
            $table->foreign('target_reseller_id')->references('id')->on('partner.resellers')->nullOnDelete();
        });

        Schema::dropIfExists('product.promotion_target_resellers');
        Schema::dropIfExists('product.promotion_target_agents');
    }
};
