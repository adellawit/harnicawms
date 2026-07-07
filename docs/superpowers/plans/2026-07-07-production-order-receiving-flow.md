# Production Order Receiving Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split Production Order completion into a no-stock-movement status flow (`draft` → `in_progress` → `pending_receiving`) plus a final Receiving step that takes only an actual-produced-quantity input and computes/executes all stock movement, and redesign the create form to pick a finished-good product instead of a BOM record with hidden auto-resolved warehouses.

**Architecture:** Extend `ProductionOrder.status` with a new `pending_receiving` value and activate the already-declared-but-unused `in_progress` value. Replace `ProductionService::complete()` (which silently defaulted `produced_qty` to `planned_qty` and moved stock at creation time) with `ProductionService::receive()`, which requires an explicit actual quantity and is only reachable from the new Receiving page. Extract the BOM controller's warehouse-fallback logic into a shared `App\Support\ManufacturingWarehouseResolver` so both BOM and Production Order use identical, single-company-wide warehouse defaults (no per-ingredient multi-warehouse routing — that's out of scope, see spec).

**Tech Stack:** Laravel 12, PostgreSQL (multi-schema: `manufacturing`, `product`), Blade + Bootstrap 5, Select2 (existing `product-variant-select2` partial for AJAX product search).

## Global Constraints

- No automated test harness exists for this domain (no factories beyond `UserFactory`, `phpunit.xml` points at a real Postgres DB, not sqlite). Follow the project's established verification convention for this area (used throughout the prior BOM work in this same repo): verify each task via `php artisan tinker` and/or the browser preview, not PHPUnit. Every task's "write failing test" step is replaced with a "write a tinker verification script and run it" step, and "make it pass" is replaced with "re-run and confirm the output."
- All new/changed Eloquent models use `protected $connection = 'pgsql'` and fully-qualified schema table names (e.g. `manufacturing.production_order_materials`), matching every existing model in `app/Models/`.
- Money/qty fields use the same decimal casts as sibling columns: quantities `decimal:6`, costs `decimal:4` (see `ProductionOrderMaterial::$casts`, `ProductionOrder::$casts`).
- Route permission middleware follows `permission:<Menu Name>,<is_read|is_create|is_update|is_delete>` exactly as used for `Bill of Materials` and `Production Order` (both menus have `has_create/has_update/has_read/has_delete = true` per `database/seeders/MenuSeeder.php:2119-2129`).
- Row-action dropdowns in index tables use the exact Bootstrap pattern already shipped for `/bom` (`resources/views/admin/bom/index.blade.php`): a `.dropdown` with a `btn-icon dropdown-toggle hide-arrow` "⋮" trigger, `dropdown-menu-end`, colored `dropdown-item` icons (`text-primary`/`text-warning`/`text-danger`), plus the `popperConfig` `strategy: 'fixed'` fix appended in a `@push('page-js')` block to avoid clipping inside `.table-responsive`.
- Never trust client-side calculated quantities for stock-affecting operations — the server always recalculates from scratch before touching `StockMutationService`.
- Out of scope (do not implement): per-ingredient multi-warehouse auto-routing, editing/re-running a `completed` Production Order.

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `database/migrations/manufacturing/2026_07_08_000001_add_expected_qty_to_production_order_materials.php` | Create | Adds `expected_qty` column |
| `app/Models/ProductionOrderMaterial.php` | Modify | Add `expected_qty` to fillable/casts |
| `app/Support/ManufacturingWarehouseResolver.php` | Create | Shared source/output warehouse fallback resolution used by both BOM and Production Order |
| `app/Http/Controllers/Admin/BomController.php` | Modify | Replace private `resolveWipContext()` with the shared resolver (behavior-preserving refactor) |
| `app/Services/Manufacturing/ProductionService.php` | Modify | Replace `complete()` with `receive($order, $actualQty, $userId)`; persist `expected_qty` |
| `app/Http/Controllers/Admin/ProductionOrderController.php` | Modify | Product-based create/store, status-transition actions (`start`, `finish`), receiving actions, edit/update/destroy |
| `routes/distribution.php` | Modify | Production route group: add `edit`, `update`, `destroy`, `start`, `finish`, `receive` (GET+POST); remove `complete` |
| `resources/views/admin/production/create.blade.php` | Modify | Remove warehouse selects, product-variant-select2 instead of BOM select, auto material preview |
| `resources/views/admin/production/edit.blade.php` | Create | Same as create but locked product, only for `draft` orders |
| `resources/views/admin/production/show.blade.php` | Modify | Status-specific action buttons; Rencana/Aktual/Sisa materials columns |
| `resources/views/admin/production/receive.blade.php` | Create | Receiving form: actual qty input + live preview table |
| `resources/views/admin/production/index.blade.php` | Modify | Grand Total column; dropdown actions for `draft` rows, single "Lihat" button otherwise |

---

### Task 1: `expected_qty` column on production_order_materials

**Files:**
- Create: `database/migrations/manufacturing/2026_07_08_000001_add_expected_qty_to_production_order_materials.php`
- Modify: `app/Models/ProductionOrderMaterial.php`

**Interfaces:**
- Produces: `ProductionOrderMaterial::$fillable` includes `expected_qty`; `$casts['expected_qty'] = 'decimal:6'`. Later tasks (`ProductionService::receive()`, `show.blade.php`) read/write this column.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manufacturing.production_order_materials', function (Blueprint $table) {
            $table->decimal('expected_qty', 18, 6)->nullable()->after('qty_consumed');
        });
    }

    public function down(): void
    {
        Schema::table('manufacturing.production_order_materials', function (Blueprint $table) {
            $table->dropColumn('expected_qty');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan migrate --path=database/migrations/manufacturing/2026_07_08_000001_add_expected_qty_to_production_order_materials.php --force`
Expected: `Migrating: 2026_07_08_000001_add_expected_qty_to_production_order_materials` then `Migrated:` with no errors.

- [ ] **Step 3: Update the model**

In `app/Models/ProductionOrderMaterial.php`, change:

```php
    protected $fillable = [
        'production_order_id',
        'component_product_id',
        'component_variant_id',
        'unit_id',
        'qty_consumed',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'qty_consumed' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];
```

to:

```php
    protected $fillable = [
        'production_order_id',
        'component_product_id',
        'component_variant_id',
        'unit_id',
        'qty_consumed',
        'expected_qty',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'qty_consumed' => 'decimal:6',
        'expected_qty' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];
```

- [ ] **Step 4: Verify via tinker**

Run:
```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan tinker --execute="echo Schema::hasColumn('manufacturing.production_order_materials', 'expected_qty') ? 'OK' : 'MISSING';"
```
Expected output: `OK`

- [ ] **Step 5: Commit**

```bash
git add database/migrations/manufacturing/2026_07_08_000001_add_expected_qty_to_production_order_materials.php app/Models/ProductionOrderMaterial.php
git commit -m "feat(production): add expected_qty column for material variance tracking"
```

---

### Task 2: Shared warehouse fallback resolver

**Files:**
- Create: `app/Support/ManufacturingWarehouseResolver.php`
- Modify: `app/Http/Controllers/Admin/BomController.php:1-16,230-249`

**Interfaces:**
- Consumes: `App\Models\Warehouse::inventoryActive()` query scope (already used by `BomController::resolveWipContext()`).
- Produces:
  - `ManufacturingWarehouseResolver::resolveMaterialWarehouse(?string $companyId): array` returns `[?string $warehouseId, string $branchId]`, prioritizing `WIP` → `RAW_MATERIAL` → `FG` → any company warehouse (same logic as the current `BomController::resolveWipContext()`).
  - `ManufacturingWarehouseResolver::resolveOutputWarehouse(?string $companyId): array` returns `[?string $warehouseId, string $branchId]`, prioritizing `FG` → `WIP` → any company warehouse.
  - Task 3 (`ProductionOrderController::create()`/`store()`) calls both methods instead of requiring `source_warehouse_id`/`output_warehouse_id` form input.

- [ ] **Step 1: Write the resolver class**

```php
<?php

namespace App\Support;

use App\Models\Warehouse;

/**
 * Gudang acuan tunggal (company-wide) untuk operasi manufaktur ketika user
 * tidak memilih gudang secara eksplisit. Prioritas berjenjang supaya tetap
 * berfungsi walau gudang bertipe tertentu belum ada di seed data.
 *
 * Auto-routing per-bahan berdasarkan lokasi stok sebenarnya (multi-gudang)
 * adalah task terpisah di masa depan — resolver ini sengaja hanya
 * mengembalikan SATU gudang per company per peran (bahan baku / produk jadi).
 */
class ManufacturingWarehouseResolver
{
    /**
     * @return array{0: ?string, 1: string} [warehouse_id, branch_id]
     */
    public static function resolveMaterialWarehouse(?string $companyId): array
    {
        return self::resolve($companyId, ['WIP', 'RAW_MATERIAL', 'FG']);
    }

    /**
     * @return array{0: ?string, 1: string} [warehouse_id, branch_id]
     */
    public static function resolveOutputWarehouse(?string $companyId): array
    {
        return self::resolve($companyId, ['FG', 'WIP', 'RAW_MATERIAL']);
    }

    /**
     * @param  list<string>  $typePriority
     * @return array{0: ?string, 1: string}
     */
    protected static function resolve(?string $companyId, array $typePriority): array
    {
        $cases = collect($typePriority)
            ->map(fn ($code, $i) => "WHEN '{$code}' THEN {$i}")
            ->implode(' ');
        $fallbackIndex = count($typePriority);

        $warehouse = Warehouse::inventoryActive()
            ->where('company_id', $companyId)
            ->orderByRaw("CASE warehouse_type_code {$cases} ELSE {$fallbackIndex} END")
            ->orderByDesc('is_default')
            ->first();

        $warehouseId = optional($warehouse)->id;
        $branchId = optional($warehouse)->branch_id ?? $warehouseId ?? $companyId;

        return [$warehouseId, $branchId];
    }
}
```

- [ ] **Step 2: Refactor BomController to use it**

In `app/Http/Controllers/Admin/BomController.php`, add the import near the top:

```php
use App\Models\Warehouse;
```
→ replace with (add alongside existing imports):
```php
use App\Support\ManufacturingWarehouseResolver;
```

Then replace the entire `resolveWipContext()` method:

```php
    /**
     * Gudang acuan untuk lookup HPP bahan baku. WIP diprioritaskan (sesuai desain awal),
     * tapi HPP bahan baku sebenarnya tercatat di gudang RAW_MATERIAL, jadi fallback berjenjang
     * dipakai supaya HPP tetap tampil walau gudang WIP belum ada (mis. di environment lokal/baru).
     * company_id != branch_id di skema ini, jadi branch_id HARUS diambil dari gudang yang nyata ada,
     * tidak boleh asal pakai company_id sebagai pengganti.
     */
    private function resolveWipContext(?string $companyId): array
    {
        $warehouse = Warehouse::inventoryActive()
            ->where('company_id', $companyId)
            ->orderByRaw("CASE warehouse_type_code WHEN 'WIP' THEN 0 WHEN 'RAW_MATERIAL' THEN 1 WHEN 'FG' THEN 2 ELSE 3 END")
            ->orderByDesc('is_default')
            ->first();

        $wipId = optional($warehouse)->id;
        $wipBranchId = optional($warehouse)->branch_id ?? $wipId ?? $companyId;

        return [$wipId, $wipBranchId];
    }
```

with:

```php
    /**
     * Gudang acuan untuk lookup HPP bahan baku — delegasi ke resolver bersama
     * yang juga dipakai Production Order (lihat App\Support\ManufacturingWarehouseResolver).
     */
    private function resolveWipContext(?string $companyId): array
    {
        return ManufacturingWarehouseResolver::resolveMaterialWarehouse($companyId);
    }
```

Since `Warehouse` is no longer referenced directly in this file (only via the resolver), remove the now-unused `use App\Models\Warehouse;` import — first confirm it's unused elsewhere in the file:

```bash
grep -n "Warehouse::" app/Http/Controllers/Admin/BomController.php
```
Expected: no matches. Then remove the `use App\Models\Warehouse;` line and add `use App\Support\ManufacturingWarehouseResolver;` in its place (keep imports alphabetically grouped as they already are).

- [ ] **Step 3: Verify BOM behavior is unchanged (regression check)**

Run:
```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan tinker --execute="
\$companyId = optional(App\Support\WmsContext::distributor())->id;
[\$a, \$b] = App\Support\ManufacturingWarehouseResolver::resolveMaterialWarehouse(\$companyId);
echo 'material_warehouse=' . (\$a ?? 'null') . ' branch=' . \$b . PHP_EOL;
[\$c, \$d] = App\Support\ManufacturingWarehouseResolver::resolveOutputWarehouse(\$companyId);
echo 'output_warehouse=' . (\$c ?? 'null') . ' branch=' . \$d . PHP_EOL;
"
```
Expected: both lines print non-empty UUIDs (or the same warehouse for both if only one type exists), no exceptions.

Then open `http://localhost:8010/bom` in the browser preview, click into any existing BOM's "Lihat", and confirm HPP values still display (not `Rp 0` / not blank) — same as before this refactor.

- [ ] **Step 4: Commit**

```bash
git add app/Support/ManufacturingWarehouseResolver.php app/Http/Controllers/Admin/BomController.php
git commit -m "refactor(manufacturing): extract shared warehouse fallback resolver"
```

---

### Task 3: `ProductionService::receive()` (replaces `complete()`)

**Files:**
- Modify: `app/Services/Manufacturing/ProductionService.php:1-150`

**Interfaces:**
- Consumes: `StockAvailabilityService::assertSufficient()`, `StockMutationService::outbound()`/`inbound()` (unchanged signatures), `ManufacturingWarehouseResolver` (from Task 2, used by the controller — not this service).
- Produces: `ProductionService::receive(ProductionOrder $order, float $actualQty, ?string $userId = null): ProductionOrder`. Task 6 (`ProductionOrderController::receive()`) calls this. Throws `\RuntimeException` if `$actualQty <= 0`, if `$order->status !== 'pending_receiving'`, or if a material's stock is insufficient (bubbled from `StockAvailabilityService::assertSufficient`).
- Each persisted `ProductionOrderMaterial` row now has both `expected_qty` (BOM qty × the *planned* scale, i.e. what would have been consumed had actual output matched the plan) and `qty_consumed` (BOM qty × the *actual* scale).

- [ ] **Step 1: Replace `complete()` with `receive()`**

Replace the entire `complete()` method (currently lines 23-150) with:

```php
    public static function receive(ProductionOrder $order, float $actualQty, ?string $userId = null): ProductionOrder
    {
        if ($order->status !== 'pending_receiving') {
            throw new \RuntimeException('Production order harus berstatus "Menunggu Receiving" sebelum bisa diterima.');
        }

        if ($actualQty <= 0) {
            throw new \RuntimeException('Qty aktual produksi harus lebih dari 0.');
        }

        return DB::transaction(function () use ($order, $actualQty, $userId) {
            $bom = $order->bom()->with(['items.componentVariant.product', 'items.componentProduct'])->first();
            $plannedQty = (float) $order->planned_qty;

            $outputPerBatch = $bom ? (float) ($bom->output_quantity ?: 1) : 1;
            $plannedScale = $outputPerBatch > 0 ? $plannedQty / $outputPerBatch : $plannedQty;
            $actualScale = $outputPerBatch > 0 ? $actualQty / $outputPerBatch : $actualQty;

            $totalMaterialCost = 0.0;

            $order->materials()->delete();
            $order->outputs()->delete();

            $branchId = $order->branch_id ?: $order->outputWarehouse?->branch_id ?: $order->sourceWarehouse?->branch_id;
            $materialWarehouseId = $order->source_warehouse_id;
            $outputWarehouseId = $order->output_warehouse_id ?: $order->branch_id;

            if ($bom) {
                foreach ($bom->items as $item) {
                    $qtyNeeded = (float) $item->quantity * $actualScale;
                    if ($qtyNeeded <= 0) {
                        continue;
                    }

                    $label = $item->componentVariant?->display_name
                        ?? $item->componentProduct?->name
                        ?? 'Bahan baku';

                    StockAvailabilityService::assertSufficient(
                        $item->component_variant_id,
                        $branchId ?: $materialWarehouseId,
                        $item->unit_id,
                        $qtyNeeded,
                        $label,
                        $materialWarehouseId
                    );
                }

                foreach ($bom->items as $item) {
                    $expectedQty = (float) $item->quantity * $plannedScale;
                    $qtyNeeded = (float) $item->quantity * $actualScale;
                    if ($qtyNeeded <= 0) {
                        continue;
                    }

                    $result = StockMutationService::outbound(
                        $item->component_product_id,
                        $item->component_variant_id,
                        $order->company_id,
                        $branchId ?: $materialWarehouseId,
                        $item->unit_id,
                        $qtyNeeded,
                        'ProductionConsume',
                        $order->id,
                        $userId,
                        'Konsumsi bahan baku produksi ' . $order->order_number,
                        $materialWarehouseId
                    );

                    $cogs = $result['total_cost'];
                    $unitCost = $qtyNeeded > 0 ? round($cogs / $qtyNeeded, 4) : 0.0;
                    $totalMaterialCost += $cogs;

                    ProductionOrderMaterial::create([
                        'production_order_id' => $order->id,
                        'component_product_id' => $item->component_product_id,
                        'component_variant_id' => $item->component_variant_id,
                        'unit_id' => $item->unit_id,
                        'qty_consumed' => $qtyNeeded,
                        'expected_qty' => $expectedQty,
                        'unit_cost' => $unitCost,
                        'total_cost' => round($cogs, 4),
                    ]);
                }
            }

            $overhead = (float) $order->overhead_cost;
            $totalCost = $totalMaterialCost + $overhead;
            $outputUnitCost = $actualQty > 0 ? round($totalCost / $actualQty, 4) : 0.0;

            StockMutationService::inbound(
                $order->product_id,
                $order->product_variant_id,
                $order->company_id,
                $branchId ?: $outputWarehouseId,
                $order->output_unit_id,
                $actualQty,
                $outputUnitCost,
                'ProductionOutput',
                $order->id,
                $userId,
                'Hasil produksi ' . $order->order_number,
                optional($order->production_date)->toDateString(),
                optional($order->output_expiry_date)->toDateString(),
                $outputWarehouseId
            );

            ProductionOrderOutput::create([
                'production_order_id' => $order->id,
                'product_id' => $order->product_id,
                'product_variant_id' => $order->product_variant_id,
                'unit_id' => $order->output_unit_id,
                'qty_produced' => $actualQty,
                'unit_cost' => $outputUnitCost,
                'total_cost' => round($totalCost, 4),
            ]);

            $order->update([
                'produced_qty' => $actualQty,
                'total_material_cost' => round($totalMaterialCost, 4),
                'output_unit_cost' => $outputUnitCost,
                'status' => 'completed',
                'updated_by' => $userId,
            ]);

            return $order->fresh(['materials', 'outputs']);
        });
    }
```

Note: `generateNumber()` (the remaining method in the file) is untouched.

- [ ] **Step 2: Verify via tinker end-to-end**

This requires an existing `draft`-turned-`pending_receiving` order with a BOM that has at least one component with available stock. Adjust IDs to real data in your environment:

```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan tinker --execute="
\$order = App\Models\ProductionOrder::where('status', 'draft')->orWhere('status', 'pending_receiving')->first();
if (!\$order) { echo 'NO_ORDER_TO_TEST'; exit; }
\$order->status = 'pending_receiving';
\$order->save();
try {
    \$result = App\Services\Manufacturing\ProductionService::receive(\$order, (float) \$order->planned_qty);
    echo 'status=' . \$result->status . ' produced_qty=' . \$result->produced_qty . ' materials=' . \$result->materials->count() . PHP_EOL;
    \$result->materials->each(fn (\$m) => print_r(['expected' => (float) \$m->expected_qty, 'consumed' => (float) \$m->qty_consumed]));
} catch (\Throwable \$e) {
    echo 'ERROR: ' . \$e->getMessage();
}
"
```
Expected: `status=completed produced_qty=<value>` with at least one materials row printed, `expected` equal to `consumed` (since `$actualQty` equals `planned_qty` in this test, both scales are identical).

Also verify the guard rejects a non-`pending_receiving` order:
```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan tinker --execute="
\$order = App\Models\ProductionOrder::factory()->make(); // not persisted, just for the status check path
\$draft = App\Models\ProductionOrder::where('status', 'draft')->first();
if (!\$draft) { echo 'NO_DRAFT_TO_TEST'; exit; }
try {
    App\Services\Manufacturing\ProductionService::receive(\$draft, 1.0);
    echo 'UNEXPECTED_SUCCESS';
} catch (\RuntimeException \$e) {
    echo 'OK: ' . \$e->getMessage();
}
"
```
Expected: `OK: Production order harus berstatus "Menunggu Receiving" sebelum bisa diterima.`

- [ ] **Step 3: Commit**

```bash
git add app/Services/Manufacturing/ProductionService.php
git commit -m "feat(production): replace complete() with receive(), require explicit actual qty"
```

---

### Task 4: Product-based create form — backend

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionOrderController.php:1-119` (`create()`, `store()`, `bomPreview()`, `simulate()`)

**Interfaces:**
- Consumes: `ManufacturingWarehouseResolver::resolveMaterialWarehouse()`/`resolveOutputWarehouse()` (Task 2), existing `ProductionSimulationService`, `ProductionQuantityNormalizer`, `StockAvailabilityService::availableQuantity()`.
- Produces: `create()` now passes a `$products` collection (FINISHED_GOOD variants, for the fallback/empty-BOM warning) instead of `$boms`/`$warehouses`/`$wip`/`$fg`; `bomPreview()` and `simulate()` now accept `product_variant_id` instead of `bom_id` + `source_warehouse_id`. Task 5 (`create.blade.php`) consumes these new request/response shapes.

- [ ] **Step 1: Rewrite `create()`**

Replace:

```php
    public function create()
    {
        $boms = BillOfMaterial::with([
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'product.defaultUnit',
            'outputUnit',
            'variant',
            'items.componentVariant.product',
            'items.unit',
        ])
            ->where('is_active', true)
            ->whereHas('product')
            ->orderByDesc('created_at')
            ->get();

        $bomCatalog = $boms->map(function (BillOfMaterial $bom) {
            return [
                'id' => $bom->id,
                'output_unit_id' => $bom->output_unit_id,
                'output_quantity' => (float) $bom->output_quantity,
                'output_unit' => $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? '',
                'units' => ProductionSimulationService::unitOptions($bom->product),
            ];
        })->values();

        $distributor = WmsContext::distributor();
        $wip = WmsContext::wipWarehouse(optional($distributor)->id);
        $fg = WmsContext::finishedGoodsWarehouse(optional($distributor)->id);
        $warehouses = WmsContext::accessibleWarehouses();

        return view('admin.production.create', compact('boms', 'bomCatalog', 'distributor', 'wip', 'fg', 'warehouses'));
    }
```

with:

```php
    public function create()
    {
        return view('admin.production.create');
    }
```

(The BOM catalog is no longer preloaded wholesale — it's fetched on demand per selected product via `bomForProduct()` below, since the form now searches products via AJAX rather than listing every BOM up front.)

- [ ] **Step 2: Add `bomForProduct()` (new AJAX endpoint backing the product picker)**

Add this method directly after `create()`:

```php
    public function bomForProduct(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
        ]);

        $bom = BillOfMaterial::with([
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'product.defaultUnit',
            'outputUnit',
            'variant',
        ])
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('is_active', true)
            ->first();

        if (! $bom) {
            return response()->json(['message' => 'Produk ini belum punya resep (BOM).'], 404);
        }

        return response()->json([
            'bom_id' => $bom->id,
            'output_unit_id' => $bom->output_unit_id,
            'output_quantity' => (float) $bom->output_quantity,
            'output_unit' => $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? '',
            'units' => ProductionSimulationService::unitOptions($bom->product),
        ]);
    }
```

- [ ] **Step 3: Rewrite `store()`**

Replace:

```php
    public function store(Request $request)
    {
        $accessibleWarehouseIds = WmsContext::accessibleWarehouseIds();

        $data = $request->validate([
            'bom_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
            'source_warehouse_id' => ['required', 'uuid', Rule::in($accessibleWarehouseIds)],
            'output_warehouse_id' => ['required', 'uuid', Rule::in($accessibleWarehouseIds)],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'production_date' => ['nullable', 'date'],
            'output_expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'complete' => ['nullable'],
        ]);

        $bom = BillOfMaterial::with(['product.unitConversions', 'product.defaultUnit', 'outputUnit'])->findOrFail($data['bom_id']);
        $plannedUnitId = $data['planned_unit_id'] ?? $bom->output_unit_id;

        try {
            $plannedInOutputUnit = ProductionQuantityNormalizer::toBomOutputUnit(
                $bom,
                (float) $data['planned_qty'],
                $plannedUnitId
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['planned_qty' => $e->getMessage()]);
        }

        $distributor = WmsContext::distributor();
        $distId = optional($distributor)->id;
        $sourceWarehouse = Warehouse::findOrFail($data['source_warehouse_id']);
        $outputWarehouse = Warehouse::findOrFail($data['output_warehouse_id']);
        $userId = Auth::id();
        $branchId = $outputWarehouse->branch_id
            ?: $sourceWarehouse->branch_id
            ?: auth('web')->user()?->getBranchIdForTransaction();

        $order = ProductionOrder::create([
            'order_number' => ProductionService::generateNumber(),
            'production_date' => $data['production_date'] ?? now()->toDateString(),
            'output_expiry_date' => $data['output_expiry_date'] ?? null,
            'company_id' => $distId,
            'branch_id' => $branchId,
            'source_warehouse_id' => $sourceWarehouse->id,
            'output_warehouse_id' => $outputWarehouse->id,
            'bom_id' => $bom->id,
            'product_id' => $bom->product_id,
            'product_variant_id' => $bom->product_variant_id,
            'output_unit_id' => $bom->output_unit_id,
            'planned_qty' => $plannedInOutputUnit,
            'overhead_cost' => (float) ($data['overhead_cost'] ?? 0),
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if ($request->boolean('complete')) {
            try {
                ProductionService::complete($order, $userId);
            } catch (\Throwable $e) {
                return redirect()->route('production.show', $order->id)
                    ->with('error', 'Order dibuat tapi gagal diselesaikan: ' . $e->getMessage());
            }

            return redirect()->route('production.show', $order->id)
                ->with('success', 'Produksi selesai. HPP produk jadi telah dihitung dari bahan baku (FIFO).');
        }

        return redirect()->route('production.show', $order->id)->with('success', 'Production Order dibuat (draft).');
    }
```

with:

```php
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'production_date' => ['nullable', 'date'],
            'output_expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'mark_pending_receiving' => ['nullable'],
        ]);

        $bom = BillOfMaterial::with(['product.unitConversions', 'product.defaultUnit', 'outputUnit'])
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('is_active', true)
            ->first();

        if (! $bom) {
            return back()->withInput()->withErrors(['product_variant_id' => 'Produk ini belum punya resep (BOM). Buat resep dulu di menu Bill of Materials.']);
        }

        $plannedUnitId = $data['planned_unit_id'] ?? $bom->output_unit_id;

        try {
            $plannedInOutputUnit = ProductionQuantityNormalizer::toBomOutputUnit(
                $bom,
                (float) $data['planned_qty'],
                $plannedUnitId
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['planned_qty' => $e->getMessage()]);
        }

        $distributor = WmsContext::distributor();
        $distId = optional($distributor)->id;
        $userId = Auth::id();

        [$sourceWarehouseId, $materialBranchId] = ManufacturingWarehouseResolver::resolveMaterialWarehouse($distId);
        [$outputWarehouseId, $outputBranchId] = ManufacturingWarehouseResolver::resolveOutputWarehouse($distId);
        $branchId = $outputBranchId ?: $materialBranchId ?: auth('web')->user()?->getBranchIdForTransaction();

        $order = ProductionOrder::create([
            'order_number' => ProductionService::generateNumber(),
            'production_date' => $data['production_date'] ?? now()->toDateString(),
            'output_expiry_date' => $data['output_expiry_date'] ?? null,
            'company_id' => $distId,
            'branch_id' => $branchId,
            'source_warehouse_id' => $sourceWarehouseId,
            'output_warehouse_id' => $outputWarehouseId,
            'bom_id' => $bom->id,
            'product_id' => $bom->product_id,
            'product_variant_id' => $bom->product_variant_id,
            'output_unit_id' => $bom->output_unit_id,
            'planned_qty' => $plannedInOutputUnit,
            'overhead_cost' => (float) ($data['overhead_cost'] ?? 0),
            'status' => $request->boolean('mark_pending_receiving') ? 'pending_receiving' : 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return redirect()->route('production.show', $order->id)->with('success', 'Production Order dibuat.');
    }
```

- [ ] **Step 4: Update `bomPreview()` to key off `product_variant_id`**

Replace:

```php
    public function bomPreview(Request $request)
    {
        $data = $request->validate([
            'bom_id' => ['required', 'string'],
            'source_warehouse_id' => ['required', 'uuid'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
        ]);

        $bom = BillOfMaterial::with(['items.componentVariant.product', 'items.unit', 'product.unitConversions', 'product.defaultUnit', 'outputUnit'])
            ->findOrFail($data['bom_id']);

        $warehouse = InventoryWarehouseContext::assertAccessible($data['source_warehouse_id']);
        $branchId = $warehouse->branch_id ?: $warehouse->company_id ?: auth('web')->user()?->getBranchIdForTransaction();
        $plannedUnitId = $data['planned_unit_id'] ?? $bom->output_unit_id;
```

with:

```php
    public function bomPreview(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
        ]);

        $bom = BillOfMaterial::with(['items.componentVariant.product', 'items.unit', 'product.unitConversions', 'product.defaultUnit', 'outputUnit'])
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $distId = optional(WmsContext::distributor())->id;
        [$sourceWarehouseId, $branchId] = ManufacturingWarehouseResolver::resolveMaterialWarehouse($distId);
        $warehouse = $sourceWarehouseId ? Warehouse::find($sourceWarehouseId) : null;
        $plannedUnitId = $data['planned_unit_id'] ?? $bom->output_unit_id;
```

Then, further down in the same method, replace the two remaining uses of `$warehouse->id` (in the `$available` lookup and the JSON response) — they stay as `$warehouse->id` but `$warehouse` may now be `null` if no warehouse resolves at all, so guard the whole body: wrap the `$items = ...` block and the final `return response()->json(...)` with:

```php
        if (! $warehouse) {
            return response()->json(['message' => 'Tidak ada gudang aktif untuk company ini.'], 422);
        }
```

placed immediately after the `$plannedUnitId = ...` line and before the existing `try { $scale = ... }` block. The rest of `bomPreview()` (the `try/catch` for `materialScale`, the `$items` mapping, and the final `response()->json([...])`) stays exactly as-is.

- [ ] **Step 5: Update `simulate()` the same way**

Apply the identical transformation to `simulate()`: replace its validation of `bom_id` + `source_warehouse_id` with `product_variant_id`, resolve the BOM via `where('product_variant_id', ...)->where('is_active', true)->firstOrFail()`, resolve the warehouse via `ManufacturingWarehouseResolver::resolveMaterialWarehouse()`, and add the same `if (! $warehouse) { return response()->json(['message' => '...'], 422); }` guard before the existing simulation logic. Leave `ProductionSimulationService::simulate()` itself untouched — it already takes `$bom`, `$branchId`, `$warehouse->id`, etc. as plain arguments.

- [ ] **Step 6: Update imports**

At the top of the file, add:

```php
use App\Support\ManufacturingWarehouseResolver;
```

Remove `use Illuminate\Validation\Rule;` if `grep -n "Rule::" app/Http/Controllers/Admin/ProductionOrderController.php` shows no remaining matches after Step 3 (it was only used for the `source_warehouse_id`/`output_warehouse_id` validation rules that no longer exist).

- [ ] **Step 7: Verify with tinker + route list**

```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan route:list --name=production
```
Expected: still lists `production.index`, `production.create`, `production.store`, `production.show`, `production.bom-preview`, `production.simulate` (no `production.complete` route reference errors — that route/action is untouched until Task 6).

```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan tinker --execute="
\$bom = App\Models\BillOfMaterial::where('is_active', true)->first();
if (!\$bom) { echo 'NO_BOM'; exit; }
echo 'variant_id=' . \$bom->product_variant_id . PHP_EOL;
"
```
Note the printed `variant_id` — you'll use it manually in the browser once Task 5's view is wired up (this task only touches the controller, so there is no page to click through yet).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/ProductionOrderController.php
git commit -m "feat(production): resolve BOM/warehouses from selected product, drop manual warehouse pick"
```

---

### Task 5: Product-based create form — view

**Files:**
- Modify: `resources/views/admin/production/create.blade.php` (full rewrite)

**Interfaces:**
- Consumes: `production.bom-for-product` (new route, added in this task), `production.bom-preview`, `production.simulate`, `production.store` (all updated in Task 4/6), the `product-variant-select2` partial (`window.initProductVariantSelect2`).

- [ ] **Step 1: Add the `production.bom-for-product` route**

In `routes/distribution.php`, inside the `production` group, add this line right after the `production.bom-preview` route:

```php
        Route::get('/bom-for-product', [ProductionOrderController::class, 'bomForProduct'])->name('production.bom-for-product')->middleware('permission:Production Order,is_create');
```

- [ ] **Step 2: Update the `bomPreview`/`simulate`/`store` routes' expected params**

No route signature changes needed (`bomPreview`, `simulate`, `store` are still plain `GET`/`POST` with query/body params) — only the controller-side validation changed in Task 4. Skip to Step 3.

- [ ] **Step 3: Rewrite the view**

Replace the entire contents of `resources/views/admin/production/create.blade.php` with:

```blade
<x-app-layout>
    @section('title', 'Buat Produksi | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => 'Buat', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        @include('admin.partials.product-variant-select2')

        <form method="POST" action="{{ route('production.store') }}" id="productionForm">
            @csrf
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Perintah Produksi</h5></div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Produk Jadi <span class="text-danger">*</span></label>
                            <select name="product_variant_id" id="productSelect" class="form-select" required style="width:100%"></select>
                            <div id="noBomWarning" class="d-none mt-2">
                                <x-alert type="warning" class="mb-0" :dismissible="false">
                                    Produk ini belum punya resep (BOM). <a href="{{ route('bom.create') }}">Buat resep dulu</a>.
                                </x-alert>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Qty Produksi <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="planned_qty" id="plannedQty" class="form-control" value="{{ old('planned_qty', 1) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Satuan Produksi <span class="text-danger">*</span></label>
                            <input type="hidden" name="planned_unit_id" id="plannedUnitId" value="{{ old('planned_unit_id') }}">
                            <div id="plannedUnitOptions" class="d-flex flex-wrap gap-1">
                                <span class="text-muted small">Pilih produk...</span>
                            </div>
                            <small class="text-muted" id="plannedUnitHint"></small>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Overhead (Rp)</label>
                            <input type="number" step="any" min="0" name="overhead_cost" class="form-control" value="{{ old('overhead_cost', 0) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tgl Produksi</label>
                            <input type="date" name="production_date" class="form-control" value="{{ old('production_date', date('Y-m-d')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expired Produk Jadi <span class="text-muted small">(FEFO)</span></label>
                            <input type="date" name="output_expiry_date" class="form-control" value="{{ old('output_expiry_date') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="notes" class="form-control" placeholder="opsional" value="{{ old('notes') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4" id="bomPreview" style="display:none">
                <div class="card-header"><h5 class="card-title mb-0">Kebutuhan Bahan Baku</h5></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th>Bahan</th><th class="text-end">Stok Tersedia</th><th class="text-end">Qty Dibutuhkan</th><th class="text-center">Status</th></tr></thead>
                        <tbody id="bomRows"></tbody>
                    </table>
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="mark_pending_receiving" value="1" id="pendingReceivingChk">
                <label class="form-check-label" for="pendingReceivingChk">Tandai produksi langsung selesai (lewati draft, lanjut ke Receiving)</label>
            </div>
            <div id="bomStockWarn" class="d-none mb-3">
                <x-alert type="warning" class="mb-0" :dismissible="false">
                    Stok bahan baku tidak mencukupi. Terima barang atau kurangi qty produksi sebelum melanjutkan.
                </x-alert>
            </div>

            <button type="submit" class="btn btn-primary" id="btnSubmit" disabled><i class="ti ti-check me-1"></i> Proses Produksi</button>
            <a href="{{ route('production.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>

    @push('page-js')
    <script>
        const bomForProductUrl = @json(route('production.bom-for-product'));
        const previewUrl = @json(route('production.bom-preview'));
        let previewTimer = null;
        let plannedUnitId = document.getElementById('plannedUnitId')?.value || null;
        let currentBom = null;

        function setPlannedUnit(unitId) {
            plannedUnitId = unitId;
            const hidden = document.getElementById('plannedUnitId');
            if (hidden) hidden.value = unitId || '';
        }

        function renderPlannedUnitOptions(units, selectedId) {
            const container = document.getElementById('plannedUnitOptions');
            if (!container) return;

            if (!units || !units.length) {
                container.innerHTML = '<span class="text-muted small">Pilih produk...</span>';
                return;
            }

            const sel = selectedId || units[0]?.id;
            setPlannedUnit(sel);

            container.innerHTML = units.map(u => `
                <label class="btn btn-sm ${u.id === sel ? 'btn-primary' : 'btn-outline-primary'} planned-unit-btn">
                    <input type="radio" class="d-none" value="${u.id}" ${u.id === sel ? 'checked' : ''}>
                    ${u.label}
                </label>
            `).join('');

            container.querySelectorAll('.planned-unit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.querySelector('input').value;
                    container.querySelectorAll('.planned-unit-btn').forEach(b => {
                        b.classList.remove('btn-primary');
                        b.classList.add('btn-outline-primary');
                    });
                    this.classList.remove('btn-outline-primary');
                    this.classList.add('btn-primary');
                    setPlannedUnit(id);
                    renderBom();
                });
            });

            const hint = document.getElementById('plannedUnitHint');
            if (hint && currentBom) {
                hint.textContent = `Resep BOM: ${currentBom.output_quantity} ${currentBom.output_unit} per batch`;
            }
        }

        function setBomStockWarn(visible) {
            document.getElementById('bomStockWarn')?.classList.toggle('d-none', !visible);
        }

        function loadBomForProduct(variantId) {
            const noBomWarning = document.getElementById('noBomWarning');
            const box = document.getElementById('bomPreview');
            const submitBtn = document.getElementById('btnSubmit');

            noBomWarning.classList.add('d-none');
            box.style.display = 'none';
            submitBtn.disabled = true;
            currentBom = null;

            if (!variantId) {
                renderPlannedUnitOptions([], null);
                return;
            }

            fetch(bomForProductUrl + '?product_variant_id=' + encodeURIComponent(variantId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(async r => {
                if (r.status === 404) {
                    noBomWarning.classList.remove('d-none');
                    renderPlannedUnitOptions([], null);
                    return null;
                }
                return r.json();
            })
            .then(data => {
                if (!data) return;
                currentBom = data;
                renderPlannedUnitOptions(data.units, data.output_unit_id);
                renderBom();
            });
        }

        function renderBom() {
            const variantId = document.getElementById('productSelect').value;
            const qty = parseFloat(document.getElementById('plannedQty').value || '0');
            const box = document.getElementById('bomPreview');
            const rows = document.getElementById('bomRows');
            const submitBtn = document.getElementById('btnSubmit');

            if (!variantId || !currentBom || qty <= 0) {
                box.style.display = 'none';
                setBomStockWarn(false);
                submitBtn.disabled = true;
                return;
            }

            clearTimeout(previewTimer);
            previewTimer = setTimeout(function() {
                const params = new URLSearchParams({
                    product_variant_id: variantId,
                    planned_qty: qty,
                });
                if (plannedUnitId) {
                    params.set('planned_unit_id', plannedUnitId);
                }

                fetch(previewUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                .then(r => r.json())
                .then(data => {
                    rows.innerHTML = '';
                    let allOk = true;
                    (data.items || []).forEach(it => {
                        const need = it.qty;
                        const ok = it.available >= need;
                        if (!ok) allOk = false;
                        const unit = it.unit ? ` ${it.unit}` : '';
                        rows.innerHTML += `<tr class="${ok ? '' : 'table-danger'}">
                            <td>${it.label}</td>
                            <td class="text-end">${(+it.available.toFixed(4))}${unit}</td>
                            <td class="text-end">${(+need.toFixed(4))}${unit}</td>
                            <td class="text-center">${ok
                                ? '<span class="badge bg-label-success">Cukup</span>'
                                : '<span class="badge bg-label-danger">Kurang</span>'}</td>
                        </tr>`;
                    });
                    submitBtn.disabled = !allOk;
                    setBomStockWarn(!allOk);
                    box.style.display = '';
                })
                .catch(() => {
                    box.style.display = 'none';
                    setBomStockWarn(false);
                    submitBtn.disabled = true;
                });
            }, 250);
        }

        window.initProductVariantSelect2($('#productSelect'), {
            nature: 'FINISHED_GOOD',
            placeholder: 'Cari produk jadi...',
            onSelect: function (data) {
                loadBomForProduct(data.id);
            },
        });

        document.getElementById('productSelect')?.addEventListener('change', function() {
            if (!this.value) loadBomForProduct(null);
        });
        document.getElementById('plannedQty')?.addEventListener('change', renderBom);
        document.getElementById('plannedQty')?.addEventListener('input', renderBom);
    </script>
    @endpush
</x-app-layout>
```

- [ ] **Step 4: Verify in the browser preview**

Start/confirm the dev server (`preview_start` with the `laravel` config), then:
1. Navigate to `http://localhost:8010/production/create`.
2. Confirm there is no "Gudang Bahan Baku" / "Gudang Produk Jadi" field visible.
3. Type into "Produk Jadi" and confirm Select2 shows matching finished-good products (min 2 chars).
4. Select a product that has an active BOM (use the `variant_id` printed in Task 4 Step 7) — confirm "Kebutuhan Bahan Baku" table appears automatically with rows.
5. Select a finished-good product that has NO BOM — confirm the inline warning appears and "Proses Produksi" stays disabled.
6. Check console via `preview_console_logs` for JS errors — expect none.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/production/create.blade.php routes/distribution.php
git commit -m "feat(production): redesign create form around product search, drop BOM/warehouse pickers"
```

---

### Task 6: Status transitions (`start`, `finish`) + show page buttons

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionOrderController.php` (add `start()`, `finish()`; remove `complete()`)
- Modify: `routes/distribution.php` (production group)
- Modify: `resources/views/admin/production/show.blade.php`

**Interfaces:**
- Produces: `POST production.start` (`draft` → `in_progress`), `POST production.finish` (`in_progress` → `pending_receiving`). Both are plain status writes, no stock movement. Task 7 links to the actual Receiving page for the `pending_receiving` → `completed` transition.

- [ ] **Step 1: Remove `complete()`, add `start()`/`finish()`**

In `app/Http/Controllers/Admin/ProductionOrderController.php`, delete the existing `complete()` method entirely:

```php
    public function complete(string $id)
    {
        $order = ProductionOrder::findOrFail($id);
        try {
            ProductionService::complete($order, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menyelesaikan produksi: ' . $e->getMessage());
        }

        return redirect()->route('production.show', $order->id)
            ->with('success', 'Produksi selesai. HPP produk jadi telah dihitung (FIFO).');
    }
```

Replace it with:

```php
    public function start(string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'draft') {
            return back()->with('error', 'Hanya production order berstatus Draft yang bisa dimulai.');
        }

        $order->update(['status' => 'in_progress', 'updated_by' => Auth::id()]);

        return redirect()->route('production.show', $order->id)->with('success', 'Produksi dimulai.');
    }

    public function finish(string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'in_progress') {
            return back()->with('error', 'Hanya production order yang sedang dikerjakan yang bisa ditandai selesai.');
        }

        $order->update(['status' => 'pending_receiving', 'updated_by' => Auth::id()]);

        return redirect()->route('production.show', $order->id)->with('success', 'Produksi selesai di lantai. Lanjutkan ke Receiving untuk mencatat hasil aktual.');
    }
```

- [ ] **Step 2: Update routes**

In `routes/distribution.php`, replace:

```php
        Route::post('/{id}/complete', [ProductionOrderController::class, 'complete'])->name('production.complete')->middleware('permission:Production Order,is_update');
```

with:

```php
        Route::post('/{id}/start', [ProductionOrderController::class, 'start'])->name('production.start')->middleware('permission:Production Order,is_update');
        Route::post('/{id}/finish', [ProductionOrderController::class, 'finish'])->name('production.finish')->middleware('permission:Production Order,is_update');
```

- [ ] **Step 3: Update `show.blade.php` action buttons and status badge map**

Replace the status badge map (appears twice — once implicitly via the shared `$map` variable defined at line 41):

```php
                        @php $map = ['draft'=>'secondary','in_progress'=>'info','completed'=>'success','cancelled'=>'danger']; @endphp
```

with:

```php
                        @php $map = ['draft'=>'secondary','in_progress'=>'info','pending_receiving'=>'warning','completed'=>'success','cancelled'=>'danger']; @endphp
```

and change the label rendering right after it from `{{ ucfirst($order->status) }}` to a proper Indonesian label:

```php
                        @php $statusLabels = ['draft'=>'Draft','in_progress'=>'Sedang Dikerjakan','pending_receiving'=>'Menunggu Receiving','completed'=>'Selesai','cancelled'=>'Dibatalkan']; @endphp
                        <span class="badge bg-label-{{ $map[$order->status] ?? 'secondary' }}">{{ $statusLabels[$order->status] ?? ucfirst($order->status) }}</span>
```

(this replaces the existing `<span class="badge bg-label-{{ $map[$order->status] ?? 'secondary' }}">{{ ucfirst($order->status) }}</span>` line)

Then replace the single completion form block:

```php
                @if ($order->status !== 'completed')
                    <form method="POST" action="{{ route('production.complete', $order->id) }}" class="mt-3" onsubmit="return confirm('Selesaikan produksi? Bahan baku akan dikonsumsi (FIFO).')">
                        @csrf
                        <button class="btn btn-success"><i class="ti ti-check me-1"></i> Selesaikan Produksi</button>
                    </form>
                @endif
```

with:

```php
                @if ($order->status === 'draft')
                    <form method="POST" action="{{ route('production.start', $order->id) }}" class="mt-3">
                        @csrf
                        <button class="btn btn-primary"><i class="ti ti-player-play me-1"></i> Mulai Produksi</button>
                    </form>
                @elseif ($order->status === 'in_progress')
                    <form method="POST" action="{{ route('production.finish', $order->id) }}" class="mt-3">
                        @csrf
                        <button class="btn btn-success"><i class="ti ti-check me-1"></i> Selesaikan Produksi</button>
                    </form>
                @elseif ($order->status === 'pending_receiving')
                    <a href="{{ route('production.receive', $order->id) }}" class="btn btn-warning mt-3"><i class="ti ti-package-import me-1"></i> Terima Hasil Produksi</a>
                @endif
```

- [ ] **Step 4: Verify**

```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan route:list --name=production
```
Expected: `production.start` and `production.finish` present, `production.complete` gone, `production.receive` NOT yet present (added in Task 7 — this step will show a Blade error on the show page until Task 7 adds the route; that's expected and resolved by the next task).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ProductionOrderController.php routes/distribution.php resources/views/admin/production/show.blade.php
git commit -m "feat(production): add draft->in_progress->pending_receiving status transitions"
```

---

### Task 7: Receiving page

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionOrderController.php` (add `receiveView()`, `receive()`)
- Modify: `routes/distribution.php`
- Create: `resources/views/admin/production/receive.blade.php`
- Modify: `resources/views/admin/production/show.blade.php` (materials table columns)

**Interfaces:**
- Consumes: `ProductionService::receive()` (Task 3).
- Produces: `GET production.receive`, `POST production.receive.store`.

- [ ] **Step 1: Add controller actions**

Add to `app/Http/Controllers/Admin/ProductionOrderController.php`, after `finish()`:

```php
    public function receiveView(string $id)
    {
        $order = ProductionOrder::with([
            'product.defaultUnit',
            'variant',
            'outputUnit',
            'bom.items.componentVariant.product',
            'bom.items.unit',
        ])->findOrFail($id);

        if ($order->status !== 'pending_receiving') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Production order ini belum siap untuk diterima.');
        }

        return view('admin.production.receive', compact('order'));
    }

    public function receive(Request $request, string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        $data = $request->validate([
            'actual_qty' => ['required', 'numeric', 'min:0.000001'],
        ]);

        try {
            ProductionService::receive($order, (float) $data['actual_qty'], Auth::id());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Gagal menerima hasil produksi: ' . $e->getMessage());
        }

        return redirect()->route('production.show', $order->id)
            ->with('success', 'Hasil produksi diterima. Stok bahan baku terpotong dan produk jadi masuk gudang.');
    }
```

- [ ] **Step 2: Add routes**

In `routes/distribution.php`, add after `production.finish`:

```php
        Route::get('/{id}/receive', [ProductionOrderController::class, 'receiveView'])->name('production.receive')->middleware('permission:Production Order,is_update');
        Route::post('/{id}/receive', [ProductionOrderController::class, 'receive'])->name('production.receive.store')->middleware('permission:Production Order,is_update');
```

- [ ] **Step 3: Write the Receiving view**

Create `resources/views/admin/production/receive.blade.php`:

```blade
<x-app-layout>
    @section('title', 'Terima Hasil Produksi | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => $order->order_number, 'url' => route('production.show', $order->id)],
                ['label' => 'Terima Hasil', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        @php
            $outputUnit = $order->outputUnit?->symbol ?? $order->outputUnit?->name ?? '';
            $bomItems = $order->bom?->items ?? collect();
            $outputPerBatch = (float) ($order->bom?->output_quantity ?: 1);
            $plannedScale = $outputPerBatch > 0 ? (float) $order->planned_qty / $outputPerBatch : (float) $order->planned_qty;
        @endphp

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><small class="text-muted">Produk Jadi</small><div class="fw-medium">{{ $order->variant?->display_name ?? $order->product?->name }}</div></div>
                    <div class="col-md-4"><small class="text-muted">Qty Rencana</small><div class="fw-medium">{{ rtrim(rtrim(number_format((float) $order->planned_qty, 4), '0'), '.') }} {{ $outputUnit }}</div></div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('production.receive.store', $order->id) }}" onsubmit="return confirm('Kirim hasil produksi? Bahan baku akan dipotong dan stok produk jadi bertambah. Tindakan ini final dan tidak bisa diedit.')">
            @csrf
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Qty Aktual Produksi</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Qty Aktual <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="actual_qty" id="actualQty" class="form-control" value="{{ old('actual_qty', (float) $order->planned_qty) }}" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Kebutuhan Bahan Baku</h5></div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Bahan</th>
                                <th class="text-end">Qty Rencana</th>
                                <th class="text-end">Qty Aktual Terpakai</th>
                                <th class="text-end">Sisa/Hemat</th>
                            </tr>
                        </thead>
                        <tbody id="materialRows">
                            @foreach ($bomItems as $item)
                                @php
                                    $unitLabel = $item->unit?->symbol ?? $item->unit?->name ?? '';
                                    $expected = (float) $item->quantity * $plannedScale;
                                @endphp
                                <tr
                                    data-per-batch-qty="{{ (float) $item->quantity }}"
                                    data-expected="{{ $expected }}"
                                    data-unit="{{ $unitLabel }}"
                                >
                                    <td>{{ $item->componentVariant?->display_name ?? $item->componentProduct?->name }}</td>
                                    <td class="text-end expected-cell">{{ rtrim(rtrim(number_format($expected, 4), '0'), '.') }} {{ $unitLabel }}</td>
                                    <td class="text-end actual-cell">-</td>
                                    <td class="text-end sisa-cell">-</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Kirim & Terima</button>
            <a href="{{ route('production.show', $order->id) }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>

    @push('page-js')
    <script>
        const outputPerBatch = {{ $outputPerBatch }};

        function formatQty(n) {
            return (+n).toFixed(4).replace(/\.?0+$/, '');
        }

        function recalc() {
            const actualQty = parseFloat(document.getElementById('actualQty').value || '0');
            const actualScale = outputPerBatch > 0 ? actualQty / outputPerBatch : actualQty;

            document.querySelectorAll('#materialRows tr').forEach(function (tr) {
                const perBatchQty = parseFloat(tr.dataset.perBatchQty || '0');
                const expected = parseFloat(tr.dataset.expected || '0');
                const unit = tr.dataset.unit || '';
                const actualUsed = perBatchQty * actualScale;
                const sisa = expected - actualUsed;

                tr.querySelector('.actual-cell').textContent = formatQty(actualUsed) + (unit ? ' ' + unit : '');
                const sisaCell = tr.querySelector('.sisa-cell');
                sisaCell.textContent = formatQty(sisa) + (unit ? ' ' + unit : '');
                sisaCell.classList.toggle('text-success', sisa > 0);
                sisaCell.classList.toggle('text-danger', sisa < 0);
            });
        }

        document.getElementById('actualQty')?.addEventListener('input', recalc);
        recalc();
    </script>
    @endpush
</x-app-layout>
```

- [ ] **Step 4: Add Rencana/Aktual/Sisa columns to `show.blade.php`'s materials table**

Replace the "Bahan Baku Dikonsumsi" table header:

```php
                            <thead><tr><th>Bahan</th><th class="text-end">Qty</th><th class="text-end">HPP/Unit</th><th class="text-end">Total</th></tr></thead>
```

with:

```php
                            <thead><tr><th>Bahan</th><th class="text-end">Rencana</th><th class="text-end">Aktual Terpakai</th><th class="text-end">Sisa</th><th class="text-end">HPP/Unit</th><th class="text-end">Total</th></tr></thead>
```

Then, inside the `@foreach ($order->materials as $m)` loop, replace the single quantity `<td>`:

```php
                                        <td class="text-end">
                                            <div>
                                                {{ rtrim(rtrim(number_format($materialQty, 4), '0'), '.') }}
                                                @if ($materialUnit)<span class="text-muted ms-1">{{ $materialUnit }}</span>@endif
                                            </div>
                                            @if ($materialConversionHint)
                                                <small class="text-muted">{{ $materialConversionHint }}</small>
                                            @endif
                                        </td>
```

with three cells (Rencana / Aktual / Sisa):

```php
                                        @php $sisa = (float) $m->expected_qty - $materialQty; @endphp
                                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $m->expected_qty, 4), '0'), '.') }} {{ $materialUnit }}</td>
                                        <td class="text-end">
                                            <div>
                                                {{ rtrim(rtrim(number_format($materialQty, 4), '0'), '.') }}
                                                @if ($materialUnit)<span class="text-muted ms-1">{{ $materialUnit }}</span>@endif
                                            </div>
                                            @if ($materialConversionHint)
                                                <small class="text-muted">{{ $materialConversionHint }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end {{ $sisa > 0 ? 'text-success' : ($sisa < 0 ? 'text-danger' : '') }}">{{ rtrim(rtrim(number_format($sisa, 4), '0'), '.') }} {{ $materialUnit }}</td>
```

and update the `<tfoot>` colspan from `colspan="3"` to `colspan="5"`:

```php
                            <tfoot><tr class="fw-bold"><td colspan="5" class="text-end">Total Biaya Bahan</td><td class="text-end">Rp {{ number_format($order->total_material_cost, 2) }}</td></tr></tfoot>
```

and the empty-state colspan from `colspan="4"` to `colspan="6"`:

```php
                                    <tr><td colspan="6" class="text-center text-muted py-3">Belum diproses.</td></tr>
```

- [ ] **Step 5: Verify**

```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan route:list --name=production
```
Expected: `production.receive` (GET) and `production.receive.store` (POST) both present.

In the browser preview: open a `pending_receiving` order's show page (use tinker to flip an existing draft/in_progress order's status to `pending_receiving` if none exists), click "Terima Hasil Produksi", confirm the page loads with the Rencana column pre-filled and Aktual/Sisa showing `-` until you type a qty, type a different actual qty and confirm Aktual/Sisa recalculate live, submit, and confirm you land back on the show page with status `Selesai` and the materials table showing all three columns with real numbers.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/ProductionOrderController.php routes/distribution.php resources/views/admin/production/receive.blade.php resources/views/admin/production/show.blade.php
git commit -m "feat(production): add receiving page with actual-qty input and material variance preview"
```

---

### Task 8: Edit / Update / Destroy for draft orders

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionOrderController.php` (add `edit()`, `update()`, `destroy()`)
- Modify: `routes/distribution.php`
- Create: `resources/views/admin/production/edit.blade.php`

**Interfaces:**
- Produces: `GET production.edit`, `PUT production.update`, `DELETE production.destroy` — all guarded to `status === 'draft'` only.

- [ ] **Step 1: Add controller actions**

Add to `app/Http/Controllers/Admin/ProductionOrderController.php`, after `store()`:

```php
    public function edit(string $id)
    {
        $order = ProductionOrder::with(['variant.product.defaultUnit', 'bom'])->findOrFail($id);

        if ($order->status !== 'draft') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Hanya production order berstatus Draft yang bisa diedit.');
        }

        $units = $order->bom ? ProductionSimulationService::unitOptions($order->bom->product) : [];

        return view('admin.production.edit', compact('order', 'units'));
    }

    public function update(Request $request, string $id)
    {
        $order = ProductionOrder::with('bom')->findOrFail($id);

        if ($order->status !== 'draft') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Hanya production order berstatus Draft yang bisa diedit.');
        }

        $data = $request->validate([
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'production_date' => ['nullable', 'date'],
            'output_expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $bom = $order->bom;
        $plannedUnitId = $data['planned_unit_id'] ?? $bom->output_unit_id;

        try {
            $plannedInOutputUnit = ProductionQuantityNormalizer::toBomOutputUnit(
                $bom,
                (float) $data['planned_qty'],
                $plannedUnitId
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['planned_qty' => $e->getMessage()]);
        }

        $order->update([
            'planned_qty' => $plannedInOutputUnit,
            'overhead_cost' => (float) ($data['overhead_cost'] ?? 0),
            'production_date' => $data['production_date'] ?? $order->production_date,
            'output_expiry_date' => $data['output_expiry_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('production.show', $order->id)->with('success', 'Production Order diperbarui.');
    }

    public function destroy(string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'draft') {
            return back()->with('error', 'Hanya production order berstatus Draft yang bisa dihapus.');
        }

        $order->delete();

        return redirect()->route('production.index')->with('success', 'Production Order dihapus.');
    }
```

- [ ] **Step 2: Add routes**

In `routes/distribution.php`, add right after the `production.store` route:

```php
        Route::get('/{id}/edit', [ProductionOrderController::class, 'edit'])->name('production.edit')->middleware('permission:Production Order,is_update');
        Route::put('/{id}', [ProductionOrderController::class, 'update'])->name('production.update')->middleware('permission:Production Order,is_update');
        Route::delete('/{id}', [ProductionOrderController::class, 'destroy'])->name('production.destroy')->middleware('permission:Production Order,is_delete');
```

- [ ] **Step 3: Write the edit view**

Create `resources/views/admin/production/edit.blade.php`:

```blade
<x-app-layout>
    @section('title', 'Edit Produksi | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Production Order', 'url' => route('production.index')],
                ['label' => 'Edit', 'active' => true],
            ]"
        />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <form method="POST" action="{{ route('production.update', $order->id) }}">
            @csrf
            @method('PUT')
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Perintah Produksi</h5></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 p-3 rounded bg-label-primary mb-3">
                        <span class="avatar-initial rounded-circle bg-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                            <i class="ti ti-box-seam fs-4 text-white"></i>
                        </span>
                        <div>
                            <div class="text-uppercase small text-muted mb-1">Produk Jadi</div>
                            <div class="fw-bold fs-5 mb-0">{{ $order->variant?->display_name ?? $order->product?->name }}</div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Qty Produksi <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="planned_qty" class="form-control" value="{{ old('planned_qty', (float) $order->planned_qty) }}" required>
                            <input type="hidden" name="planned_unit_id" value="{{ old('planned_unit_id', $order->output_unit_id) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Overhead (Rp)</label>
                            <input type="number" step="any" min="0" name="overhead_cost" class="form-control" value="{{ old('overhead_cost', (float) $order->overhead_cost) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tgl Produksi</label>
                            <input type="date" name="production_date" class="form-control" value="{{ old('production_date', optional($order->production_date)->toDateString()) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expired Produk Jadi</label>
                            <input type="date" name="output_expiry_date" class="form-control" value="{{ old('output_expiry_date', optional($order->output_expiry_date)->toDateString()) }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Catatan</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes', $order->notes) }}">
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan Perubahan</button>
            <a href="{{ route('production.show', $order->id) }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>
</x-app-layout>
```

Note: the product/BOM is locked (not editable) here, matching the same pattern as `resources/views/admin/bom/edit.blade.php`. Qty is entered directly in the BOM's output unit (`$order->output_unit_id`) for simplicity — no unit switcher, since this is a draft correction form, not the initial creation flow.

- [ ] **Step 4: Verify**

```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan route:list --name=production
```
Expected: `production.edit` (GET), `production.update` (PUT), `production.destroy` (DELETE) all present.

In the browser: create a fresh draft production order, click through to `/production/{id}/edit`, change the Qty Produksi, submit, confirm the show page reflects the new qty. Then try visiting `/production/{id}/edit` for a `completed` order directly by URL — confirm it redirects to show with the "Hanya production order berstatus Draft..." error message.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ProductionOrderController.php routes/distribution.php resources/views/admin/production/edit.blade.php
git commit -m "feat(production): add edit/update/destroy for draft production orders"
```

---

### Task 9: Index page — Grand Total column + dropdown actions

**Files:**
- Modify: `resources/views/admin/production/index.blade.php`

**Interfaces:**
- Consumes: nothing new (uses fields already loaded by `ProductionOrderController::index()`).

- [ ] **Step 1: Add the Grand Total column and dropdown actions**

Replace the table header:

```php
                        <tr>
                            <th>No. Produksi</th>
                            <th>Tanggal</th>
                            <th>Produk Jadi</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">HPP/Unit</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
```

with:

```php
                        <tr>
                            <th>No. Produksi</th>
                            <th>Tanggal</th>
                            <th>Produk Jadi</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">HPP/Unit</th>
                            <th class="text-end">Grand Total</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
```

Then replace the single "Lihat" action cell:

```php
                                <td class="text-end"><a href="{{ route('production.show', $o->id) }}" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti ti-eye"></i></a></td>
```

with (Grand Total cell added right before it, dropdown/plain-button logic in the action cell):

```php
                                <td class="text-end">
                                    @if ($o->output_unit_cost > 0)
                                        Rp {{ number_format($o->output_unit_cost * $qty, 2) }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @php $statusLabels = ['draft'=>'Draft','in_progress'=>'Sedang Dikerjakan','pending_receiving'=>'Menunggu Receiving','completed'=>'Selesai','cancelled'=>'Dibatalkan']; @endphp
                                    <span class="badge bg-label-{{ $map[$o->status] ?? 'secondary' }}">{{ $statusLabels[$o->status] ?? ucfirst($o->status) }}</span>
                                </td>
                                <td class="text-end">
                                    @if ($o->status === 'draft')
                                        <div class="dropdown">
                                            <button type="button" class="btn btn-sm btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                <i class="ti ti-dots-vertical text-primary"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('production.show', $o->id) }}">
                                                        <i class="ti ti-eye me-2 text-primary"></i>Lihat
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('production.edit', $o->id) }}">
                                                        <i class="ti ti-pencil me-2 text-warning"></i>Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" action="{{ route('production.destroy', $o->id) }}" onsubmit="return confirm('Hapus production order ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="ti ti-trash me-2 text-danger"></i>Hapus
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    @else
                                        <a href="{{ route('production.show', $o->id) }}" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti ti-eye"></i></a>
                                    @endif
                                </td>
```

Also remove the now-duplicated original status `<td>` block right above (the one that previously contained `$map`/badge — it's superseded by the block just added, which already includes the badge). Concretely, delete this block that currently sits between the HPP/Unit `<td>` and the old action `<td>`:

```php
                                <td>
                                    @php $map = ['draft'=>'secondary','in_progress'=>'info','completed'=>'success','cancelled'=>'danger']; @endphp
                                    <span class="badge bg-label-{{ $map[$o->status] ?? 'secondary' }}">{{ ucfirst($o->status) }}</span>
                                </td>
```

but keep the `$map` array definition itself (move it into the `@php` block at the top of the row, alongside `$outputUnit`/`$qty`/`$conversionHint`) since the new status `<td>` above still references `$map`. Concretely, update the row's leading `@php` block from:

```php
                            @php
                                $outputUnit = $o->outputUnit?->symbol ?? $o->outputUnit?->name ?? '';
                                $qty = (float) ($o->produced_qty ?: $o->planned_qty);
                                $conversionHint = $o->product && $o->output_unit_id
                                    ? \App\Support\ProductionQuantityDisplay::conversionSummary($o->product, $qty, $o->output_unit_id)
                                    : null;
                            @endphp
```

to:

```php
                            @php
                                $outputUnit = $o->outputUnit?->symbol ?? $o->outputUnit?->name ?? '';
                                $qty = (float) ($o->produced_qty ?: $o->planned_qty);
                                $conversionHint = $o->product && $o->output_unit_id
                                    ? \App\Support\ProductionQuantityDisplay::conversionSummary($o->product, $qty, $o->output_unit_id)
                                    : null;
                                $map = ['draft'=>'secondary','in_progress'=>'info','pending_receiving'=>'warning','completed'=>'success','cancelled'=>'danger'];
                            @endphp
```

Finally, update the empty-state colspan from `colspan="7"` to `colspan="8"`:

```php
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada produksi.</td></tr>
```

- [ ] **Step 2: Add the dropdown clipping fix**

Append right before `</x-app-layout>` (mirroring `resources/views/admin/bom/index.blade.php`):

```blade
    @push('page-js')
    <script>
        document.querySelectorAll('.table-responsive .dropdown-toggle[data-bs-toggle="dropdown"]').forEach(function (toggle) {
            bootstrap.Dropdown.getOrCreateInstance(toggle, {
                popperConfig: function (defaultConfig) {
                    return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                },
            });
        });
    </script>
    @endpush
```

- [ ] **Step 3: Verify in the browser preview**

Navigate to `http://localhost:8010/production`:
1. Confirm a "Grand Total" column appears with values (or `-` for orders with no `output_unit_cost` yet).
2. Confirm `draft` rows show the "⋮" dropdown with Lihat/Edit/Hapus, and clicking it opens without being clipped by the table.
3. Confirm non-draft rows show a single "Lihat" icon button, no dropdown.
4. Use `preview_console_logs` to confirm no JS errors.

- [ ] **Step 4: Commit**

```bash
git add resources/views/admin/production/index.blade.php
git commit -m "feat(production): add grand total column and draft-only edit/delete dropdown to index"
```

---

## Self-Review Notes

- **Spec coverage:** status flow (Task 6/7), create-form product picker + hidden warehouses (Task 4/5), receiving with actual-qty-only input and auto-calculated material variance (Task 3/7), index Grand Total + draft edit/delete dropdown (Task 9), edit/update/destroy for drafts (Task 8), shared warehouse fallback (Task 2), `expected_qty` data model addition (Task 1) — all spec sections are covered.
- **Placeholder scan:** no TBD/TODO; every step has literal code.
- **Type consistency:** `ManufacturingWarehouseResolver::resolveMaterialWarehouse()`/`resolveOutputWarehouse()` signatures match their call sites in both `BomController` (Task 2) and `ProductionOrderController` (Task 4). `ProductionService::receive(ProductionOrder $order, float $actualQty, ?string $userId = null)` signature matches its call site in `ProductionOrderController::receive()` (Task 7). `expected_qty` column/cast (Task 1) matches its writer (Task 3) and reader (Task 7 show.blade.php).
