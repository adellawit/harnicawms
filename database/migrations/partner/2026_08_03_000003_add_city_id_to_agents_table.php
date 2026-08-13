<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner.agents', function (Blueprint $table) {
            if (! Schema::hasColumn('partner.agents', 'city_id')) {
                $table->uuid('city_id')->nullable()->after('city');
                $table->foreign('city_id')->references('id')->on('public.cities')->nullOnDelete();
                $table->index('city_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner.agents', function (Blueprint $table) {
            if (Schema::hasColumn('partner.agents', 'city_id')) {
                $table->dropForeign(['city_id']);
                $table->dropIndex(['city_id']);
                $table->dropColumn('city_id');
            }
        });
    }
};
