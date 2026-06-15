<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\UnitConversionService;
use PHPUnit\Framework\TestCase;

class UnitConversionServiceTest extends TestCase
{
    public function test_convert_unit_cost_from_box_to_sachet(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('convertQuantity')
            ->willReturnCallback(function (float $qty, string $from, string $to) {
                if ($from === 'box-id' && $to === 'sct-id') {
                    return round($qty * 3, 6);
                }
                if ($from === 'sct-id' && $to === 'box-id') {
                    return round($qty / 3, 6);
                }

                return $qty;
            });

        $costPerSct = UnitConversionService::convertUnitCost($product, 100000, 'box-id', 'sct-id');

        $this->assertEqualsWithDelta(33333.3333, $costPerSct, 0.0001);
    }
}
