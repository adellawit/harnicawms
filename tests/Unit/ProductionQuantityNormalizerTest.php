<?php

namespace Tests\Unit;

use App\Models\BillOfMaterial;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductUnitConversion;
use App\Support\ProductionQuantityNormalizer;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductionQuantityNormalizerTest extends TestCase
{
    public function test_material_scale_when_planned_unit_matches_bom_output(): void
    {
        $product = $this->makeChainProduct(4);
        $bom = $this->makeBom($product, 'unit-krt', 1);

        $scale = ProductionQuantityNormalizer::materialScale($bom, 2, 'unit-krt');

        $this->assertEquals(2.0, $scale);
    }

    public function test_to_bom_output_unit_converts_box_to_carton(): void
    {
        $product = $this->makeChainProduct(4);
        $bom = $this->makeBom($product, 'unit-krt', 1);

        $qty = ProductionQuantityNormalizer::toBomOutputUnit($bom, 300, 'unit-box');

        $this->assertEquals(1.0, $qty);
    }

    public function test_material_scale_for_partial_carton_in_box_unit(): void
    {
        $product = $this->makeChainProduct(4);
        $bom = $this->makeBom($product, 'unit-krt', 1);

        $scale = ProductionQuantityNormalizer::materialScale($bom, 150, 'unit-box');

        $this->assertEquals(0.5, $scale);
    }

    protected function makeBom(Product $product, string $outputUnitId, float $outputQty): BillOfMaterial
    {
        $bom = new BillOfMaterial([
            'output_unit_id' => $outputUnitId,
            'output_quantity' => $outputQty,
        ]);
        $bom->setRelation('product', $product);
        $bom->setRelation('outputUnit', $product->defaultUnit);

        return $bom;
    }

    protected function makeChainProduct(int $sctPerBox = 4): Product
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
