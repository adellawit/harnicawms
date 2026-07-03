<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductUnitConversion;
use App\Services\Manufacturing\ProductionSimulationService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductionSimulationServiceTest extends TestCase
{
    public function test_decompose_factor_map(): void
    {
        $product = $this->makeChainProduct();
        $levels = ProductionSimulationService::buildUnitChain($product)->values()->all();
        $unitIds = array_map(fn (array $row) => $row['unit']->id, $levels);
        $factors = [];

        foreach ($unitIds as $index => $unitId) {
            $factor = 1.0;
            for ($j = $index; $j < count($unitIds) - 1; $j++) {
                $factor *= (float) ($levels[$j]['factor_to_next'] ?? 1);
            }
            $factors[$unitId] = $factor;
        }

        $this->assertSame(['unit-krt', 'unit-pack', 'unit-box', 'unit-sct'], $unitIds);
        $this->assertEquals(1200, $factors[$unitIds[0]]);
    }

    public function test_build_unit_chain_has_four_levels(): void
    {
        $product = $this->makeChainProduct();
        $chain = ProductionSimulationService::buildUnitChain($product);

        $this->assertCount(4, $chain);
        $this->assertEquals(30, $chain->values()->get(0)['factor_to_next']);
        $this->assertEquals(10, $chain->values()->get(1)['factor_to_next']);
    }

    public function test_decompose_smallest_quantity_matches_repack_example(): void
    {
        $product = $this->makeChainProduct();

        $breakdown = ProductionSimulationService::decomposeSmallestQuantity($product, 22500);

        $this->assertSame([
            ['label' => 'krt', 'qty' => 18.0],
            ['label' => 'pack', 'qty' => 22.0],
            ['label' => 'box', 'qty' => 5.0],
        ], collect($breakdown)->map(fn (array $row) => [
            'label' => $row['label'],
            'qty' => $row['qty'],
        ])->all());
    }

    public function test_decompose_smallest_quantity_for_even_carton_count(): void
    {
        $product = $this->makeChainProduct();

        $breakdown = ProductionSimulationService::decomposeSmallestQuantity($product, 7200);

        $this->assertSame([
            ['label' => 'krt', 'qty' => 6.0],
        ], collect($breakdown)->map(fn (array $row) => [
            'label' => $row['label'],
            'qty' => $row['qty'],
        ])->all());
    }

    protected function makeChainProduct(): Product
    {
        $krt = tap(new ProductUnit(['name' => 'Karton', 'symbol' => 'krt']), fn ($u) => $u->id = 'unit-krt');
        $pack = tap(new ProductUnit(['name' => 'Pack', 'symbol' => 'pack']), fn ($u) => $u->id = 'unit-pack');
        $box = tap(new ProductUnit(['name' => 'Box', 'symbol' => 'box']), fn ($u) => $u->id = 'unit-box');
        $sct = tap(new ProductUnit(['name' => 'Sachet', 'symbol' => 'sct']), fn ($u) => $u->id = 'unit-sct');

        $convs = new Collection([
            new ProductUnitConversion([
                'from_unit_id' => 'unit-krt',
                'to_unit_id' => 'unit-pack',
                'conversion_factor' => 30,
                'conversion_level' => 1,
            ]),
            new ProductUnitConversion([
                'from_unit_id' => 'unit-pack',
                'to_unit_id' => 'unit-box',
                'conversion_factor' => 10,
                'conversion_level' => 2,
            ]),
            new ProductUnitConversion([
                'from_unit_id' => 'unit-box',
                'to_unit_id' => 'unit-sct',
                'conversion_factor' => 4,
                'conversion_level' => 3,
            ]),
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
