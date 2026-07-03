<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductUnitConversion;
use App\Models\ProductVariant;
use App\Services\Manufacturing\BomRecipeCalculatorService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BomRecipeCalculatorServiceTest extends TestCase
{
    public function test_suggest_one_carton_fg_needs_matching_sachets_in_rm_box_unit(): void
    {
        $fg = $this->makeVariant($this->makeChainProduct(4));
        $rm = $this->makeVariant($this->makeChainProduct(3));

        $result = BomRecipeCalculatorService::suggest(
            $fg,
            1,
            'unit-krt',
            $rm,
            'unit-box'
        );

        $this->assertNotNull($result);
        $this->assertEquals(1200, $result['output_smallest_qty']);
        $this->assertEquals(400, $result['suggested_qty']);
        $this->assertEquals('box', $result['component_unit']);
    }

    public function test_suggest_in_sachet_unit_for_rm(): void
    {
        $fg = $this->makeVariant($this->makeChainProduct(4));
        $rm = $this->makeVariant($this->makeChainProduct(3));

        $result = BomRecipeCalculatorService::suggest(
            $fg,
            1,
            'unit-krt',
            $rm,
            'unit-sct'
        );

        $this->assertNotNull($result);
        $this->assertEquals(1200, $result['suggested_qty']);
    }

    protected function makeVariant(Product $product): ProductVariant
    {
        $variant = new ProductVariant(['name' => 'Test']);
        $variant->setRelation('product', $product);

        return $variant;
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
