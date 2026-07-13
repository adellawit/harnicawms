<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $agentCustomerIds = DB::table('partner.agents')
            ->whereNull('deleted_at')
            ->whereNotNull('customer_id')
            ->pluck('customer_id');

        if ($agentCustomerIds->isNotEmpty()) {
            DB::table('customer.customers')
                ->whereIn('id', $agentCustomerIds)
                ->where(function ($query) {
                    $query->whereNull('customer_type')
                        ->orWhere('customer_type', 'PARTNER_LEAD');
                })
                ->update([
                    'customer_type' => 'AGENT',
                    'updated_at' => now(),
                ]);
        }

        $resellerCustomerIds = DB::table('partner.resellers')
            ->whereNull('deleted_at')
            ->whereNotNull('customer_id')
            ->pluck('customer_id');

        if ($resellerCustomerIds->isNotEmpty()) {
            DB::table('customer.customers')
                ->whereIn('id', $resellerCustomerIds)
                ->where(function ($query) {
                    $query->whereNull('customer_type')
                        ->orWhereIn('customer_type', ['PARTNER_LEAD', 'AGENT']);
                })
                ->update([
                    'customer_type' => 'RESELLER',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Data migration only — no rollback.
    }
};
