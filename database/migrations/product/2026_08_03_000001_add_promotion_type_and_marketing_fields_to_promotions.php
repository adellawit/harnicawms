<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('product.promotions', 'promotion_type')) {
                $table->string('promotion_type', 20)->default('product')->after('code');
            }
            if (! Schema::hasColumn('product.promotions', 'target_type')) {
                $table->string('target_type', 20)->nullable()->after('promotion_type');
            }
            if (! Schema::hasColumn('product.promotions', 'target_agent_id')) {
                $table->uuid('target_agent_id')->nullable()->after('target_type');
            }
            if (! Schema::hasColumn('product.promotions', 'target_reseller_id')) {
                $table->uuid('target_reseller_id')->nullable()->after('target_agent_id');
            }
            if (! Schema::hasColumn('product.promotions', 'reactivates_reseller')) {
                $table->boolean('reactivates_reseller')->default(false)->after('target_reseller_id');
            }
            if (! Schema::hasColumn('product.promotions', 'min_purchase_type')) {
                $table->string('min_purchase_type', 10)->nullable()->after('reactivates_reseller');
            }
            if (! Schema::hasColumn('product.promotions', 'min_purchase_value')) {
                $table->decimal('min_purchase_value', 18, 4)->nullable()->after('min_purchase_type');
            }
            if (! Schema::hasColumn('product.promotions', 'discount_type')) {
                $table->string('discount_type', 10)->nullable()->after('min_purchase_value');
            }
            if (! Schema::hasColumn('product.promotions', 'discount_value')) {
                $table->decimal('discount_value', 18, 4)->nullable()->after('discount_type');
            }
        });

        foreach (['buy_min_qty', 'get_qty', 'get_product_mode', 'free_warehouse_type'] as $column) {
            DB::statement("ALTER TABLE product.promotions ALTER COLUMN {$column} DROP NOT NULL");
        }

        Schema::table('product.promotions', function (Blueprint $table) {
            $table->foreign('target_agent_id')->references('id')->on('partner.agents')->nullOnDelete();
            $table->foreign('target_reseller_id')->references('id')->on('partner.resellers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product.promotions', function (Blueprint $table) {
            $table->dropForeign(['target_agent_id']);
            $table->dropForeign(['target_reseller_id']);

            foreach ([
                'discount_value',
                'discount_type',
                'min_purchase_value',
                'min_purchase_type',
                'reactivates_reseller',
                'target_reseller_id',
                'target_agent_id',
                'target_type',
                'promotion_type',
            ] as $column) {
                if (Schema::hasColumn('product.promotions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
