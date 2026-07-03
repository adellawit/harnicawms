<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Support\PurchaseOrderCartonDisplay;
use Tests\TestCase;

class PurchaseOrderCartonDisplayTest extends TestCase
{
    public function test_karton_breakdown_and_pack_to_box(): void
    {
        $product = new Product(['default_unit_id' => 'unit-krt']);
        $product->setRelation('defaultUnit', (object) ['id' => 'unit-krt', 'name' => 'Karton', 'code' => 'KARTON']);
        $product->setRelation('unitConversions', collect([
            new ProductUnitConversion(['from_unit_id' => 'unit-krt', 'to_unit_id' => 'unit-pck', 'conversion_factor' => 30, 'conversion_level' => 1]),
            new ProductUnitConversion(['from_unit_id' => 'unit-pck', 'to_unit_id' => 'unit-box', 'conversion_factor' => 10, 'conversion_level' => 2]),
        ]));

        $units = collect([
            (object) ['id' => 'unit-krt', 'name' => 'Karton', 'code' => 'KARTON'],
            (object) ['id' => 'unit-pck', 'name' => 'Pack', 'code' => 'PACK'],
            (object) ['id' => 'unit-box', 'name' => 'Box', 'code' => 'BOX'],
        ])->keyBy('id');

        $this->assertSame('30 Pack 300 Box', PurchaseOrderCartonDisplay::format($product, 1, 'unit-krt', $units));
        $this->assertSame('300 Box', PurchaseOrderCartonDisplay::format($product, 30, 'unit-pck', $units));
        $this->assertSame('300 Box', PurchaseOrderCartonDisplay::format($product, 300, 'unit-box', $units));
        $this->assertSame(300.0, PurchaseOrderCartonDisplay::boxQuantity($product, 1, 'unit-krt'));
    }
}
