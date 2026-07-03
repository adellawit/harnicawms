<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductUnitConversion;
use App\Services\UnitConversionService;
use Tests\TestCase;

class StockAvailabilityServiceTest extends TestCase
{
    public function test_multi_hop_unit_conversion_krt_to_box(): void
    {
        $product = new Product;
        $krt = 'unit-krt';
        $pck = 'unit-pck';
        $box = 'unit-box';

        $product->setRelation('unitConversions', collect([
            new ProductUnitConversion(['from_unit_id' => $krt, 'to_unit_id' => $pck, 'conversion_factor' => 30]),
            new ProductUnitConversion(['from_unit_id' => $pck, 'to_unit_id' => $box, 'conversion_factor' => 10]),
        ]));

        $this->assertSame(300.0, $product->convertQuantity(1, $krt, $box));
        $this->assertSame(20.1, $product->convertQuantity(201, $box, $pck));
        $this->assertEqualsWithDelta(0.67, $product->convertQuantity(201, $box, $krt), 0.01);
    }

    public function test_unit_conversion_service_delegates_to_product(): void
    {
        $product = new Product;
        $product->setRelation('unitConversions', collect([
            new ProductUnitConversion(['from_unit_id' => 'a', 'to_unit_id' => 'b', 'conversion_factor' => 2]),
        ]));

        $this->assertSame(10.0, UnitConversionService::convertQuantity($product, 5, 'a', 'b'));
    }
}
