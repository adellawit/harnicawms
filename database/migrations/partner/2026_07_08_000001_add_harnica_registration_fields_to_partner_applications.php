<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner.partner_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('partner.partner_applications', 'birth_place')) {
                $table->string('birth_place', 100)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('birth_place');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'address_ktp')) {
                $table->text('address_ktp')->nullable()->after('birth_date');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'marketplace_tokopedia')) {
                $table->boolean('marketplace_tokopedia')->default(false)->after('postal_code');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'marketplace_shopee')) {
                $table->boolean('marketplace_shopee')->default(false)->after('marketplace_tokopedia');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'marketplace_other')) {
                $table->string('marketplace_other', 200)->nullable()->after('marketplace_shopee');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'reseller_package')) {
                $table->string('reseller_package', 10)->nullable()->after('marketplace_other');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'terms_accepted')) {
                $table->json('terms_accepted')->nullable()->after('reseller_package');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'declaration_accepted')) {
                $table->boolean('declaration_accepted')->default(false)->after('terms_accepted');
            }
            if (! Schema::hasColumn('partner.partner_applications', 'filled_at')) {
                $table->date('filled_at')->nullable()->after('declaration_accepted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partner.partner_applications', function (Blueprint $table) {
            $columns = [
                'birth_place',
                'birth_date',
                'address_ktp',
                'marketplace_tokopedia',
                'marketplace_shopee',
                'marketplace_other',
                'reseller_package',
                'terms_accepted',
                'declaration_accepted',
                'filled_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('partner.partner_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
