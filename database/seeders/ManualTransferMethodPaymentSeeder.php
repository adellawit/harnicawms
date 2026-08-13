<?php

namespace Database\Seeders;

use App\Models\MethodPayment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManualTransferMethodPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $branches = DB::table('master_data.business_units')
            ->where('type_code', 'BRANCH')
            ->where('is_active', true)
            ->get();

        foreach ($branches as $branch) {
            MethodPayment::updateOrCreate(
                ['branch_id' => $branch->id, 'code' => 'MANUAL_TRANSFER'],
                [
                    'name' => 'Transfer Manual (Bank)',
                    'description' => 'Transfer bank manual dengan kode unik (verifikasi admin)',
                    'sort_order' => 5,
                    'is_active' => true,
                    'uses_payment_gateway' => false,
                    'gateway_provider' => null,
                    'payment_group_code' => null,
                    'gateway_channel_code' => null,
                ]
            );
        }
    }
}
