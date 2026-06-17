<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\StockAvailabilityService;
use App\Services\UnitConversionService;
use PHPUnit\Framework\TestCase;

class StockAvailabilityServiceTest extends TestCase
{
    public function test_available_quantity_converts_stock_unit_to_bom_unit(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('convertQuantity')
            ->willReturnCallback(function (float $qty, string $from, string $to) {
                if ($from === 'box-id' && $to === 'sct-id') {
                    return round($qty * 3, 6);
                }

                return $qty;
            });

        $factor = UnitConversionService::convertQuantity($product, 10.0, 'box-id', 'sct-id');

        $this->assertEqualsWithDelta(30.0, $factor, 0.0001);
    }
}
