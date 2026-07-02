<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductNature;
use App\Models\ProductPurchaseOrder;
use App\Models\ProductPurchaseOrderItem;
use App\Models\ProductPurchaseOrderReceiveItem;
use App\Support\PurchaseOrderReceiveWarehouse;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class PurchaseOrderReceiveWarehouseTest extends TestCase
{
    public function test_detects_receivable_raw_material_items(): void
    {
        $purchase = $this->purchaseWithNatureCodes(['RAW_MATERIAL']);

        $this->assertTrue(PurchaseOrderReceiveWarehouse::hasReceivableRawMaterial($purchase));
    }

    public function test_ignores_fully_received_raw_material_items(): void
    {
        $purchase = $this->purchaseWithNatureCodes(['RAW_MATERIAL'], remaining: 0);

        $this->assertFalse(PurchaseOrderReceiveWarehouse::hasReceivableRawMaterial($purchase));
    }

    public function test_does_not_treat_finished_goods_as_raw_material(): void
    {
        $purchase = $this->purchaseWithNatureCodes(['FINISHED_GOOD']);

        $this->assertFalse(PurchaseOrderReceiveWarehouse::hasReceivableRawMaterial($purchase));
    }

    public function test_defaults_to_wip_when_raw_material_is_receivable(): void
    {
        $purchase = $this->purchaseWithNatureCodes(['RAW_MATERIAL']);

        $warehouses = [
            ['id' => 'branch-wh', 'label' => 'Gudang Bandung'],
            ['id' => 'wip-wh', 'label' => 'Gudang WIP'],
        ];

        $this->assertSame(
            'wip-wh',
            PurchaseOrderReceiveWarehouse::defaultWarehouseId(
                $purchase,
                $warehouses,
                'company-1',
                'wip-wh'
            )
        );
    }

    public function test_does_not_default_to_wip_for_finished_goods(): void
    {
        $purchase = $this->purchaseWithNatureCodes(['FINISHED_GOOD']);
        $purchase->warehouse_id = 'branch-wh';

        $warehouses = [
            ['id' => 'branch-wh', 'label' => 'Gudang Bandung'],
            ['id' => 'wip-wh', 'label' => 'Gudang WIP'],
        ];

        $this->assertSame(
            'branch-wh',
            PurchaseOrderReceiveWarehouse::defaultWarehouseId(
                $purchase,
                $warehouses,
                'company-1',
                'wip-wh'
            )
        );
    }

    /**
     * @param  list<string>  $natureCodes
     */
    private function purchaseWithNatureCodes(array $natureCodes, float $remaining = 10): ProductPurchaseOrder
    {
        $items = collect($natureCodes)->map(function (string $code) use ($remaining) {
            $nature = new ProductNature(['code' => $code]);
            $product = new Product;
            $product->setRelation('nature', $nature);

            $item = new ProductPurchaseOrderItem([
                'quantity' => 10,
            ]);
            $item->setRelation('product', $product);
            $item->setRelation('receiveItems', new Collection);

            if ($remaining <= 0) {
                $receiveItem = new ProductPurchaseOrderReceiveItem([
                    'quantity_received' => 10,
                ]);
                $item->setRelation('receiveItems', new Collection([$receiveItem]));
            }

            return $item;
        });

        $purchase = new ProductPurchaseOrder;
        $purchase->setRelation('items', $items);

        return $purchase;
    }
}
