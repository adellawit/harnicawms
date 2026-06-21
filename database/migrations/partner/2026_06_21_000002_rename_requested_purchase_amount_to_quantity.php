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

        if (Schema::hasColumn('partner.partner_applications', 'requested_purchase_amount')
            && ! Schema::hasColumn('partner.partner_applications', 'requested_purchase_quantity')) {
            Schema::table('partner.partner_applications', function (Blueprint $table) {
                $table->renameColumn('requested_purchase_amount', 'requested_purchase_quantity');
            });

            return;
        }

        if (! Schema::hasColumn('partner.partner_applications', 'requested_purchase_quantity')) {
            Schema::table('partner.partner_applications', function (Blueprint $table) {
                $table->decimal('requested_purchase_quantity', 18, 4)->default(0)->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner.partner_applications')) {
            return;
        }

        if (Schema::hasColumn('partner.partner_applications', 'requested_purchase_quantity')
            && ! Schema::hasColumn('partner.partner_applications', 'requested_purchase_amount')) {
            Schema::table('partner.partner_applications', function (Blueprint $table) {
                $table->renameColumn('requested_purchase_quantity', 'requested_purchase_amount');
            });
        }
    }
};
