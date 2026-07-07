# Production Receiving Unit Picker + Barcode Print Handoff Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let staff enter the actual received production quantity in whichever unit matches what was physically counted (not just the order's base unit), and hand off straight into the existing barcode-print flow, pre-filled with what was actually received, after a successful Receiving submission.

**Architecture:** Add a unit `<select>` to the Receiving form, convert the entered `(qty, unit)` pair to the order's base output unit server-side using the already-fixed `Product::convertQuantity()` chain before calling the unchanged `ProductionService::receive()`. On success, redirect to the existing `product.print-barcode.view` route with query parameters instead of back to the order's show page. Add small, additive pre-fill support to that existing controller action/view so it honors those query parameters while remaining fully backward compatible with its current manual-access behavior.

**Tech Stack:** Laravel 12, Blade, vanilla JS (no new libraries — reuses `Product::getBarcodeQuantityBreakdown()`, `ProductLabelSerialService`, existing print-barcode PDF pipeline unchanged).

## Global Constraints

- Never trust client-side calculated quantities for stock-affecting operations — the server always recalculates from scratch before touching `StockMutationService`. The Receiving form's live JS recalculation (of material Rencana/Aktual/Sisa) is display-only; `ProductionService::receive()` remains the sole source of truth and is not modified by this plan.
- No PHPUnit suite exists for this domain; verify via `php artisan tinker` (read-only queries or non-persisted objects) and the browser preview. **Do not run `php artisan test`** — a prior incident in this session showed the project's `phpunit.xml` does not isolate the test database from the real dev database (the `DB_DATABASE`/sqlite override lines are commented out), and `RefreshDatabase`-based tests will wipe real data. This is a hard rule for this plan: no task may run `php artisan test`, `artisan migrate:fresh`, or any other command that drops/recreates tables, without explicit user approval first.
- The barcode hierarchy engine (`Product::getBarcodeQuantityBreakdown()`, `ProductLabelSerialService`, `ProductQrCodeService`, the PDF templates) is out of scope — reused exactly as-is, no changes.
- The "Print Barcode" action on `/product/items` stays exactly as it is today — no removal, no permission changes on `product.print-barcode.*` routes (still `Product,is_read`/`Product,is_update`).
- No support for mixed/multi-line actual-qty entry (e.g. "20 pack + 5 box") — single qty + single unit picker only, per the approved design.

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `app/Http/Controllers/Admin/ProductionOrderController.php` | Modify | `receiveView()` passes unit options + per-unit conversion factors; `receive()` validates `actual_unit_id`, converts to the order's output unit, redirects to `product.print-barcode.view` with prefill query params on success |
| `resources/views/admin/production/receive.blade.php` | Modify | Add unit `<select>` next to "Qty Aktual"; JS live-preview recalculation accounts for the selected unit's conversion factor |
| `app/Http/Controllers/Admin/ProductController.php` | Modify | `printBarcodeView()` reads optional `variant_id`/`unit_id`/`quantity` query params and uses them as pre-fill defaults (falls back to today's behavior when absent) |
| `resources/views/admin/product/master/print-barcode.blade.php` | Modify | Variant `<option>` gains `@selected` support; quantity input's default value honors the passed-in prefill |

---

### Task 1: Receiving form unit picker + redirect to barcode print

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductionOrderController.php:319-353` (`receiveView()`, `receive()`)
- Modify: `resources/views/admin/production/receive.blade.php`

**Interfaces:**
- Consumes: `ProductionSimulationService::unitOptions(Product $product): array` (existing, returns `[{id, label, level, hint}]`), `App\Support\ProductionQuantityNormalizer::toBomOutputUnit(BillOfMaterial $bom, float $qty, string $unitId): float` (existing), `ProductionService::receive(ProductionOrder $order, float $actualQty, ?string $userId): ProductionOrder` (existing, unchanged signature).
- Produces: after this task, `production.receive.store`'s success path redirects to `route('product.print-barcode.view', [...])` with `variant_id`, `unit_id`, `quantity`, `print_mode` query params — Task 2 makes that destination route actually honor them (until Task 2 lands, the destination page will just ignore the extra query params and show its normal defaults, which is a safe intermediate state, not a broken one).

- [ ] **Step 1: Update `receiveView()` to pass unit options and per-unit conversion factors**

Read the current method first:

```bash
sed -n '319,336p' app/Http/Controllers/Admin/ProductionOrderController.php
```

Replace:

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
```

with:

```php
    public function receiveView(string $id)
    {
        $order = ProductionOrder::with([
            'product.defaultUnit',
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'variant',
            'outputUnit',
            'bom.items.componentVariant.product',
            'bom.items.unit',
        ])->findOrFail($id);

        if ($order->status !== 'pending_receiving') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Production order ini belum siap untuk diterima.');
        }

        $units = ProductionSimulationService::unitOptions($order->product);

        // Faktor konversi tiap satuan RELATIF ke output_unit_id order ini, dipakai
        // JS di halaman Receiving untuk preview live (server tetap hitung ulang sendiri
        // saat submit — ini murni tampilan).
        $unitFactors = collect($units)->mapWithKeys(function (array $unit) use ($order) {
            $factor = $order->product->convertQuantity(1.0, $unit['id'], $order->output_unit_id) ?? 1.0;

            return [$unit['id'] => $factor];
        });

        return view('admin.production.receive', compact('order', 'units', 'unitFactors'));
    }
```

- [ ] **Step 2: Update `receive()` to accept and convert `actual_unit_id`**

Replace:

```php
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

with:

```php
    public function receive(Request $request, string $id)
    {
        $order = ProductionOrder::with('bom')->findOrFail($id);

        $data = $request->validate([
            'actual_qty' => ['required', 'numeric', 'min:0.000001'],
            'actual_unit_id' => ['required', 'uuid'],
        ]);

        try {
            $actualQtyInOutputUnit = ProductionQuantityNormalizer::toBomOutputUnit(
                $order->bom,
                (float) $data['actual_qty'],
                $data['actual_unit_id']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['actual_qty' => $e->getMessage()]);
        }

        try {
            ProductionService::receive($order, $actualQtyInOutputUnit, Auth::id());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Gagal menerima hasil produksi: ' . $e->getMessage());
        }

        return redirect()->route('product.print-barcode.view', [
            'id' => $order->product_id,
            'variant_id' => $order->product_variant_id,
            'unit_id' => $data['actual_unit_id'],
            'quantity' => (int) round((float) $data['actual_qty']),
            'print_mode' => 'hierarchy',
        ])->with('success', 'Hasil produksi diterima. Stok bahan baku terpotong dan produk jadi masuk gudang.');
    }
```

Note: `quantity` is rounded to an integer because the barcode print form's `quantity` field is `type="number" min="1"` with no `step="any"` — it expects a whole count of the chosen unit (you can't print "2.5 pack" worth of labels). If a non-integer actual qty is entered in a unit (e.g. 2.5 pack), the print form will receive `round(2.5) = 3` as a starting suggestion; the user still reviews/edits it on the print page before printing, so this is a reasonable default, not a silent data error.

- [ ] **Step 3: Add the unit picker to `receive.blade.php` and make the JS preview unit-aware**

Read the current file first:

```bash
cat resources/views/admin/production/receive.blade.php
```

Replace the "Qty Aktual Produksi" card body:

```php
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Qty Aktual <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="actual_qty" id="actualQty" class="form-control" value="{{ old('actual_qty', (float) $order->planned_qty) }}" required>
                        </div>
                    </div>
                </div>
```

with:

```php
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Qty Aktual <span class="text-danger">*</span></label>
                            <input type="number" step="any" min="0.000001" name="actual_qty" id="actualQty" class="form-control" value="{{ old('actual_qty', (float) $order->planned_qty) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Satuan <span class="text-danger">*</span></label>
                            <select name="actual_unit_id" id="actualUnitId" class="form-select" required>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit['id'] }}" @selected(old('actual_unit_id', $order->output_unit_id) === $unit['id'])>
                                        {{ $unit['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
```

Replace the JS `outputPerBatch`/`recalc()` block:

```php
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
```

with:

```php
    @push('page-js')
    <script>
        const outputPerBatch = {{ $outputPerBatch }};
        const unitFactors = @json($unitFactors);

        function formatQty(n) {
            return (+n).toFixed(4).replace(/\.?0+$/, '');
        }

        function recalc() {
            const actualQty = parseFloat(document.getElementById('actualQty').value || '0');
            const selectedUnitId = document.getElementById('actualUnitId').value;
            const factor = unitFactors[selectedUnitId] ?? 1;
            const actualQtyInOutputUnit = actualQty * factor;
            const actualScale = outputPerBatch > 0 ? actualQtyInOutputUnit / outputPerBatch : actualQtyInOutputUnit;

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
        document.getElementById('actualUnitId')?.addEventListener('change', recalc);
        recalc();
    </script>
    @endpush
```

- [ ] **Step 4: Verify via tinker (read-only) and the browser preview**

First, a read-only sanity check that `unitOptions()`/`convertQuantity()` behave as expected against real data (no writes):

```bash
"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php" artisan tinker --execute="
\$bom = App\Models\BillOfMaterial::where('is_active', true)->with('product.unitConversions')->first();
if (!\$bom) { echo 'NO_ACTIVE_BOM — create one manually in the browser first, then re-run this check.'; exit; }
\$units = App\Services\Manufacturing\ProductionSimulationService::unitOptions(\$bom->product);
echo 'units: ' . json_encode(\$units) . PHP_EOL;
foreach (\$units as \$u) {
    \$factor = \$bom->product->convertQuantity(1.0, \$u['id'], \$bom->output_unit_id);
    echo \$u['label'] . ' -> output_unit factor = ' . \$factor . PHP_EOL;
}
"
```
Expected: one row per unit in the product's conversion chain, with sane factors (e.g. the order's own output unit shows factor `1`, smaller units show fractional factors, no `null`/exception).

Then in the browser (start the dev server via `preview_start` with the `laravel` config if not already running):
1. Create a fresh draft production order (or reuse one and drive it via the "Mulai Produksi"/"Selesaikan Produksi" buttons) until it reaches `pending_receiving`.
2. Open its "Terima Hasil Produksi" page. Confirm the new "Satuan" dropdown appears, defaulting to the order's output unit.
3. Change "Qty Aktual" and the "Satuan" dropdown to a smaller unit (e.g. switch from the order's base unit to a smaller one) and confirm the Aktual/Sisa columns recalculate live and change when the unit selection changes (not just when the qty changes).
4. Submit. Confirm you land on `/product/items/{id}/print-barcode` (even though it won't yet honor the prefill query params until Task 2 — confirm the redirect target URL itself is correct, e.g. via `preview_network` or the browser address bar, and that it contains `variant_id`, `unit_id`, `quantity`, `print_mode` query params).
5. Confirm via tinker that the production order's materials were actually deducted using the qty converted to the output unit (not the raw typed number in the smaller unit) — pick the order you just completed and check its `ProductionOrderMaterial` rows' `qty_consumed` against the expected converted value.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ProductionOrderController.php resources/views/admin/production/receive.blade.php
git commit -m "feat(production): let receiving qty be entered in any unit, redirect to barcode print"
```

---

### Task 2: Barcode print page pre-fill support

**Files:**
- Modify: `app/Http/Controllers/Admin/ProductController.php` (`printBarcodeView()`)
- Modify: `resources/views/admin/product/master/print-barcode.blade.php`

**Interfaces:**
- Consumes: query params `variant_id`, `unit_id`, `quantity` (all optional) set by Task 1's redirect. `print_mode` is also passed by Task 1's redirect but requires no code change here — the form already defaults its "hierarchy" radio to `checked` whenever `$hasUnitHierarchy` is true, which is exactly what Task 1 always requests.
- Produces: nothing consumed by a later task — this is the final task in this plan.

- [ ] **Step 1: Read `printBarcodeView()`'s current body**

```bash
sed -n '319,389p' app/Http/Controllers/Admin/ProductController.php
```

- [ ] **Step 2: Add query-param pre-fill, falling back to current behavior**

Locate this line inside `printBarcodeView()` (it computes the unit pre-selected in the dropdown):

```php
        $units = $product->getBarcodeUnits()->values()->map(function (ProductUnit $unit, int $index) use ($product, $serialService) {
```

Immediately **before** that line, insert:

```php
        $prefillVariantId = $request->query('variant_id');
        $prefillUnitId = $request->query('unit_id');
        $prefillQuantity = $request->query('quantity');

```

(This requires `Illuminate\Http\Request $request` to be available as a parameter on `printBarcodeView()`. Check its current signature first:

```bash
grep -n "public function printBarcodeView" app/Http/Controllers/Admin/ProductController.php
```

If it currently reads `public function printBarcodeView(string $id, ProductLabelSerialService $serialService)` with no `Request $request` parameter, change it to:

```php
    public function printBarcodeView(Request $request, string $id, ProductLabelSerialService $serialService)
```

`Illuminate\Http\Request` is Laravel's standard request class — confirm it's already imported at the top of this controller file (`grep -n "use Illuminate\\\\Http\\\\Request;" app/Http/Controllers/Admin/ProductController.php`); if not, add `use Illuminate\Http\Request;` alongside the other `use` statements.)

Then find this line (the `defaultUnitId` computed for the view, currently always `$product->default_unit_id`):

```php
            'defaultUnitId' => $product->default_unit_id,
```

Replace it with:

```php
            'defaultUnitId' => ($prefillUnitId && $units->contains(fn (array $u) => $u['id'] === $prefillUnitId))
                ? $prefillUnitId
                : $product->default_unit_id,
            'prefillVariantId' => $prefillVariantId,
            'prefillQuantity' => $prefillQuantity,
```

(This must be inserted into the same `return view('admin.product.master.print-barcode', [...])` array that already contains `'defaultUnitId' => $product->default_unit_id,` — replace that one line with the three lines above, keeping every other array entry in that `return view(...)` call unchanged.)

- [ ] **Step 3: Pre-select the variant dropdown and pre-fill quantity in the view**

Read the current variant/quantity markup:

```bash
sed -n '136,161p' resources/views/admin/product/master/print-barcode.blade.php
```

Replace:

```php
                            @if ($variants->count() > 1)
                                <div class="mb-3">
                                    <label class="form-label" for="variant_id">Varian</label>
                                    <select name="variant_id" id="variant_id" class="form-select select2">
                                        <option value="">— Semua / Default —</option>
                                        @foreach ($variants as $variant)
                                            <option value="{{ $variant['id'] }}">{{ $variant['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="mb-4">
                                <label class="form-label" for="quantity" id="quantityLabel">Jumlah Satuan Terbesar (Qty)</label>
                                <input
                                    type="number"
                                    name="quantity"
                                    id="quantity"
                                    class="form-control"
                                    min="1"
                                    max="{{ $maxHierarchyParentQty ?? 500 }}"
                                    value="{{ old('quantity', 1) }}"
                                    required
                                >
```

with:

```php
                            @if ($variants->count() > 1)
                                <div class="mb-3">
                                    <label class="form-label" for="variant_id">Varian</label>
                                    <select name="variant_id" id="variant_id" class="form-select select2">
                                        <option value="">— Semua / Default —</option>
                                        @foreach ($variants as $variant)
                                            <option value="{{ $variant['id'] }}" @selected(($prefillVariantId ?? null) === $variant['id'])>{{ $variant['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="mb-4">
                                <label class="form-label" for="quantity" id="quantityLabel">Jumlah Satuan Terbesar (Qty)</label>
                                <input
                                    type="number"
                                    name="quantity"
                                    id="quantity"
                                    class="form-control"
                                    min="1"
                                    max="{{ $maxHierarchyParentQty ?? 500 }}"
                                    value="{{ old('quantity', $prefillQuantity ?? 1) }}"
                                    required
                                >
```

(The unit `<select>` needs no view change — it already reads `@selected($defaultUnitId === $unit['id'])` at line 97, and Step 2 already made `$defaultUnitId` honor the prefill.)

- [ ] **Step 4: Verify**

In the browser, visit the print-barcode page directly with query params appended manually, e.g.:
`http://localhost:8010/product/items/{a-real-product-id}/print-barcode?variant_id={a-real-variant-id}&unit_id={a-real-unit-id}&quantity=25&print_mode=hierarchy`

Confirm: the unit dropdown pre-selects the given `unit_id`, the variant dropdown pre-selects the given `variant_id` (if that product has more than one variant), the quantity field shows `25`, and the "Berdasarkan satuan terbesar" (hierarchy) mode radio is checked. Then visit the same page with **no** query params at all and confirm it behaves exactly as before (defaults to the product's own default unit, qty 1, no variant selected) — this is the regression check that the pre-fill is additive-only.

Then repeat the full Task 1 browser flow (production order → Mulai → Selesaikan → Terima with a smaller unit) end-to-end and confirm the final redirect now lands on a **fully pre-filled** print-barcode page ready to preview/print.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Admin/ProductController.php resources/views/admin/product/master/print-barcode.blade.php
git commit -m "feat(product): support query-param prefill on barcode print page for receiving handoff"
```

---

## Self-Review Notes

- **Spec coverage:** §1 (unit picker + conversion) → Task 1; §2 (redirect with query params) → Task 1 Step 2; §3 (breakdown starts at received unit, no engine changes) → satisfied by construction (Task 1 passes the raw received unit/qty straight through, Task 2 makes the destination honor it, `getBarcodeQuantityBreakdown()` itself is never touched); §4 (pre-fill, additive) → Task 2; §5 (permissions unchanged) → no task touches permission middleware, satisfied by omission. All spec sections covered.
- **Placeholder scan:** no TBD/TODO; every step has literal code or an exact command.
- **Type consistency:** `ProductionSimulationService::unitOptions(Product $product): array` signature matches its use in Task 1 Step 1 (called on `$order->product`, an `App\Models\Product` instance via the `product` relation). `ProductionQuantityNormalizer::toBomOutputUnit(BillOfMaterial $bom, float $qty, string $unitId): float` matches its call in Task 1 Step 2 (`$order->bom` eager-loaded in the same method). `$unitFactors` (a `Collection` keyed by unit id) produced in Task 1 Step 1 matches its consumption via `@json($unitFactors)` in Task 1 Step 3's JS, which indexes it as a plain object (`unitFactors[selectedUnitId]`) — `@json()` on a Laravel `Collection` serializes to a JSON object when the collection has non-sequential/string keys, which is the case here (UUIDs), so this is correct.
