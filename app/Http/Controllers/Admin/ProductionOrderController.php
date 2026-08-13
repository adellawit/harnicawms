<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\ProductionOrder;
use App\Models\ProductUnit;
use App\Models\Warehouse;
use App\Models\ProductLabelSerial;
use App\Services\Product\ProductLabelSerialService;
use App\Services\StockAvailabilityService;
use App\Services\Manufacturing\ProductionService;
use App\Services\Manufacturing\ProductionSimulationService;
use App\Support\ManufacturingWarehouseResolver;
use App\Support\ProductionQuantityNormalizer;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProductionOrderController extends Controller
{
    public function index()
    {
        $orders = ProductionOrder::with([
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'product.defaultUnit',
            'variant',
            'outputUnit',
            'branch',
            'sourceWarehouse',
            'outputWarehouse',
        ])
            ->orderByDesc('created_at')
            ->get();

        $barcodeCounts = DB::table('product.product_label_serials')
            ->where('source_type', ProductLabelSerial::SOURCE_PRODUCTION_ORDER)
            ->whereIn('source_id', $orders->pluck('id'))
            ->groupBy('source_id')
            ->selectRaw('source_id, COUNT(*)::int as total')
            ->pluck('total', 'source_id');

        return view('admin.production.index', compact('orders', 'barcodeCounts'));
    }

    public function create()
    {
        $outputs = BillOfMaterial::query()
            ->with(['variant.product', 'outputUnit'])
            ->where('is_active', true)
            ->whereNotNull('product_variant_id')
            ->orderBy('name')
            ->get()
            ->map(function (BillOfMaterial $bom) {
                $label = $bom->variant?->display_name
                    ?? $bom->variant?->product?->name
                    ?? $bom->name;

                return [
                    'id' => $bom->product_variant_id,
                    'label' => $label,
                    'bom_name' => $bom->name,
                    'output_unit' => $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? '',
                    'output_quantity' => (float) $bom->output_quantity,
                ];
            })
            ->unique('id')
            ->values();

        return view('admin.production.create', compact('outputs'));
    }

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
            return response()->json(['message' => 'This product has no BOM recipe.'], 404);
        }

        return response()->json([
            'bom_id' => $bom->id,
            'output_unit_id' => $bom->output_unit_id,
            'output_quantity' => (float) $bom->output_quantity,
            'output_unit' => $bom->outputUnit?->symbol ?? $bom->outputUnit?->name ?? '',
            'units' => ProductionSimulationService::unitOptions($bom->product),
        ]);
    }

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

        if (! $warehouse) {
            return response()->json(['message' => 'No active warehouse found for this company.'], 422);
        }

        try {
            $scale = ProductionQuantityNormalizer::materialScale(
                $bom,
                (float) $data['planned_qty'],
                $plannedUnitId
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $items = $bom->items->map(function ($item) use ($warehouse, $branchId, $scale) {
            $available = StockAvailabilityService::availableQuantity(
                $item->component_variant_id,
                $branchId,
                $item->unit_id,
                $warehouse->id
            );

            $componentProduct = $item->componentVariant?->product ?? $item->componentProduct;
            $isSmallestUnit = $componentProduct && $item->unit_id === $componentProduct->getSmallestUnitId();

            return [
                'label' => $item->componentVariant?->display_name ?? $item->componentProduct?->name,
                'qty' => (float) $item->quantity * $scale,
                'unit' => $item->unit?->symbol ?? $item->unit?->name ?? '',
                // Stok yang sudah melewati beberapa kali konversi non-power-of-10 (mis. ÷30)
                // bisa membawa sisa desimal sangat kecil (mis. 5079.99991 / 4956.0014). Ini
                // murni kosmetik untuk preview — kalau satuannya adalah satuan terkecil produk
                // (mis. sachet), pecahan selalu noise (fisiknya tidak mungkin pecahan sachet)
                // jadi dibulatkan tanpa syarat; kalau bukan, snap hanya kalau dekat bulat.
                'available' => ProductionQuantityNormalizer::snapDisplayQty($available, $isSmallestUnit),
            ];
        })->values();

        return response()->json([
            'warehouse_name' => $warehouse->name,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
            'production_date' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $overheads = $this->validateOverheadPayload($request);
        $overheadTotal = $this->sumOverheadAmounts($overheads);

        $bom = BillOfMaterial::with(['product.unitConversions', 'product.defaultUnit', 'outputUnit'])
            ->where('product_variant_id', $data['product_variant_id'])
            ->where('is_active', true)
            ->first();

        if (! $bom) {
            return back()->withInput()->withErrors(['product_variant_id' => 'This product has no BOM recipe. Create a Bill of Materials first.']);
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

        $productionDate = HelperController::parseDate($data['production_date'] ?? null);

        $distributor = WmsContext::distributor();
        $distId = optional($distributor)->id;
        $userId = Auth::id();

        [$sourceWarehouseId, $materialBranchId] = ManufacturingWarehouseResolver::resolveMaterialWarehouse($distId);
        [$outputWarehouseId, $outputBranchId] = ManufacturingWarehouseResolver::resolveOutputWarehouse($distId);
        $branchId = $outputBranchId ?: $materialBranchId ?: auth('web')->user()?->getBranchIdForTransaction();

        try {
            $order = DB::transaction(function () use (
                $data,
                $bom,
                $plannedInOutputUnit,
                $productionDate,
                $overheadTotal,
                $overheads,
                $distId,
                $branchId,
                $sourceWarehouseId,
                $outputWarehouseId,
                $userId
            ) {
                $order = ProductionOrder::create([
                    'order_number' => ProductionService::generateNumber(),
                    'production_date' => $productionDate,
                    'company_id' => $distId,
                    'branch_id' => $branchId,
                    'source_warehouse_id' => $sourceWarehouseId,
                    'output_warehouse_id' => $outputWarehouseId,
                    'bom_id' => $bom->id,
                    'product_id' => $bom->product_id,
                    'product_variant_id' => $bom->product_variant_id,
                    'output_unit_id' => $bom->output_unit_id,
                    'planned_qty' => $plannedInOutputUnit,
                    'overhead_cost' => $overheadTotal,
                    'status' => 'in_progress',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $this->syncOverheads($order, $overheads);
                ProductionService::consumeMaterials($order, $plannedInOutputUnit, $userId);

                return $order;
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to create production order: ' . $e->getMessage());
        }

        return redirect()->route('production.index')->with('success', 'Production order created successfully. Status set to Process. Raw materials have been deducted from stock.');
    }

    public function edit(string $id)
    {
        $order = ProductionOrder::with(['variant.product.defaultUnit', 'bom', 'overheads'])->findOrFail($id);

        if ($order->status !== 'draft') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Only draft production orders can be edited.');
        }

        $units = $order->bom ? ProductionSimulationService::unitOptions($order->bom->product) : [];

        return view('admin.production.edit', compact('order', 'units'));
    }

    public function update(Request $request, string $id)
    {
        $order = ProductionOrder::with('bom')->findOrFail($id);

        if ($order->status !== 'draft') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Only draft production orders can be edited.');
        }

        $data = $request->validate([
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'planned_unit_id' => ['nullable', 'uuid'],
            'production_date' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $overheads = $this->validateOverheadPayload($request);
        $overheadTotal = $this->sumOverheadAmounts($overheads);

        $bom = $order->bom;

        if (! $bom) {
            return back()->withInput()->withErrors(['product_variant_id' => 'This product has no BOM recipe. Create a Bill of Materials first.']);
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

        $productionDate = HelperController::parseDate(
            $data['production_date'] ?? optional($order->production_date)->format('d/m/Y')
        );

        try {
            DB::transaction(function () use ($order, $data, $plannedInOutputUnit, $productionDate, $overheadTotal, $overheads) {
                ProductionService::reverseMaterials($order, Auth::id());

                $order->update([
                    'planned_qty' => $plannedInOutputUnit,
                    'overhead_cost' => $overheadTotal,
                    'production_date' => $productionDate,
                    'notes' => $data['notes'] ?? null,
                    'updated_by' => Auth::id(),
                ]);

                $this->syncOverheads($order, $overheads);
                ProductionService::consumeMaterials($order, $plannedInOutputUnit, Auth::id());
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to update production order: ' . $e->getMessage());
        }

        return redirect()->route('production.show', $order->id)->with('success', 'Production order updated successfully.');
    }

    public function destroy(string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'draft') {
            return back()->with('error', 'Only draft production orders can be deleted.');
        }

        try {
            DB::transaction(function () use ($order) {
                ProductionService::reverseMaterials($order, Auth::id());
                $order->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to delete production order: ' . $e->getMessage());
        }

        return redirect()->route('production.index')->with('success', 'Production order deleted successfully. Raw materials have been restored to stock.');
    }

    public function show(string $id)
    {
        $order = ProductionOrder::with([
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'product.defaultUnit',
            'variant',
            'outputUnit',
            'branch',
            'sourceWarehouse',
            'outputWarehouse',
            'bom.items.componentVariant.product.unitConversions',
            'bom.items.componentVariant.product.defaultUnit',
            'materials.componentVariant.product.unitConversions',
            'materials.componentVariant.product.defaultUnit',
            'materials.componentProduct.unitConversions',
            'materials.componentProduct.defaultUnit',
            'materials.unit',
            'outputs.variant.product.unitConversions',
            'outputs.variant.product.defaultUnit',
            'outputs.unit',
            'overheads',
        ])->findOrFail($id);

        return view('admin.production.show', compact('order'));
    }

    public function start(string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'draft') {
            return back()->with('error', 'Only draft production orders can be set to Process.');
        }

        $order->update(['status' => 'in_progress', 'updated_by' => Auth::id()]);

        return redirect()->route('production.show', $order->id)->with('success', 'Production order status updated to Process.');
    }

    public function finish(string $id)
    {
        $order = ProductionOrder::findOrFail($id);

        if ($order->status !== 'in_progress') {
            return back()->with('error', 'Only in-progress production orders can be marked as finished.');
        }

        $order->update(['status' => 'pending_receiving', 'updated_by' => Auth::id()]);

        return redirect()->route('production.show', $order->id)->with('success', 'Floor production finished. Continue to Receiving to record actual output.');
    }

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

        if (! in_array($order->status, ['in_progress', 'pending_receiving'], true)) {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Set the production order to Process before receiving.');
        }

        if ($order->status !== 'pending_receiving') {
            $order->update([
                'status' => 'pending_receiving',
                'updated_by' => Auth::id(),
            ]);
        }

        $units = ProductionSimulationService::unitOptions($order->product);

        // Faktor konversi tiap satuan RELATIF ke output_unit_id order ini, dipakai
        // JS di halaman Receiving untuk preview live (server tetap hitung ulang sendiri
        // saat submit — ini murni tampilan).
        $unitFactors = collect($units)->mapWithKeys(function (array $unit) use ($order) {
            $factor = $order->product->convertQuantity(1.0, $unit['id'], $order->output_unit_id) ?? 1.0;

            return [$unit['id'] => $factor];
        });

        // Rantai satuan → satuan terkecil, untuk preview "X Karton & sisa Y Sachet".
        $smallestUnitId = $order->product?->getSmallestUnitId();
        $unitChain = collect($units)->map(function (array $unit) use ($order, $smallestUnitId) {
            $toSmallest = $smallestUnitId
                ? ($order->product->convertQuantity(1.0, $unit['id'], $smallestUnitId) ?? 1.0)
                : 1.0;

            return [
                'id' => $unit['id'],
                'label' => $unit['label'],
                'to_smallest' => (float) $toSmallest,
            ];
        })->values()->all();

        $order->loadMissing([
            'sourceWarehouse',
            'outputWarehouse',
            'materials.unit',
            'materials.componentVariant',
            'materials.componentProduct',
        ]);

        $defaultUnitId = old('actual_unit_id', $units[0]['id'] ?? $order->output_unit_id);
        $plannedQty = (float) $order->planned_qty;
        $defaultActualQty = old('actual_qty');
        if ($defaultActualQty === null && $order->product && $defaultUnitId && $order->output_unit_id) {
            $converted = $order->product->convertQuantity($plannedQty, $order->output_unit_id, $defaultUnitId);
            $defaultActualQty = $converted !== null ? $converted : $plannedQty;
        } elseif ($defaultActualQty === null) {
            $defaultActualQty = $plannedQty;
        }

        return view('admin.production.receive', compact(
            'order',
            'units',
            'unitFactors',
            'unitChain',
            'defaultUnitId',
            'defaultActualQty'
        ));
    }

    public function receive(Request $request, string $id)
    {
        $order = ProductionOrder::with('bom')->findOrFail($id);

        $data = $request->validate([
            'actual_qty' => ['required', 'numeric', 'min:0.000001'],
            'actual_unit_id' => ['required', 'uuid'],
            'output_expiry_date' => ['required', 'string'],
        ]);

        if (! $order->bom) {
            return back()->withInput()->withErrors(['product_variant_id' => 'This product has no BOM recipe. Create a Bill of Materials first.']);
        }

        try {
            $actualQtyInOutputUnit = ProductionQuantityNormalizer::toBomOutputUnit(
                $order->bom,
                (float) $data['actual_qty'],
                $data['actual_unit_id']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['actual_qty' => $e->getMessage()]);
        }

        $order->output_expiry_date = HelperController::parseDate($data['output_expiry_date']);
        if (! $order->output_expiry_date) {
            return back()->withInput()->withErrors(['output_expiry_date' => 'Expiry Date is required.']);
        }

        try {
            ProductionService::receive($order, $actualQtyInOutputUnit, Auth::id());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to receive production output: ' . $e->getMessage());
        }

        $printUnitId = $data['actual_unit_id'];
        $printQuantity = max(1, (int) round((float) $data['actual_qty']));

        // Lock barcode serials at receive time (PDF later only reprints locked numbers).
        try {
            $order->loadMissing(['product.defaultUnit', 'product.unitConversions']);
            if ($order->product) {
                app(ProductLabelSerialService::class)->allocateHierarchyForSource(
                    product: $order->product,
                    parentUnitId: $printUnitId,
                    parentQuantity: $printQuantity,
                    variantId: $order->product_variant_id,
                    userId: Auth::id(),
                    sourceType: 'production_order',
                    sourceId: $order->id,
                    includeSmallestUnit: false,
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to allocate production barcode serials on receive', [
                'production_order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('production.receive.print', [
                'id' => $order->id,
                'unit_id' => $printUnitId,
                'quantity' => $printQuantity,
            ])->with('error', 'Barang jadi diterima, tetapi alokasi barcode gagal: '.$e->getMessage());
        }

        return redirect()->route('production.receive.print', [
            'id' => $order->id,
            'unit_id' => $printUnitId,
            'quantity' => $printQuantity,
        ])->with('success', 'Production output received. Barcode serials locked — silakan preview & Save PDF.');
    }

    public function receivePrint(Request $request, string $id)
    {
        $order = ProductionOrder::with(['product.defaultUnit', 'variant'])->findOrFail($id);

        if ($order->status !== 'completed') {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'This production order has not been fully received yet.');
        }

        $data = $request->validate([
            'unit_id' => ['nullable', 'uuid'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $printUnitId = $data['unit_id']
            ?? $order->product?->default_unit_id
            ?? $order->output_unit_id;

        $unit = $printUnitId ? ProductUnit::find($printUnitId) : null;
        if (! $unit) {
            return redirect()->route('production.show', $order->id)
                ->with('error', 'Invalid barcode print unit.');
        }

        $quantity = isset($data['quantity']) ? (int) $data['quantity'] : null;
        if ($quantity === null) {
            $producedQty = (float) $order->produced_qty;
            if ($order->product && $order->output_unit_id && $unit->id !== $order->output_unit_id && $producedQty > 0) {
                $converted = $order->product->convertQuantity($producedQty, $order->output_unit_id, $unit->id);
                $producedQty = $converted !== null ? (float) $converted : $producedQty;
            }
            $quantity = max(1, (int) round($producedQty));
        }

        $distributor = WmsContext::distributor();
        $distributorName = strtoupper($distributor?->legal_name ?: $distributor?->name ?: config('app.name'));

        return view('admin.production.receive-print', [
            'order' => $order,
            'unit' => $unit,
            'quantity' => $quantity,
            'distributorName' => $distributorName,
        ]);
    }

    public function barcodes(Request $request, string $id)
    {
        $order = ProductionOrder::with([
            'product.defaultUnit',
            'variant',
            'outputUnit',
        ])->findOrFail($id);

        $perPage = (int) $request->input('per_page', 50);
        $perPage = in_array($perPage, [25, 50, 100, 200], true) ? $perPage : 50;
        $unitId = $request->input('unit_id') ?: null;
        $status = $request->input('status'); // ready|dispatched|all

        $baseQuery = ProductLabelSerial::query()
            ->with(['unit:id,name,symbol', 'variant:id,sku'])
            ->withExists(['salesAssignments as is_dispatched'])
            ->where('source_type', ProductLabelSerial::SOURCE_PRODUCTION_ORDER)
            ->where('source_id', $order->id);

        $summary = DB::table('product.product_label_serials as pls')
            ->leftJoin('product.product_units as pu', 'pu.id', '=', 'pls.unit_id')
            ->where('pls.source_type', ProductLabelSerial::SOURCE_PRODUCTION_ORDER)
            ->where('pls.source_id', $order->id)
            ->groupBy('pls.unit_id', 'pls.unit_level', 'pu.name', 'pu.symbol')
            ->orderBy('pls.unit_level')
            ->selectRaw('
                pls.unit_id,
                pls.unit_level,
                pu.name as unit_name,
                pu.symbol as unit_symbol,
                COUNT(*)::int as total,
                COUNT(*) FILTER (
                    WHERE EXISTS (
                        SELECT 1
                        FROM transaction.sales_order_item_serial_assignments a
                        WHERE a.product_label_serial_id = pls.id
                    )
                )::int as dispatched
            ')
            ->get();

        $summaryRows = $summary->map(function ($row) {
            return (object) [
                'unit_id' => $row->unit_id,
                'unit_level' => $row->unit_level,
                'unit_label' => strtoupper($row->unit_symbol ?: ($row->unit_name ?: ('L'.$row->unit_level))),
                'total' => (int) $row->total,
                'dispatched' => (int) $row->dispatched,
                'ready' => (int) $row->total - (int) $row->dispatched,
            ];
        });

        $serialsQuery = (clone $baseQuery)
            ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
            ->when($status === 'ready', function ($q) {
                $q->whereDoesntHave('salesAssignments');
            })
            ->when($status === 'dispatched', function ($q) {
                $q->whereHas('salesAssignments');
            })
            ->orderBy('unit_level')
            ->orderBy('sequence');

        $serials = $serialsQuery->paginate($perPage)->withQueryString();

        $order->loadMissing([
            'product.defaultUnit',
            'product.unitConversions.fromUnit',
            'product.unitConversions.toUnit',
            'outputUnit',
        ]);

        $qtyBase = (float) $order->produced_qty > 0
            ? (float) $order->produced_qty
            : (float) $order->planned_qty;
        $qtyUnitId = $order->output_unit_id ?: $order->product?->default_unit_id;

        $conversionChain = [];
        $qtyLevels = [];
        $packagingRows = [];
        $expectedBarcodeRows = [];
        $expectedVsActual = [];

        if ($order->product && $qtyUnitId && $qtyBase > 0) {
            $product = $order->product;
            $qtyLevels = \App\Support\ProductionQuantityDisplay::qtyLevelBreakdown($product, $qtyBase, $qtyUnitId);
            $packagingRows = \App\Support\ProductionQuantityDisplay::packagingBreakdown($product, $qtyBase, $qtyUnitId);

            $chain = $product->getBarcodeUnits()->values();
            foreach ($chain as $index => $unit) {
                $parts = [];
                $cursorId = $unit->id;
                for ($j = $index; $j < $chain->count() - 1; $j++) {
                    $from = $chain[$j];
                    $to = $chain[$j + 1];
                    $factor = null;
                    foreach ($product->unitConversions as $conv) {
                        if ($conv->from_unit_id === $from->id && $conv->to_unit_id === $to->id) {
                            $factor = (float) $conv->conversion_factor;
                            break;
                        }
                    }
                    if ($factor === null || $factor <= 0) {
                        break;
                    }
                    $parts[] = '1 '.strtoupper($from->symbol ?: $from->name)
                        .' = '.(int) $factor.' '.strtoupper($to->symbol ?: $to->name);
                }
                if ($index === 0 && $parts !== []) {
                    $conversionChain = $parts;
                }
            }

            // Expected labels: same rule as receive print (hierarchy, exclude smallest/sachet).
            $startUnitId = $product->default_unit_id ?: $qtyUnitId;
            $startQty = $product->convertQuantity($qtyBase, $qtyUnitId, $startUnitId);
            $startQtyInt = max(1, (int) round((float) ($startQty ?? $qtyBase)));

            $expectedBreakdown = $product->getBarcodeQuantityBreakdown($startQtyInt, $startUnitId, false);
            $actualByUnit = $summaryRows->keyBy('unit_id');

            foreach ($expectedBreakdown as $item) {
                $actual = $actualByUnit->get($item['unit_id']);
                $expectedQty = (int) $item['qty'];
                $actualQty = (int) ($actual->total ?? 0);
                $expectedBarcodeRows[] = (object) [
                    'unit_id' => $item['unit_id'],
                    'level' => $item['level'],
                    'unit_label' => strtoupper($item['label']),
                    'expected' => $expectedQty,
                    'actual' => $actualQty,
                    'variance' => $actualQty - $expectedQty,
                    'content_summary' => $item['content_summary'] ?? null,
                ];
            }

            // Include actual serial units that were not in expected breakdown (e.g. orphan sachet).
            foreach ($summaryRows as $actual) {
                $alreadyListed = collect($expectedBarcodeRows)->contains(
                    fn ($r) => $r->unit_id === $actual->unit_id
                );
                if ($alreadyListed) {
                    continue;
                }
                $expectedBarcodeRows[] = (object) [
                    'unit_id' => $actual->unit_id,
                    'level' => $actual->unit_level,
                    'unit_label' => $actual->unit_label,
                    'expected' => 0,
                    'actual' => $actual->total,
                    'variance' => $actual->total,
                    'content_summary' => null,
                ];
            }
        }

        return view('admin.production.barcodes', [
            'order' => $order,
            'summaryRows' => $summaryRows,
            'serials' => $serials,
            'filters' => [
                'unit_id' => $unitId,
                'status' => in_array($status, ['ready', 'dispatched'], true) ? $status : 'all',
                'per_page' => $perPage,
            ],
            'totalSerials' => (int) $summaryRows->sum('total'),
            'totalDispatched' => (int) $summaryRows->sum('dispatched'),
            'totalReady' => (int) $summaryRows->sum('ready'),
            'conversionChain' => $conversionChain,
            'qtyLevels' => $qtyLevels,
            'packagingRows' => $packagingRows,
            'expectedBarcodeRows' => $expectedBarcodeRows,
            'qtyBase' => $qtyBase,
            'qtyUnitLabel' => $order->outputUnit?->symbol ?: ($order->outputUnit?->name ?: ''),
        ]);
    }

    /**
     * @return array<int, array{description: string, amount: float}>
     */
    private function validateOverheadPayload(Request $request): array
    {
        $items = collect($request->input('overheads', []))
            ->filter(function ($row) {
                $description = trim((string) ($row['description'] ?? ''));
                $amount = (float) ($row['amount'] ?? 0);

                return $description !== '' || $amount > 0;
            })
            ->map(function ($row) {
                return [
                    'description' => trim((string) ($row['description'] ?? '')),
                    'amount' => (float) ($row['amount'] ?? 0),
                ];
            })
            ->values()
            ->all();

        foreach ($items as $index => $item) {
            if ($item['description'] === '') {
                throw ValidationException::withMessages([
                    "overheads.{$index}.description" => 'Description is required.',
                ]);
            }
        }

        validator(
            ['overheads' => $items],
            [
                'overheads' => ['array'],
                'overheads.*.description' => ['required', 'string', 'max:255'],
                'overheads.*.amount' => ['required', 'numeric', 'min:0'],
            ]
        )->validate();

        return $items;
    }

    /**
     * @param  array<int, array{description: string, amount: float}>  $items
     */
    private function sumOverheadAmounts(array $items): float
    {
        return round(collect($items)->sum('amount'), 4);
    }

    /**
     * @param  array<int, array{description: string, amount: float}>  $items
     */
    private function syncOverheads(ProductionOrder $order, array $items): void
    {
        $order->overheads()->delete();

        foreach ($items as $sortOrder => $item) {
            $order->overheads()->create([
                'description' => $item['description'],
                'amount' => $item['amount'],
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
