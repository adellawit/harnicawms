<?php

namespace Database\Seeders;

use App\Models\ProductPriceList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductPriceListSeeder extends Seeder
{
    private const ALLOWED_CODES = [
        'REGULER',
    ];

    /**
     * Hapus price list lama, lalu seed hanya Reguler.
     */
    public function run(): void
    {
        $company = DB::table('master_data.business_units')
            ->where('code', 'SUHARA-001')
            ->where('type_code', 'COMPANY')
            ->first();

        if (! $company) {
            $this->command?->error('Company Suhara Botanica tidak ditemukan. Jalankan BusinessUnitSeeder terlebih dahulu.');

            return;
        }

        $obsoleteIds = ProductPriceList::withTrashed()
            ->whereNotIn('code', self::ALLOWED_CODES)
            ->pluck('id');

        if ($obsoleteIds->isNotEmpty()) {
            DB::table('customer.customer_groups')
                ->whereIn('price_list_id', $obsoleteIds)
                ->update(['price_list_id' => null]);

            if (DB::getSchemaBuilder()->hasTable('transaction.sales_orders')) {
                DB::table('transaction.sales_orders')
                    ->whereIn('price_list_id', $obsoleteIds)
                    ->update(['price_list_id' => null]);
            }

            DB::table('product.product_variant_prices')
                ->whereIn('price_list_id', $obsoleteIds)
                ->delete();

            DB::table('product.product_prices')
                ->whereIn('price_list_id', $obsoleteIds)
                ->delete();

            $deleted = ProductPriceList::withTrashed()
                ->whereIn('id', $obsoleteIds)
                ->forceDelete();

            $this->command?->info("Price list lama dihapus: {$deleted} record.");
        }

        $priceLists = [
            [
                'code' => 'REGULER',
                'name' => 'Reguler',
                'description' => 'Harga jual reguler (dine-in / default POS)',
                'channel_type' => 'pos',
                'external_channel_code' => null,
                'sort_order' => 10,
            ],
        ];

        foreach ($priceLists as $pl) {
            ProductPriceList::withTrashed()->updateOrCreate(
                ['code' => $pl['code']],
                [
                    'name' => $pl['name'],
                    'description' => $pl['description'],
                    'channel_type' => $pl['channel_type'],
                    'external_channel_code' => $pl['external_channel_code'],
                    'sort_order' => $pl['sort_order'],
                    'is_active' => true,
                    'company_id' => $company->id,
                    'branch_id' => null,
                    'deleted_at' => null,
                ]
            );
        }

        $this->command?->info('Price list aktif: Reguler.');
    }
}
