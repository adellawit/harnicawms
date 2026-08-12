<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner.partner_applications')) {
            return;
        }

        Schema::table('partner.partner_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('partner.partner_applications', 'marketplace_tokopedia_account')) {
                $table->string('marketplace_tokopedia_account', 200)->nullable()->after('marketplace_tokopedia');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'marketplace_shopee_account')) {
                $table->string('marketplace_shopee_account', 200)->nullable()->after('marketplace_shopee');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner.partner_applications')) {
            return;
        }

        Schema::table('partner.partner_applications', function (Blueprint $table) {
            if (Schema::hasColumn('partner.partner_applications', 'marketplace_tokopedia_account')) {
                $table->dropColumn('marketplace_tokopedia_account');
            }
            if (Schema::hasColumn('partner.partner_applications', 'marketplace_shopee_account')) {
                $table->dropColumn('marketplace_shopee_account');
            }
        });
    }
};
