<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner.partner_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('partner.partner_applications', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('postal_code');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner.partner_applications', function (Blueprint $table) {
            if (Schema::hasColumn('partner.partner_applications', 'longitude')) {
                $table->dropColumn('longitude');
            }
            if (Schema::hasColumn('partner.partner_applications', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
