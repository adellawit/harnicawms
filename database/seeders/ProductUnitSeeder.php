<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\ProductUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductUnitSeeder extends Seeder
{
    private const ALLOWED_CODES = [
        'KARTON',
        'PACK',
        'BOX',
        'SACHET',
    ];

    /**
     * Hapus satuan lama (hard delete), lalu seed Karton, Pack, Box, Sachet.
     */
    public function run(): void
    {
        $companyId = BusinessUnit::query()
            ->where('type_code', 'COMPANY')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->value('id');

        foreach ($this->units() as $unit) {
            ProductUnit::withTrashed()->updateOrCreate(
                ['code' => $unit['code']],
                [
                    'company_id' => $companyId,
                    'branch_id' => null,
                    'name' => $unit['name'],
                    'symbol' => $unit['symbol'],
                    'description' => $unit['description'] ?? null,
                    'deleted_at' => null,
                ]
            );
        }

        $fallbackUnitId = ProductUnit::query()->where('code', 'BOX')->value('id');
        $obsoleteIds = ProductUnit::withTrashed()
            ->whereNotIn('code', self::ALLOWED_CODES)
            ->pluck('id');

        if ($obsoleteIds->isNotEmpty() && $fallbackUnitId) {
            $deleted = $this->purgeObsoleteUnits($obsoleteIds, $fallbackUnitId);
            $this->command?->info("Satuan lama dihapus permanen: {$deleted} record.");
        }

        $this->command?->info('Satuan aktif: Karton, Pack, Box, Sachet.');
    }

    private function units(): array
    {
        return [
            ['code' => 'KARTON', 'name' => 'Karton', 'symbol' => 'krt'],
            ['code' => 'PACK', 'name' => 'Pack', 'symbol' => 'pack'],
            ['code' => 'BOX', 'name' => 'Box', 'symbol' => 'box'],
            ['code' => 'SACHET', 'name' => 'Sachet', 'symbol' => 'sct'],
        ];
    }

    private function purgeObsoleteUnits(Collection $obsoleteIds, string $fallbackUnitId): int
    {
        $ids = $obsoleteIds->all();

        DB::table('product.products')
            ->whereIn('default_unit_id', $ids)
            ->update(['default_unit_id' => $fallbackUnitId]);

        $this->deleteWhereUnitIn('product.purchase_order_receive_items', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.purchase_order_items', 'unit_id', $ids);
        $this->deleteWhereUnitIn('transaction.sales_order_items', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.sales_order_items', 'unit_id', $ids);
        $this->deleteWhereUnitIn('manufacturing.production_order_outputs', 'unit_id', $ids);
        $this->deleteWhereUnitIn('manufacturing.production_order_materials', 'unit_id', $ids);
        $this->deleteWhereUnitIn('manufacturing.bom_items', 'unit_id', $ids);
        $this->deleteWhereUnitIn('distribution.replenishment_order_items', 'unit_id', $ids);
        $this->deleteWhereUnitIn('distribution.shipment_items', 'unit_id', $ids);
        $this->deleteWhereUnitIn('distribution.receipt_items', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.product_batch_stock', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.product_batches', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.product_cost_layers', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.product_cost_history', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.product_stock_movements', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.product_variant_stock', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.product_stock', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.product_variant_prices', 'unit_id', $ids);
        $this->deleteWhereUnitIn('product.product_prices', 'unit_id', $ids);

        DB::table('product.product_label_serials')
            ->whereIn('unit_id', $ids)
            ->update(['unit_id' => null]);

        $this->updateWhereUnitIn('manufacturing.bill_of_materials', 'output_unit_id', $ids, $fallbackUnitId);
        $this->updateWhereUnitIn('manufacturing.production_orders', 'output_unit_id', $ids, $fallbackUnitId);

        DB::table('product.product_unit_conversions')
            ->where(function ($query) use ($ids) {
                $query->whereIn('from_unit_id', $ids)
                    ->orWhereIn('to_unit_id', $ids);
            })
            ->delete();

        return ProductUnit::withTrashed()
            ->whereIn('id', $ids)
            ->forceDelete();
    }

    /**
     * @param  list<string>  $unitIds
     */
    private function deleteWhereUnitIn(string $table, string $column, array $unitIds): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $unitIds)->delete();
    }

    /**
     * @param  list<string>  $unitIds
     */
    private function updateWhereUnitIn(string $table, string $column, array $unitIds, string $fallbackUnitId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->whereIn($column, $unitIds)
            ->update([$column => $fallbackUnitId]);
    }
}
