<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner.cutting_price_configs', function (Blueprint $table) {
            $table->decimal('reseller_price_30', 18, 4)->nullable()->after('map_price');
            $table->decimal('reseller_price_60', 18, 4)->nullable()->after('reseller_price_30');
            $table->decimal('reseller_price_120', 18, 4)->nullable()->after('reseller_price_60');
            $table->decimal('agent_price_600', 18, 4)->nullable()->after('reseller_price_120');
        });
    }

    public function down(): void
    {
        Schema::table('partner.cutting_price_configs', function (Blueprint $table) {
            $table->dropColumn([
                'reseller_price_30',
                'reseller_price_60',
                'reseller_price_120',
                'agent_price_600',
            ]);
        });
    }
};
