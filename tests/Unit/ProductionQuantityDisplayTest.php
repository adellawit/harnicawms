<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductUnitConversion;
use App\Support\ProductionQuantityDisplay;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductionQuantityDisplayTest extends TestCase
{
    public function test_largest_unit_hint_when_qty_in_sachet(): void
    {
        $product = $this->makeChainProduct(4);

        $hint = ProductionQuantityDisplay::largestUnitHint($product, 4800, 'unit-sct');

        $this->assertSame('≈ 4 krt', $hint);
    }

    public function test_largest_unit_hint_null_when_already_carton(): void
    {
        $product = $this->makeChainProduct(4);

        $this->assertNull(ProductionQuantityDisplay::largestUnitHint($product, 8, 'unit-krt'));
    }

    public function test_breakdown_hint_for_partial_quantities(): void
    {
        $product = $this->makeChainProduct(4);

        $hint = ProductionQuantityDisplay::breakdownHint($product, 22500, 'unit-sct');

        $this->assertSame('18 krt · 22 pack · 5 box', $hint);
    }

    protected function makeChainProduct(int $sctPerBox): Product
    {
        $krt = tap(new ProductUnit(['name' => 'Karton', 'symbol' => 'krt']), fn ($u) => $u->id = 'unit-krt');
        $pack = tap(new ProductUnit(['name' => 'Pack', 'symbol' => 'pack']), fn ($u) => $u->id = 'unit-pack');
        $box = tap(new ProductUnit(['name' => 'Box', 'symbol' => 'box']), fn ($u) => $u->id = 'unit-box');
        $sct = tap(new ProductUnit(['name' => 'Sachet', 'symbol' => 'sct']), fn ($u) => $u->id = 'unit-sct');

        $convs = new Collection([
            tap(new ProductUnitConversion([
                'from_unit_id' => 'unit-krt',
                'to_unit_id' => 'unit-pack',
                'conversion_factor' => 30,
                'conversion_level' => 1,
            ]), fn ($c) => $c->id = 'conv-1'),
            tap(new ProductUnitConversion([
                'from_unit_id' => 'unit-pack',
                'to_unit_id' => 'unit-box',
                'conversion_factor' => 10,
                'conversion_level' => 2,
            ]), fn ($c) => $c->id = 'conv-2'),
            tap(new ProductUnitConversion([
                'from_unit_id' => 'unit-box',
                'to_unit_id' => 'unit-sct',
                'conversion_factor' => $sctPerBox,
                'conversion_level' => 3,
            ]), fn ($c) => $c->id = 'conv-3'),
        ]);

        $product = new Product(['default_unit_id' => 'unit-krt']);
        $product->setRelation('defaultUnit', $krt);
        $convs[0]->setRelation('fromUnit', $krt);
        $convs[1]->setRelation('fromUnit', $pack);
        $convs[2]->setRelation('fromUnit', $box);
        $convs[0]->setRelation('toUnit', $pack);
        $convs[1]->setRelation('toUnit', $box);
        $convs[2]->setRelation('toUnit', $sct);
        $product->setRelation('unitConversions', $convs);

        return $product;
    }
}
