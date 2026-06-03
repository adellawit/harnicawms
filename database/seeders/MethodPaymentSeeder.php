<?php

namespace Database\Seeders;

use App\Models\MethodPayment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MethodPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $branches = DB::table('master_data.business_units')
            ->where('type_code', 'BRANCH')
            ->where('is_active', true)
            ->get();

        if ($branches->isEmpty()) {
            $this->command?->warn('No active branches found. Run BusinessUnitSeeder first.');
            return;
        }

        $methods = [
            [
                'code' => 'CASH',
                'name' => 'Cash / Tunai',
                'description' => 'Pembayaran tunai',
                'sort_order' => 1,
            ],
            [
                'code' => 'TRANSFER',
                'name' => 'Bank Transfer',
                'description' => 'Transfer antar bank (BCA, Mandiri, BNI, BRI, dll)',
                'sort_order' => 2,
            ],
            [
                'code' => 'QRIS',
                'name' => 'QRIS',
                'description' => 'Pembayaran via QRIS (GoPay, OVO, Dana, ShopeePay, dll)',
                'sort_order' => 3,
            ],
            [
                'code' => 'EWALLET',
                'name' => 'E-Wallet',
                'description' => 'Pembayaran via e-wallet (GoPay, OVO, Dana, ShopeePay)',
                'sort_order' => 4,
            ],
        ];

        $created = 0;

        foreach ($branches as $branch) {
            foreach ($methods as $m) {
                $usesPg = in_array($m['code'], ['TRANSFER', 'QRIS', 'EWALLET'], true);

                MethodPayment::firstOrCreate(
                    ['branch_id' => $branch->id, 'code' => $m['code']],
                    array_merge($m, [
                        'branch_id' => $branch->id,
                        'is_active' => true,
                        'uses_payment_gateway' => $usesPg,
                        'gateway_provider' => $usesPg ? 'xendit' : null,
                        'payment_group_code' => $usesPg ? $m['code'] : null,
                        'gateway_channel_code' => null,
                    ])
                );
                $created++;
            }
        }

        $branchCount = $branches->count();
        $methodCount = count($methods);
        $this->command?->info("Method payments seeded: {$methodCount} methods x {$branchCount} branch(es) = {$created} rows.");
    }
}
