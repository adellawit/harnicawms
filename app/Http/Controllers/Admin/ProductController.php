<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProductExport;
use App\Http\Controllers\Controller;
use App\Imports\ProductImport;
use App\Models\AttributeDefinition;
use App\Models\BillOfMaterial;
use App\Models\ParameterDetail;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductNature;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\ProductUnitConversion;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttribute;
use App\Models\ProductVariantPrice;
use App\Services\Product\ProductLabelSerialService;
use App\Services\Product\ProductQrCodeService;
use App\Services\Product\ProductStockBootstrapService;
use App\Support\WmsContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;

class ProductController extends Controller
{
    private const BARCODE_SINGLE_MAX_LABELS = 500;

    private const BARCODE_HIERARCHY_MAX_LABELS = 5000;

    protected function generateSku(): string
    {
        // Format: DDMMYYYY + 7-digit sequence
        $prefix = date('dmY');
        $last = Product::withTrashed()
            ->where('sku', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(sku) DESC, sku DESC')
            ->value('sku');
        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 7, '0', STR_PAD_LEFT);
    }

    protected function generateCode(): string
    {
        // Format: C-MMYYYY-XXXXX (5-digit sequence)
        $prefix = 'C-'.date('m-Y');
        $last = Product::withTrashed()
            ->where('code', 'like', $prefix.'%')
            ->orderBy('code', 'desc')
            ->value('code');
        if ($last) {
            $seq = (int) substr($last, strrpos($last, '-') + 1) + 1;
        } else {
            $seq = 1;
        }

        return $prefix.'-'.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    protected function productParameterOptions(string $code)
    {
        return ParameterDetail::query()
            ->whereHas('parameter', fn ($q) => $q->where('code', $code))
            ->whereNull('deleted_at')
            ->orderBy('value')
            ->get(['id', 'key', 'value', 'description']);
    }

    protected function defaultProductParameterId(string $code, string $key): ?string
    {
        return ParameterDetail::query()
            ->whereHas('parameter', fn ($q) => $q->where('code', $code))
            ->where('key', $key)
            ->whereNull('deleted_at')
            ->value('id');
    }

    protected function resolveIsStockItem(?string $productNatureId, ?bool $requested = null): bool
    {
        if ($productNatureId) {
            $key = ParameterDetail::query()->where('id', $productNatureId)->value('key');
            if ($key === 'non_inventory') {
                return false;
            }
        }

        return $requested ?? true;
    }

    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $branchId = $request->get('branch_id', auth('web')->user()->current_business_unit_id);
        $isFilter = $status !== ''
            || $request->filled('sku')
            || $request->filled('product')
            || $request->filled('nature_id')
            || $request->filled('category_id')
            || $request->filled('item_type_id')
            || $request->filled('product_nature_id')
            || $request->filled('procurement_type_id')
            || $request->filled('is_stock_item')
            || $request->filled('is_sale_item')
            || $request->filled('is_purchase_item')
            || $branchId !== auth('web')->user()->current_business_unit_id;
        $natures = ProductNature::whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name', 'id');
        $categories = ProductCategory::whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name', 'id');
        $itemTypes = $this->productParameterOptions('ITEM_TYPE')->pluck('value', 'id');
        $productNatures = $this->productParameterOptions('PRODUCT_NATURE')->pluck('value', 'id');
        $procurementTypes = $this->productParameterOptions('PROCUREMENT_TYPE')->pluck('value', 'id');

        return view('admin.product.master.index', compact(
            'status',
            'isFilter',
            'natures',
            'categories',
            'itemTypes',
            'productNatures',
            'procurementTypes',
            'branchId'
        ));
    }

    public function indexData(Request $request)
    {
        try {
            $user = auth('web')->user();
            if (! $user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            $branchId = $request->get('branch_id', $user->current_business_unit_id);

            $data = Product::select(
            'products.id',
            'products.nature_id',
            'products.category_id',
            'products.item_type_id',
            'products.product_nature_id',
            'products.procurement_type_id',
            'products.default_unit_id',
            'products.name',
            'products.code',
            'products.sku',
            'products.is_stock_item',
            'products.is_sale_item',
            'products.is_purchase_item',
            'products.created_at',
            'products.deleted_at',
            'products.branch_id'
        )
            ->with([
                'nature:id,name',
                'category:id,name',
                'itemType:id,value,key',
                'productNature:id,value,key',
                'procurementType:id,value,key',
                'defaultUnit:id,name,symbol',
                'unitConversions' => fn ($q) => $q->whereNull('deleted_at'),
                'prices' => fn ($q) => $branchId ? $q->where('branch_id', $branchId) : $q,
                'variants' => fn ($q) => $q->whereNull('deleted_at')
                    ->with(['variantAttributes.attributeValue:id,value']),
            ]);

        if ($branchId) {
            $data = $data->where('branch_id', $branchId);
        }

        if ($request->filled('sku') && $request->sku !== '') {
            $data = $data->where(function ($q) use ($request) {
                $q->where('sku', 'ilike', '%'.$request->sku.'%')
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'ilike', '%'.$request->sku.'%'));
            });
        }

        if ($request->filled('product') && $request->product !== '') {
            $data = $data->where(function ($q) use ($request) {
                $q->where('name', 'ilike', '%'.$request->product.'%')
                    ->orWhere('code', 'ilike', '%'.$request->product.'%');
            });
        }

        if ($request->filled('nature_id') && $request->nature_id !== '') {
            $data = $data->where('nature_id', $request->nature_id);
        }

        if ($request->filled('category_id') && $request->category_id !== '') {
            $data = $data->where('category_id', $request->category_id);
        }

        if ($request->filled('item_type_id') && $request->item_type_id !== '') {
            $data = $data->where('item_type_id', $request->item_type_id);
        }

        if ($request->filled('product_nature_id') && $request->product_nature_id !== '') {
            $data = $data->where('product_nature_id', $request->product_nature_id);
        }

        if ($request->filled('procurement_type_id') && $request->procurement_type_id !== '') {
            $data = $data->where('procurement_type_id', $request->procurement_type_id);
        }

        foreach (['is_stock_item', 'is_sale_item', 'is_purchase_item'] as $flag) {
            if ($request->filled($flag) && $request->{$flag} !== '') {
                $data = $data->where($flag, (bool) $request->{$flag});
            }
        }

        if ($request->filled('status') && $request->status !== '') {
            if ($request->status === 'deleted') {
                $data = $data->onlyTrashed();
            } elseif ($request->status === 'active') {
                $data = $data->withoutTrashed();
            } else {
                $data = $data->withTrashed();
            }
        }

        $data = $data->orderBy('name', 'ASC');

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->addColumn('has_variants', fn ($row) => $row->variants->count() > 0)
            ->addColumn('variant_skus', fn ($row) => $row->variants->pluck('sku')->filter()->join(', ') ?: '-')
            ->addColumn('variants_list', function ($row) {
                if ($row->variants->isEmpty()) {
                    return '-';
                }

                $badges = [];
                foreach ($row->variants as $variant) {
                    // Build attribute names (e.g., "Merah, XL")
                    $attrNames = $variant->variantAttributes
                        ->pluck('attributeValue.value')
                        ->filter()
                        ->join(', ');

                    $parts = array_filter([
                        $variant->sku,
                        $variant->barcode,
                        $attrNames,
                    ]);

                    $label = implode(' | ', $parts);
                    $badges[] = '<div class="mb-1">'.$label.'</div>';
                }

                return '<div class="variant-list">'.implode('', $badges).'</div>';
            })
            ->addColumn('nature_name', fn ($row) => $row->nature?->name ?? '-')
            ->addColumn('category_name', fn ($row) => $row->category?->name ?? '-')
            ->addColumn('item_type_name', fn ($row) => $row->itemType?->value ?? '-')
            ->addColumn('product_nature_name', fn ($row) => $row->productNature?->value ?? '-')
            ->addColumn('procurement_type_name', fn ($row) => $row->procurementType?->value ?? '-')
            ->addColumn('lifecycle_flags', function ($row) {
                $badges = [];
                $badges[] = $row->is_stock_item
                    ? '<span class="badge bg-label-primary me-1">Stock</span>'
                    : '<span class="badge bg-label-secondary me-1">Non Stock</span>';
                if ($row->is_sale_item) {
                    $badges[] = '<span class="badge bg-label-success me-1">Sales</span>';
                }
                if ($row->is_purchase_item) {
                    $badges[] = '<span class="badge bg-label-info me-1">Purchase</span>';
                }

                return implode('', $badges);
            })
            ->addColumn('purchase_price', function ($row) {
                $factor = $row->getFactorToSmallest();
                $smallUnitId = $row->getSmallestUnitId();
                $price = $row->prices->firstWhere('unit_id', $smallUnitId);
                if (! $price) {
                    return '-';
                }
                $bigPrice = (float) $price->purchase_price * $factor;
                $unitLabel = $row->defaultUnit ? ($row->defaultUnit->symbol ?: $row->defaultUnit->name) : '';

                return format_number($bigPrice, 2, true).' <small class="text-muted">/ '.$unitLabel.'</small>';
            })
            ->filter(function ($query) use ($request) {
                $search = data_get($request->all(), 'search.value');
                if ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%")
                            ->orWhere('sku', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->rawColumns(['variants_list', 'nature_name', 'unit_name', 'purchase_price', 'lifecycle_flags'])
            ->make(true);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => config('app.debug') ? $e->getMessage() : 'Gagal memuat data produk.',
            ], 500);
        }
    }

    public function printBarcodeView(Request $request, string $id, ProductLabelSerialService $serialService)
    {
        $product = Product::with([
            'defaultUnit',
            'unitConversions.fromUnit',
            'unitConversions.toUnit',
            'variants' => fn ($q) => $q->whereNull('deleted_at')
                ->with(['variantAttributes.attributeValue:id,value'])
                ->orderBy('sort_order')
                ->orderBy('sku'),
        ])->findOrFail($id);

        $variants = $product->variants->map(function (ProductVariant $variant) use ($product) {
            $attrs = $variant->variantAttributes
                ->pluck('attributeValue.value')
                ->filter()
                ->implode(' / ');

            return [
                'id' => $variant->id,
                'label' => $attrs !== '' ? $attrs : ($variant->sku ?: 'Default'),
            ];
        })->values();

        $prefillVariantId = $request->query('variant_id');
        $prefillUnitId = $request->query('unit_id');
        $prefillQuantity = $request->query('quantity');

        $units = $product->getBarcodeUnits()->values()->map(function (ProductUnit $unit, int $index) use ($product, $serialService) {
            $level = $index + 1;

            return [
                'id' => $unit->id,
                'label' => $unit->symbol ?: $unit->name,
                'name' => $unit->name,
                'level' => $level,
                'format_example' => $serialService->formatExample($level),
                'conversion_hint' => $product->getBarcodeUnitConversionHint($unit->id),
                'content_summary' => $product->getBarcodeUnitLabelContent($unit->id),
                'child_labels_hint' => $product->getChildLabelsPerParent($unit->id),
            ];
        })->values();

        $unitChain = $product->getBarcodeUnitChain()->map(fn (array $item) => [
            'unit_id' => $item['unit']->id,
            'level' => $item['level'],
            'label' => $item['unit']->symbol ?: $item['unit']->name,
            'factor_to_next' => $item['factor_to_next'],
        ])->values();

        $distributor = WmsContext::distributor();
        $distributorName = strtoupper($distributor?->legal_name ?: $distributor?->name ?: config('app.name'));

        $labelsPerParent = $product->getBarcodeHierarchyTotalLabels(1, $product->default_unit_id);
        $maxHierarchyParentQty = $product->getMaxHierarchyParentQty(
            self::BARCODE_HIERARCHY_MAX_LABELS,
            $product->default_unit_id
        );

        $serialStatus = $serialService->serialStatusForProduct($product);

        return view('admin.product.master.print-barcode', [
            'product' => $product,
            'variants' => $variants,
            'units' => $units,
            'unitChain' => $unitChain,
            'defaultUnitId' => ($prefillUnitId && $units->contains(fn (array $u) => $u['id'] === $prefillUnitId))
                ? $prefillUnitId
                : $product->default_unit_id,
            'prefillVariantId' => $prefillVariantId,
            'prefillQuantity' => $prefillQuantity,
            'distributorName' => $distributorName,
            'hasUnitHierarchy' => $units->count() > 1,
            'singlePrintMaxLabels' => self::BARCODE_SINGLE_MAX_LABELS,
            'hierarchyPrintMaxLabels' => self::BARCODE_HIERARCHY_MAX_LABELS,
            'labelsPerParent' => $labelsPerParent,
            'maxHierarchyParentQty' => $maxHierarchyParentQty,
            'serialStatus' => $serialStatus,
        ]);
    }

    public function printBarcodeResetSerials(Request $request, string $id, ProductLabelSerialService $serialService)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'variant_id' => 'nullable|uuid|exists:product.product_variants,id',
        ]);

        $variantId = $request->variant_id ?: null;
        if ($variantId && ! $product->variants()->where('id', $variantId)->exists()) {
            abort(422, 'Variant tidak valid untuk produk ini.');
        }

        $deleted = $serialService->resetSerialsForProduct($product->id, $variantId);

        return response()->json([
            'message' => $deleted > 0
                ? "Berhasil reset {$deleted} nomor barcode. Cetak berikutnya akan mulai dari urutan 1 per level."
                : 'Tidak ada nomor barcode yang perlu direset untuk produk ini.',
            'deleted' => $deleted,
            'serial_status' => $serialService->serialStatusForProduct($product),
        ]);
    }

    public function printBarcodePreview(Request $request, string $id, ProductLabelSerialService $serialService, ProductQrCodeService $qrCodeService)
    {
        $validated = $this->validatePrintBarcodeRequest($request, $id);

        $product = $validated['product'];
        $unit = $validated['unit'];
        $unitLevel = $validated['unit_level'];
        $printMode = $validated['print_mode'];
        $includeSmallestUnit = $validated['include_smallest_unit'];
        $quantity = (int) $request->quantity;
        $variantId = $request->variant_id ?: null;
        $previewLimit = 24;

        if ($printMode === 'hierarchy') {
            $breakdown = $product->getBarcodeQuantityBreakdown($quantity, $unit->id, $includeSmallestUnit);
            $breakdownMeta = [];
            $serialsByUnitId = [];
            $totalLabels = 0;

            foreach ($breakdown as $item) {
                $levelUnit = $product->getBarcodeUnits()->firstWhere('id', $item['unit_id']);
                if (! $levelUnit) {
                    continue;
                }

                $itemQty = (int) $item['qty'];
                $totalLabels += $itemQty;
                $serials = $serialService->peekNextSerials($itemQty, $product->id, $levelUnit->id, $item['level']);
                $serialsByUnitId[$item['unit_id']] = $serials;

                $breakdownMeta[$item['unit_id']] = [
                    'unit_id' => $item['unit_id'],
                    'level' => $item['level'],
                    'label' => $item['label'],
                    'qty' => $itemQty,
                    'content_summary' => $item['content_summary'],
                    'serial_from' => $serials[0] ?? null,
                    'serial_to' => $serials[$itemQty - 1] ?? null,
                ];
            }

            $treeResult = $this->buildBarcodeHierarchyTree(
                $product,
                $breakdown,
                $serialsByUnitId,
                function (string $serial, array $item) use ($product, $qrCodeService) {
                    $levelUnit = $product->getBarcodeUnits()->firstWhere('id', $item['unit_id']);

                    return $this->mapBarcodeLabel(
                        $serial,
                        $product,
                        $levelUnit,
                        $qrCodeService,
                        $item['level'],
                        $item['content_summary']
                    );
                },
                $previewLimit
            );
            $treeResult['hidden'] = max(0, $totalLabels - $treeResult['displayed']);

            $batchId = (string) Str::uuid();
            session()->put("barcode_preview.{$batchId}", [
                'print_mode' => 'hierarchy',
                'product_id' => $product->id,
                'variant_id' => $variantId,
                'parent_unit_id' => $unit->id,
                'parent_quantity' => $quantity,
                'include_smallest_unit' => $includeSmallestUnit,
                'breakdown' => $breakdownMeta,
                'total_labels' => $totalLabels,
                'user_id' => auth('web')->id(),
                'created_at' => now()->timestamp,
            ]);

            return response()->json([
                'batch_id' => $batchId,
                'print_mode' => 'hierarchy',
                'total' => $totalLabels,
                'displayed' => $treeResult['displayed'],
                'hidden' => $treeResult['hidden'],
                'parent_quantity' => $quantity,
                'breakdown' => $breakdownMeta,
                'tree' => $treeResult['tree'],
                'distributor_name' => $validated['distributor_name'],
                'unit_label' => strtoupper($unit->symbol ?: $unit->name),
                'unit_level' => $unitLevel,
            ]);
        }

        $serials = $serialService->peekNextSerials($quantity, $product->id, $unit->id, $unitLevel);
        $displaySerials = array_slice($serials, 0, $previewLimit);
        $contentSummary = $product->getBarcodeUnitLabelContent($unit->id);

        $labels = collect($displaySerials)->map(function (string $serial) use ($product, $unit, $qrCodeService, $unitLevel, $contentSummary) {
            return $this->mapBarcodeLabel($serial, $product, $unit, $qrCodeService, $unitLevel, $contentSummary);
        })->values();

        $batchId = (string) Str::uuid();
        session()->put("barcode_preview.{$batchId}", [
            'print_mode' => 'single',
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'serial_from' => $serials[0] ?? null,
            'serial_to' => $serials[$quantity - 1] ?? null,
            'user_id' => auth('web')->id(),
            'created_at' => now()->timestamp,
        ]);

        return response()->json([
            'batch_id' => $batchId,
            'print_mode' => 'single',
            'total' => $quantity,
            'displayed' => count($displaySerials),
            'serial_from' => $serials[0] ?? null,
            'serial_to' => $serials[$quantity - 1] ?? null,
            'distributor_name' => $validated['distributor_name'],
            'unit_label' => strtoupper($unit->symbol ?: $unit->name),
            'unit_level' => $unitLevel,
            'content_summary' => $contentSummary,
            'format_hint' => $serialService->formatExample($unitLevel),
            'labels' => $labels,
        ]);
    }

    public function printBarcodePdf(Request $request, string $id, ProductLabelSerialService $serialService, ProductQrCodeService $qrCodeService)
    {
        $request->validate([
            'batch_id' => 'required|uuid',
        ]);

        $validated = $this->validatePrintBarcodeRequest($request, $id);

        $product = $validated['product'];
        $unit = $validated['unit'];
        $variantId = $request->variant_id ?: null;
        $unitId = $unit->id;
        $batchId = $request->batch_id;
        $batch = session("barcode_preview.{$batchId}");
        $printMode = $validated['print_mode'];
        $includeSmallestUnit = $validated['include_smallest_unit'];

        if (! $this->isBarcodePreviewBatchValid($batch, $product->id, $variantId, $printMode, $unitId, (int) $request->quantity, $includeSmallestUnit)) {
            return redirect()
                ->route('product.print-barcode.view', $product->id)
                ->with('error', 'Preview kedaluwarsa atau tidak valid. Silakan preview ulang sebelum generate PDF.');
        }

        $tempDir = storage_path('app/temp/barcode_qr');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $this->cleanupOldBarcodeTempFiles($tempDir);

        ini_set('memory_limit', '512M');

        $labels = [];
        $labelTree = null;
        $tempFiles = [];

        try {
            if ($printMode === 'hierarchy') {
                $labelTree = $this->allocateHierarchyBarcodeLabelsForPdf(
                    $product,
                    $unit,
                    (int) $request->quantity,
                    $batch['breakdown'] ?? [],
                    $variantId,
                    $serialService,
                    $qrCodeService,
                    $tempDir,
                    $includeSmallestUnit
                );
                $labels = $this->flattenBarcodeHierarchyTree($labelTree);
            } else {
                $serials = $serialService->allocateSerials(
                    (int) $request->quantity,
                    $product->id,
                    $unitId,
                    $validated['unit_level'],
                    $variantId,
                    auth('web')->id()
                );

                if (($batch['serial_from'] ?? null) !== ($serials[0] ?? null)) {
                    session()->forget("barcode_preview.{$batchId}");

                    return redirect()
                        ->route('product.print-barcode.view', $product->id)
                        ->with('error', 'Nomor seri berubah karena ada cetak lain. Silakan preview ulang.');
                }

                $contentSummary = $product->getBarcodeUnitLabelContent($unit->id);
                $labels = $this->buildBarcodeLabelsForPdf(
                    $serials,
                    $product,
                    $unit,
                    $qrCodeService,
                    $validated['unit_level'],
                    $tempDir,
                    $contentSummary
                );
            }

            $tempFiles = array_column($labels, 'qr_file');
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            session()->forget("barcode_preview.{$batchId}");

            return redirect()
                ->route('product.print-barcode.view', $product->id)
                ->with('error', 'Nomor barcode bentrok dengan data lama. Silakan preview ulang.');
        } catch (\RuntimeException) {
            session()->forget("barcode_preview.{$batchId}");

            return redirect()
                ->route('product.print-barcode.view', $product->id)
                ->with('error', 'Nomor seri berubah karena ada cetak lain. Silakan preview ulang.');
        }

        session()->forget("barcode_preview.{$batchId}");

        $filename = 'barcode-'.preg_replace('/[^A-Za-z0-9\-_]/', '_', $product->code).'-'.date('YmdHis').'.pdf';
        $qrBaseUrl = 'file://'.str_replace('\\', '/', $tempDir).'/';

        try {
            return Pdf::loadView('admin.product.master.pdf-barcode', [
                'labels' => $labels,
                'labelTree' => $labelTree,
                'printMode' => $printMode,
                'distributorName' => $validated['distributor_name'],
                'productName' => $product->name,
                'qrBaseUrl' => $qrBaseUrl,
            ])
                ->setPaper('a3', 'portrait')
                ->download($filename);
        } finally {
            foreach ($tempFiles as $file) {
                @unlink($tempDir.DIRECTORY_SEPARATOR.$file);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function allocateHierarchyBarcodeLabelsForPdf(
        Product $product,
        ProductUnit $parentUnit,
        int $parentQuantity,
        array $breakdownPreview,
        ?string $variantId,
        ProductLabelSerialService $serialService,
        ProductQrCodeService $qrCodeService,
        string $tempDir,
        bool $includeSmallestUnit = true
    ): array {
        $breakdown = $product->getBarcodeQuantityBreakdown($parentQuantity, $parentUnit->id, $includeSmallestUnit);
        $serialsByUnitId = [];

        foreach ($breakdown as $item) {
            $levelUnit = $product->getBarcodeUnits()->firstWhere('id', $item['unit_id']);
            if (! $levelUnit) {
                continue;
            }

            $serials = $serialService->allocateSerials(
                (int) $item['qty'],
                $product->id,
                $levelUnit->id,
                $item['level'],
                $variantId,
                auth('web')->id()
            );

            $expectedFrom = $breakdownPreview[$item['unit_id']]['serial_from'] ?? null;
            if ($expectedFrom !== null && $expectedFrom !== ($serials[0] ?? null)) {
                throw new \RuntimeException('Serial mismatch during hierarchy print.');
            }

            $serialsByUnitId[$item['unit_id']] = $serials;
        }

        $treeResult = $this->buildBarcodeHierarchyTree(
            $product,
            $breakdown,
            $serialsByUnitId,
            function (string $serial, array $item) use ($product, $qrCodeService, $tempDir) {
                $levelUnit = $product->getBarcodeUnits()->firstWhere('id', $item['unit_id']);

                return $this->mapBarcodeLabelForPdf(
                    $serial,
                    $product,
                    $levelUnit,
                    $qrCodeService,
                    $item['level'],
                    $tempDir,
                    $item['content_summary']
                );
            }
        );

        return $treeResult['tree'];
    }

    protected function isBarcodePreviewBatchValid(
        ?array $batch,
        string $productId,
        ?string $variantId,
        string $printMode,
        string $unitId,
        int $quantity,
        bool $includeSmallestUnit = true
    ): bool {
        if (! $batch
            || ($batch['product_id'] ?? null) !== $productId
            || ($batch['user_id'] ?? null) !== auth('web')->id()
            || ($batch['print_mode'] ?? 'single') !== $printMode
            || ($batch['variant_id'] ?? null) !== $variantId
            || (now()->timestamp - ($batch['created_at'] ?? 0)) > 1800
        ) {
            return false;
        }

        if ($printMode === 'hierarchy') {
            return ($batch['parent_unit_id'] ?? null) === $unitId
                && (int) ($batch['parent_quantity'] ?? 0) === $quantity
                && ($batch['include_smallest_unit'] ?? true) === $includeSmallestUnit;
        }

        return ($batch['unit_id'] ?? null) === $unitId
            && (int) ($batch['quantity'] ?? 0) === $quantity;
    }

    protected function cleanupOldBarcodeTempFiles(string $tempDir): void
    {
        if (! is_dir($tempDir)) {
            return;
        }

        $maxAge = 3600;
        $now = time();

        $files = glob($tempDir.DIRECTORY_SEPARATOR.'qr_*.png');
        if ($files === false) {
            return;
        }

        foreach ($files as $path) {
            if (is_file($path) && ($now - filemtime($path)) > $maxAge) {
                @unlink($path);
            }
        }
    }

    /**
     * @param  array<int, array{unit_id: string, level: int, qty: int, serial_from?: string|null}>  $breakdownPreview
     * @return array<int, array<string, mixed>>
     */
    protected function allocateHierarchyBarcodeLabels(
        Product $product,
        ProductUnit $parentUnit,
        int $parentQuantity,
        array $breakdownPreview,
        ?string $variantId,
        ProductLabelSerialService $serialService,
        ProductQrCodeService $qrCodeService
    ): array {
        $breakdown = $product->getBarcodeQuantityBreakdown($parentQuantity, $parentUnit->id);
        $labels = [];

        foreach ($breakdown as $index => $item) {
            $levelUnit = $product->getBarcodeUnits()->firstWhere('id', $item['unit_id']);
            if (! $levelUnit) {
                continue;
            }

            $serials = $serialService->allocateSerials(
                (int) $item['qty'],
                $product->id,
                $levelUnit->id,
                $item['level'],
                $variantId,
                auth('web')->id()
            );

            $expectedFrom = $breakdownPreview[$item['unit_id']]['serial_from'] ?? null;
            if ($expectedFrom !== null && $expectedFrom !== ($serials[0] ?? null)) {
                throw new \RuntimeException('Serial mismatch during hierarchy print.');
            }

            $levelLabels = $this->buildBarcodeLabels(
                $serials,
                $product,
                $levelUnit,
                $qrCodeService,
                $item['level'],
                $item['content_summary']
            );

            $labels = array_merge($labels, $levelLabels);
        }

        return $labels;
    }

    protected function buildBarcodeLabelsForPdf(
        array $serials,
        Product $product,
        ProductUnit $unit,
        ProductQrCodeService $qrCodeService,
        int $unitLevel,
        string $tempDir,
        ?string $contentSummary = null
    ): array {
        return collect($serials)->map(function (string $serial) use ($product, $unit, $qrCodeService, $unitLevel, $contentSummary, $tempDir) {
            return $this->mapBarcodeLabelForPdf($serial, $product, $unit, $qrCodeService, $unitLevel, $tempDir, $contentSummary);
        })->all();
    }

    protected function mapBarcodeLabelForPdf(
        string $serial,
        Product $product,
        ProductUnit $unit,
        ProductQrCodeService $qrCodeService,
        int $unitLevel,
        string $tempDir,
        ?string $contentSummary = null
    ): array {
        $labelType = match ($unitLevel) {
            1 => 'karton',
            2 => 'pack',
            default => 'box',
        };

        $qrFile = $qrCodeService->toPngTempFile(
            $qrCodeService->contentForLabel($serial, $unitLevel),
            $tempDir
        );

        return [
            'serial' => $serial,
            'product_name' => $product->name,
            'unit_label' => strtoupper($unit->symbol ?: $unit->name),
            'unit_level' => $unitLevel,
            'label_type' => $labelType,
            'content_summary' => $contentSummary,
            'qr_file' => $qrFile,
        ];
    }

    protected function validatePrintBarcodeRequest(Request $request, string $id): array
    {
        $printMode = $request->input('print_mode', 'hierarchy');
        $maxQty = $printMode === 'hierarchy'
            ? self::BARCODE_HIERARCHY_MAX_LABELS
            : self::BARCODE_SINGLE_MAX_LABELS;

        $request->validate([
            'quantity' => "required|integer|min:1|max:{$maxQty}",
            'variant_id' => 'nullable|uuid|exists:product.product_variants,id',
            'unit_id' => 'required|uuid|exists:product.product_units,id',
            'print_mode' => 'nullable|in:single,hierarchy',
        ]);

        $includeSmallestUnit = $request->boolean('include_smallest_unit', true);

        $product = Product::with([
            'defaultUnit',
            'unitConversions.fromUnit',
            'unitConversions.toUnit',
            'variants' => fn ($q) => $q->whereNull('deleted_at'),
        ])->findOrFail($id);

        $variantId = $request->variant_id;
        if ($variantId && ! $product->variants->contains('id', $variantId)) {
            abort(422, 'Variant tidak valid untuk produk ini.');
        }

        if (! $product->hasBarcodeUnit($request->unit_id)) {
            abort(422, 'Satuan tidak valid untuk produk ini.');
        }

        $unit = $product->getBarcodeUnits()->firstWhere('id', $request->unit_id);
        if (! $unit) {
            abort(422, 'Satuan tidak ditemukan.');
        }

        if ($printMode === 'hierarchy') {
            if ($product->getBarcodeUnits()->count() < 2) {
                abort(422, 'Mode hierarki membutuhkan minimal 2 level satuan.');
            }

            $totalLabels = $product->getBarcodeHierarchyTotalLabels((int) $request->quantity, $unit->id, $includeSmallestUnit);
            $maxTotal = self::BARCODE_HIERARCHY_MAX_LABELS;
            if ($totalLabels > $maxTotal) {
                $maxParentQty = $product->getMaxHierarchyParentQty($maxTotal, $unit->id, $includeSmallestUnit);
                abort(422, "Total label ({$totalLabels}) melebihi batas {$maxTotal}. Maks. qty satuan terbesar: {$maxParentQty}.");
            }
        } elseif ((int) $request->quantity > self::BARCODE_SINGLE_MAX_LABELS) {
            abort(422, 'Maksimal '.self::BARCODE_SINGLE_MAX_LABELS.' label per cetak (mode satuan tunggal).');
        }

        $distributor = WmsContext::distributor();
        $distributorName = strtoupper($distributor?->legal_name ?: $distributor?->name ?: config('app.name'));

        return [
            'product' => $product,
            'unit' => $unit,
            'unit_level' => $product->getBarcodeUnitLevel($unit->id),
            'print_mode' => $printMode,
            'distributor_name' => $distributorName,
            'include_smallest_unit' => $includeSmallestUnit,
        ];
    }

    /**
     * @param  array<int, string>  $serials
     * @return array<int, array<string, mixed>>
     */
    protected function buildBarcodeLabels(
        array $serials,
        Product $product,
        ProductUnit $unit,
        ProductQrCodeService $qrCodeService,
        int $unitLevel,
        ?string $contentSummary = null
    ): array {
        return collect($serials)->map(function (string $serial) use ($product, $unit, $qrCodeService, $unitLevel, $contentSummary) {
            return $this->mapBarcodeLabel($serial, $product, $unit, $qrCodeService, $unitLevel, $contentSummary);
        })->all();
    }

    /**
     * Bangun pohon hierarki label (Karton → Pack → Box) sesuai urutan nomor seri per level.
     *
     * @param  array<int, array{unit_id: string, level: int, qty: int, label: string, content_summary: string|null}>  $breakdown
     * @param  array<string, array<int, string>>  $serialsByUnitId
     * @param  callable(string, array{unit_id: string, level: int, qty: int, label: string, content_summary: string|null}): array  $mapLabel
     * @return array{tree: array<int, array<string, mixed>>, displayed: int, hidden: int}
     */
    protected function buildBarcodeHierarchyTree(
        Product $product,
        array $breakdown,
        array $serialsByUnitId,
        callable $mapLabel,
        ?int $previewLimit = null
    ): array {
        if ($breakdown === []) {
            return ['tree' => [], 'displayed' => 0, 'hidden' => 0];
        }

        $childFactors = $this->getHierarchyChildFactors($breakdown);
        $serialIndexes = array_fill_keys(array_column($breakdown, 'unit_id'), 0);
        $displayed = 0;
        $limitReached = false;

        $buildNodes = function (int $levelIndex, int $ordinal) use (
            &$buildNodes,
            &$displayed,
            &$limitReached,
            $breakdown,
            $childFactors,
            &$serialIndexes,
            $serialsByUnitId,
            $mapLabel,
            $previewLimit
        ): array {
            $item = $breakdown[$levelIndex];
            $unitId = $item['unit_id'];
            $serialIndex = $serialIndexes[$unitId];
            $serial = $serialsByUnitId[$unitId][$serialIndex] ?? null;

            if ($serial === null) {
                return [];
            }

            $serialIndexes[$unitId] = $serialIndex + 1;

            $includeLabel = ! $limitReached;
            if ($previewLimit !== null && $displayed >= $previewLimit) {
                $limitReached = true;
                $includeLabel = false;
            }

            if ($includeLabel) {
                $displayed++;
            }

            $node = [
                'ordinal' => $ordinal,
                'level' => $item['level'],
                'unit_label' => $item['label'],
                'content_summary' => $item['content_summary'],
                'label' => $includeLabel ? $mapLabel($serial, $item) : null,
                'serial' => $serial,
                'children' => [],
                'hidden_children' => 0,
            ];

            if ($levelIndex < count($breakdown) - 1) {
                $childrenPerNode = $childFactors[$levelIndex] ?? 0;

                for ($childOrdinal = 1; $childOrdinal <= $childrenPerNode; $childOrdinal++) {
                    if ($limitReached && $previewLimit !== null) {
                        $node['hidden_children'] += $this->countHierarchySubtreeLabels($breakdown, $childFactors, $levelIndex + 1);
                        break;
                    }

                    $childNode = $buildNodes($levelIndex + 1, $childOrdinal);
                    if ($childNode !== []) {
                        $node['children'][] = $childNode;
                    }
                }

                if ($limitReached && $previewLimit !== null && $childrenPerNode > count($node['children'])) {
                    $remainingChildren = $childrenPerNode - count($node['children']);
                    for ($i = 0; $i < $remainingChildren; $i++) {
                        $node['hidden_children'] += $this->countHierarchySubtreeLabels($breakdown, $childFactors, $levelIndex + 1);
                    }
                }
            }

            return $node;
        };

        $tree = [];
        $rootQty = (int) $breakdown[0]['qty'];

        for ($ordinal = 1; $ordinal <= $rootQty; $ordinal++) {
            if ($limitReached && $previewLimit !== null) {
                break;
            }

            $node = $buildNodes(0, $ordinal);
            if ($node !== []) {
                $tree[] = $node;
            }
        }

        return [
            'tree' => $tree,
            'displayed' => $displayed,
            'hidden' => 0,
        ];
    }

    /**
     * @param  array<int, array{unit_id: string, level: int, qty: int, label: string, content_summary: string|null}>  $breakdown
     * @return array<int, int>
     */
    protected function getHierarchyChildFactors(array $breakdown): array
    {
        $factors = [];

        for ($i = 0; $i < count($breakdown) - 1; $i++) {
            $parentQty = (int) $breakdown[$i]['qty'];
            $childQty = (int) $breakdown[$i + 1]['qty'];
            $factors[$i] = $parentQty > 0 ? (int) round($childQty / $parentQty) : 0;
        }

        return $factors;
    }

    /**
     * @param  array<int, int>  $childFactors
     */
    protected function countHierarchySubtreeLabels(array $breakdown, array $childFactors, int $levelIndex): int
    {
        $count = 1;

        if ($levelIndex < count($breakdown) - 1) {
            $childrenPerNode = $childFactors[$levelIndex] ?? 0;
            $childSubtree = $this->countHierarchySubtreeLabels($breakdown, $childFactors, $levelIndex + 1);
            $count += $childrenPerNode * $childSubtree;
        }

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    protected function flattenBarcodeHierarchyTree(array $tree): array
    {
        $flat = [];

        foreach ($tree as $node) {
            if (! empty($node['label'])) {
                $flat[] = $node['label'];
            }

            if (! empty($node['children'])) {
                $flat = array_merge($flat, $this->flattenBarcodeHierarchyTree($node['children']));
            }
        }

        return $flat;
    }

    /**
     * @return array{serial: string, product_name: string, unit_label: string, unit_level: int, label_type: string, content_summary: string|null, qr_data_uri: string}
     */
    protected function mapBarcodeLabel(
        string $serial,
        Product $product,
        ProductUnit $unit,
        ProductQrCodeService $qrCodeService,
        int $unitLevel,
        ?string $contentSummary = null
    ): array {
        $labelType = match ($unitLevel) {
            1 => 'karton',
            2 => 'pack',
            default => 'box',
        };

        return [
            'serial' => $serial,
            'product_name' => $product->name,
            'unit_label' => strtoupper($unit->symbol ?: $unit->name),
            'unit_level' => $unitLevel,
            'label_type' => $labelType,
            'content_summary' => $contentSummary,
            'qr_data_uri' => $qrCodeService->toPngDataUri(
                $qrCodeService->contentForLabel($serial, $unitLevel)
            ),
        ];
    }

    // === STEP 1: Insert Product Basic Info ===
    public function generateCodeApi(Request $request)
    {
        return response()->json(['code' => $this->generateCode()]);
    }

    public function insertViewStep1(Request $request)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $natures = ProductNature::whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'name']);
        $categories = ProductCategory::whereNull('deleted_at')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->when($companyId, fn ($query) => $query->orWhere('company_id', $companyId));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        $units = ProductUnit::whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'symbol']);
        $itemTypes = $this->productParameterOptions('ITEM_TYPE');
        $productNatures = $this->productParameterOptions('PRODUCT_NATURE');
        $procurementTypes = $this->productParameterOptions('PROCUREMENT_TYPE');
        $defaultItemTypeId = $this->defaultProductParameterId('ITEM_TYPE', 'finished_good');
        $defaultProductNatureId = $this->defaultProductParameterId('PRODUCT_NATURE', 'inventory');
        $defaultProcurementTypeId = $this->defaultProductParameterId('PROCUREMENT_TYPE', 'purchase');

        // Check for temp data from previous attempts
        $tempProduct = session()->get('temp_product', []);

        // Generate auto code for new products
        $generatedCode = $this->generateCode();

        return view('admin.product.master.insert-step1', compact(
            'natures',
            'categories',
            'units',
            'itemTypes',
            'productNatures',
            'procurementTypes',
            'defaultItemTypeId',
            'defaultProductNatureId',
            'defaultProcurementTypeId',
            'tempProduct',
            'generatedCode'
        ));
    }

    public function insertDataStep1(Request $request)
    {
        $request->merge([
            'min_stock' => normalize_number_input($request->min_stock),
            'max_stock' => normalize_number_input($request->max_stock),
        ]);

        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $request->validate([
            'nature_id' => 'nullable|exists:product.product_natures,id',
            'category_id' => 'nullable|exists:product.product_categories,id',
            'item_type_id' => 'nullable|exists:public.parameter_details,id',
            'product_nature_id' => 'nullable|exists:public.parameter_details,id',
            'procurement_type_id' => 'nullable|exists:public.parameter_details,id',
            'default_unit_id' => 'required|exists:product.product_units,id',
            'name' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($branchId) {
                    $exists = Product::withTrashed()
                        ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->exists();
                    if ($exists) {
                        $fail('Nama product "'.$value.'" sudah ada.');
                    }
                },
            ],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('product.products', 'code')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'description' => 'nullable|string',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'has_variants' => 'nullable|boolean',
            'is_stock_item' => 'nullable|boolean',
            'is_sale_item' => 'nullable|boolean',
            'is_purchase_item' => 'nullable|boolean',
            'cogs_account_code' => 'nullable|string|max:50',
            'revenue_account_code' => 'nullable|string|max:50',
        ], [
            'code.unique' => 'Code already exists.',
        ]);

        // Store in session for next steps
        // Use generated code if not provided
        $code = $request->code ?: $this->generateCode();

        session()->put('temp_product', [
            'nature_id' => $request->nature_id ?: null,
            'category_id' => $request->category_id ?: null,
            'item_type_id' => $request->item_type_id ?: null,
            'product_nature_id' => $request->product_nature_id ?: null,
            'procurement_type_id' => $request->procurement_type_id ?: null,
            'default_unit_id' => $request->default_unit_id,
            'name' => $request->name,
            'code' => $code,
            'description' => $request->description,
            'min_stock' => $request->min_stock ?? 0,
            'max_stock' => $request->max_stock,
            'has_variants' => $request->boolean('has_variants'),
            'is_stock_item' => $this->resolveIsStockItem(
                $request->product_nature_id,
                $request->boolean('is_stock_item', true)
            ),
            'is_sale_item' => $request->boolean('is_sale_item'),
            'is_purchase_item' => $request->boolean('is_purchase_item', true),
            'cogs_account_code' => $request->cogs_account_code,
            'revenue_account_code' => $request->revenue_account_code,
            'purchase_price' => normalize_number_input($request->purchase_price),
            'selling_price' => normalize_number_input($request->selling_price),
        ]);

        return redirect()->route('product.insert.view.step2');
    }

    // === STEP 2: Unit Conversions ===
    public function insertViewStep2(Request $request)
    {
        $tempProduct = session('temp_product');
        if (! $tempProduct) {
            return redirect()->route('product.insert.view.step1')
                ->with('error', 'Please fill in product information first.');
        }

        $branchId = auth('web')->user()->current_business_unit_id;
        $units = ProductUnit::whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'symbol']);

        $conversions = session('temp_conversions', []);
        $currentFromUnitId = $this->resolveTempConversionFromUnitId(
            (string) $tempProduct['default_unit_id'],
            $conversions
        );

        $selectedUnit = ProductUnit::find($currentFromUnitId)
            ?? ProductUnit::find($tempProduct['default_unit_id']);

        if ($selectedUnit && ! $units->contains('id', $selectedUnit->id)) {
            $units->prepend($selectedUnit);
        }

        $defaultUnit = ProductUnit::find($tempProduct['default_unit_id']);
        $usedUnitIds = $this->getTempConversionUsedUnitIds(
            (string) $tempProduct['default_unit_id'],
            $conversions
        );

        return view('admin.product.master.insert-step2', compact(
            'tempProduct',
            'units',
            'selectedUnit',
            'defaultUnit',
            'conversions',
            'usedUnitIds'
        ));
    }

    public function insertDataStep2(Request $request)
    {
        $tempProduct = session('temp_product');

        // If adding more conversions, validate the conversion fields
        if ($request->has('add_more')) {
            $request->merge([
                'conversion_factor' => normalize_number_input($request->conversion_factor),
            ]);

            $request->validate([
                'from_unit_id' => 'required|exists:product.product_units,id',
                'to_unit_id' => 'required|exists:product.product_units,id|different:from_unit_id',
                'conversion_factor' => 'required|numeric|min:0.000001',
            ], [
                'conversion_factor.required' => 'Conversion factor is required.',
                'conversion_factor.min' => 'Conversion factor must be greater than 0.',
                'to_unit_id.different' => 'To Unit must be different from From Unit.',
            ]);

            $conversions = session()->get('temp_conversions', []);
            $expectedFromUnitId = $this->resolveTempConversionFromUnitId(
                (string) $tempProduct['default_unit_id'],
                $conversions
            );

            if ($request->from_unit_id !== $expectedFromUnitId) {
                return redirect()->route('product.insert.view.step2')
                    ->with('error', 'From Unit must follow the latest conversion chain.');
            }

            $usedUnitIds = $this->getTempConversionUsedUnitIds(
                (string) $tempProduct['default_unit_id'],
                $conversions
            );

            if (in_array($request->to_unit_id, $usedUnitIds, true)) {
                return redirect()->route('product.insert.view.step2')
                    ->withInput()
                    ->with('error', 'To Unit is already used in the conversion chain.');
            }

            // Store conversions in session
            $conversions[] = [
                'from_unit_id' => $request->from_unit_id,
                'to_unit_id' => $request->to_unit_id,
                'conversion_factor' => $request->conversion_factor,
                'conversion_level' => count($conversions) + 1,
            ];
            session()->put('temp_conversions', $conversions);

            return redirect()->route('product.insert.view.step2')
                ->with('success', 'Unit conversion added. Add more or continue.');
        }

        // Check if at least one conversion is added before proceeding
        $conversions = session()->get('temp_conversions', []);
        if (empty($conversions)) {
            return redirect()->route('product.insert.view.step2')
                ->with('error', 'Please add at least one unit conversion before proceeding.');
        }

        return redirect()->route('product.insert.view.step3');
    }

    // === STEP 3: Variants & Prices ===
    public function insertViewStep3(Request $request)
    {
        $tempProduct = session('temp_product');
        if (! $tempProduct) {
            return redirect()->route('product.insert.view.step1')
                ->with('error', 'Please fill in product information first.');
        }

        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $hasVariants = $tempProduct['has_variants'] ?? false;

        if ($hasVariants) {
            // Get attributes for variant selection
            $attributeDefinitions = AttributeDefinition::whereNull('deleted_at')
                ->with('attributeValues')
                ->orderBy('name')
                ->get();

            $tempVariants = session()->get('temp_variants', []);

            // Generate next SKU prefix for display
            $skuPrefix = date('dmy').'TH';
            $lastSku = Product::withTrashed()
                ->where('sku', 'like', $skuPrefix.'%')
                ->orderByRaw('LENGTH(sku) DESC, sku DESC')
                ->value('sku');
            $nextSkuNum = $lastSku ? (int) substr($lastSku, strlen($skuPrefix)) + 1 : 1;

            return view('admin.product.master.insert-step3-variants', compact(
                'tempProduct',
                'attributeDefinitions',
                'tempVariants',
                'branchId',
                'companyId',
                'skuPrefix',
                'nextSkuNum'
            ));
        } else {
            // No variants - just prices
            $units = ProductUnit::whereNull('deleted_at')
                ->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')
                        ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
                })
                ->orderBy('name')
                ->get(['id', 'name', 'symbol']);

            $conversions = session()->get('temp_conversions', []);
            $selectedUnit = ProductUnit::find($tempProduct['default_unit_id']);

            if ($selectedUnit && ! $units->contains('id', $selectedUnit->id)) {
                $units->prepend($selectedUnit);
            }

            return view('admin.product.master.insert-step3-prices', compact(
                'tempProduct',
                'units',
                'conversions',
                'selectedUnit',
                'branchId',
                'companyId'
            ));
        }
    }

    public function insertDataStep3(Request $request)
    {
        $tempProduct = session('temp_product');
        if (! $tempProduct) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Please fill in product information first.']);
            }

            return redirect()->route('product.insert.view.step1')
                ->with('error', 'Please fill in product information first.');
        }

        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();
        $hasVariants = (bool) ($tempProduct['has_variants'] ?? false);

        // Get variants from request body or session
        $variants = [];
        if ($hasVariants && $request->has('variants')) {
            $variantsData = $request->input('variants');
            if (is_string($variantsData)) {
                $variants = json_decode($variantsData, true) ?? [];
            } else {
                $variants = $variantsData;
            }
        } elseif ($hasVariants) {
            $variants = session()->get('temp_variants', []);
        }

        // Validate at least one variant only when this product uses variants.
        if ($hasVariants && empty($variants)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Please add at least one variant.']);
            }

            return redirect()->route('product.insert.view.step3')
                ->with('error', 'Please add at least one variant.');
        }

        DB::beginTransaction();
        try {
            $user = auth('web')->user();
            $itemTypeId = $tempProduct['item_type_id'] ?? $this->defaultProductParameterId('ITEM_TYPE', 'finished_good');
            $productNatureId = $tempProduct['product_nature_id'] ?? $this->defaultProductParameterId('PRODUCT_NATURE', 'inventory');
            $procurementTypeId = $tempProduct['procurement_type_id'] ?? $this->defaultProductParameterId('PROCUREMENT_TYPE', 'purchase');
            $isStockItem = $this->resolveIsStockItem(
                $productNatureId,
                array_key_exists('is_stock_item', $tempProduct) ? (bool) $tempProduct['is_stock_item'] : null
            );

            // Get the smallest unit for price calculation
            $smallestUnitId = null;
            $factor = 1;
            $conversions = session()->get('temp_conversions', []);
            if (! empty($conversions)) {
                $tempUnitId = $tempProduct['default_unit_id'] ?? null;
                if ($tempUnitId) {
                    $tempUnit = ProductUnit::find($tempUnitId);
                    if ($tempUnit) {
                        // For now, use the default unit as smallest (actual calculation would need conversion logic)
                        $smallestUnitId = $tempUnitId;
                    }
                }
            }

            // Create product
            $product = Product::create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'nature_id' => $tempProduct['nature_id'] ?? null,
                'category_id' => $tempProduct['category_id'] ?? null,
                'item_type_id' => $itemTypeId,
                'product_nature_id' => $productNatureId,
                'procurement_type_id' => $procurementTypeId,
                'default_unit_id' => $tempProduct['default_unit_id'],
                'name' => $tempProduct['name'],
                'code' => $tempProduct['code'] ?? null,
                'description' => $tempProduct['description'],
                'sku' => $this->generateSku(),
                'is_stock_item' => $isStockItem,
                'is_sale_item' => (bool) ($tempProduct['is_sale_item'] ?? false),
                'is_purchase_item' => (bool) ($tempProduct['is_purchase_item'] ?? true),
                'min_stock' => $tempProduct['min_stock'] ?? 0,
                'max_stock' => $tempProduct['max_stock'] ?? null,
                'cogs_account_code' => $tempProduct['cogs_account_code'] ?? null,
                'revenue_account_code' => $tempProduct['revenue_account_code'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create unit conversions
            foreach ($conversions as $index => $convData) {
                ProductUnitConversion::create([
                    'product_id' => $product->id,
                    'from_unit_id' => $convData['from_unit_id'],
                    'to_unit_id' => $convData['to_unit_id'],
                    'conversion_factor' => $convData['conversion_factor'],
                    'conversion_level' => $convData['conversion_level'] ?? ($index + 1),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            // Reload product to get conversions for price calculation
            $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
            $factor = $product->getFactorToSmallest();
            $smallestUnitId = $product->getSmallestUnitId();
            $defaultUnitId = $product->default_unit_id;

            if ($hasVariants) {
                // Create variants with prices
                foreach ($variants as $variantData) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $variantData['sku'] ?? null,
                        'barcode' => $variantData['barcode'] ?? null,
                        'weight' => ! empty($variantData['weight']) ? $variantData['weight'] : null,
                        'sort_order' => $variantData['sort_order'] ?? 0,
                        'is_active' => $variantData['is_active'] ?? true,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);

                    // Create variant attributes
                    if (! empty($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $attrData) {
                            ProductVariantAttribute::create([
                                'product_variant_id' => $variant->id,
                                'attribute_definition_id' => $attrData['attribute_definition_id'],
                                'attribute_value_id' => $attrData['attribute_value_id'],
                                'created_by' => $user->id,
                                'updated_by' => $user->id,
                            ]);
                        }
                    }

                    // Create variant prices - save at both default unit and smallest unit
                    $prices = $variantData['prices'] ?? [];

                    if (! empty($prices)) {
                        foreach ($prices as $priceListId => $priceData) {
                            $purchasePrice = isset($priceData['purchase_price']) ? normalize_number_input($priceData['purchase_price']) : 0;
                            $sellingPrice = isset($priceData['selling_price']) ? normalize_number_input($priceData['selling_price']) : null;

                            if ($purchasePrice > 0 || ($sellingPrice !== null && $sellingPrice > 0)) {
                                // Save price at default unit (From Unit / Satuan Besar)
                                if ($defaultUnitId) {
                                    ProductVariantPrice::create([
                                        'variant_id' => $variant->id,
                                        'company_id' => $companyId,
                                        'branch_id' => $branchId,
                                        'unit_id' => $defaultUnitId,
                                        'price_list_id' => $priceListId,
                                        'purchase_price' => $purchasePrice,
                                        'selling_price' => $sellingPrice,
                                        'created_by' => $user->id,
                                        'updated_by' => $user->id,
                                    ]);
                                }

                                // Also save price at smallest unit (To Unit / Satuan Kecil) if different
                                if ($smallestUnitId && $smallestUnitId != $defaultUnitId) {
                                    $smallPP = $purchasePrice > 0 ? $purchasePrice / $factor : 0;
                                    $smallSP = $sellingPrice !== null ? $sellingPrice / $factor : null;
                                    ProductVariantPrice::create([
                                        'variant_id' => $variant->id,
                                        'company_id' => $companyId,
                                        'branch_id' => $branchId,
                                        'unit_id' => $smallestUnitId,
                                        'price_list_id' => $priceListId,
                                        'purchase_price' => $smallPP,
                                        'selling_price' => $smallSP,
                                        'created_by' => $user->id,
                                        'updated_by' => $user->id,
                                    ]);
                                }
                            }
                        }
                    }
                }
            } else {
                // No variants - create default variant and prices
                if ($request->filled('purchase_price') || $request->filled('selling_price')) {
                    $tempProduct['purchase_price'] = normalize_number_input($request->purchase_price);
                    $tempProduct['selling_price'] = normalize_number_input($request->selling_price);
                }

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => null,
                    'barcode' => null,
                    'sort_order' => 1,
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                $purchasePrice = normalize_number_input($tempProduct['purchase_price'] ?? 0);
                $sellingPrice = normalize_number_input($tempProduct['selling_price'] ?? 0);

                if ($purchasePrice > 0 || ($sellingPrice !== null && $sellingPrice > 0)) {
                    // Save price at default unit (From Unit / Satuan Besar)
                    if ($defaultUnitId) {
                        ProductVariantPrice::create([
                            'variant_id' => $variant->id,
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'unit_id' => $defaultUnitId,
                            'purchase_price' => $purchasePrice,
                            'selling_price' => $sellingPrice,
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }

                    // Also save price at smallest unit (To Unit / Satuan Kecil) if different
                    if ($smallestUnitId && $smallestUnitId != $defaultUnitId) {
                        $smallPP = $purchasePrice > 0 ? $purchasePrice / $factor : 0;
                        $smallSP = $sellingPrice !== null ? $sellingPrice / $factor : null;
                        ProductVariantPrice::create([
                            'variant_id' => $variant->id,
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'unit_id' => $smallestUnitId,
                            'purchase_price' => $smallPP,
                            'selling_price' => $smallSP,
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }
                }
            }

            $product->load([
                'variants' => fn ($q) => $q->whereNull('deleted_at'),
                'itemType',
                'productNature',
                'nature',
                'unitConversions' => fn ($q) => $q->whereNull('deleted_at'),
            ]);
            app(ProductStockBootstrapService::class)->bootstrap($product, $user->id);

            DB::commit();

            // Clear session data
            session()->forget(['temp_product', 'temp_conversions', 'temp_variants']);

            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Product added successfully']);
            }

            return redirect()->route('product.index.view')->with('success', 'Product added successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to create product: '.$e->getMessage()]);
            }

            return redirect()->route('product.insert.view.step1')
                ->with('error', 'Failed to create product: '.$e->getMessage());
        }
    }

    // === EDIT View ===
    public function editView(Request $request, $id)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $product = Product::where('id', $id)->withTrashed()
            ->with(['unitConversions.fromUnit', 'unitConversions.toUnit', 'variants.prices'])
            ->firstOrFail();

        $natures = ProductNature::whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'name']);
        $categories = ProductCategory::whereNull('deleted_at')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->when($companyId, fn ($query) => $query->orWhere('company_id', $companyId));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
        $units = ProductUnit::whereNull('deleted_at')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id')
                    ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'symbol']);
        $itemTypes = $this->productParameterOptions('ITEM_TYPE');
        $productNatures = $this->productParameterOptions('PRODUCT_NATURE');
        $procurementTypes = $this->productParameterOptions('PROCUREMENT_TYPE');

        return view('admin.product.master.edit', compact(
            'product',
            'natures',
            'categories',
            'units',
            'itemTypes',
            'productNatures',
            'procurementTypes'
        ));
    }

    public function variantsView(Request $request, $productId)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $product = Product::where('id', $productId)
            ->with(['nature', 'defaultUnit', 'category'])
            ->with(['variants' => function ($q) {
                $q->whereNull('deleted_at')
                    ->with(['variantAttributes.attributeDefinition', 'variantAttributes.attributeValue'])
                    ->with(['prices' => function ($pq) {
                        $pq->whereNull('deleted_at')
                            ->with('priceList');
                    }]);
            }])
            ->firstOrFail();

        // Get all attribute definitions for variant management
        $attributeDefinitions = AttributeDefinition::whereNull('deleted_at')
            ->with('attributeValues')
            ->orderBy('name')
            ->get();

        // Load unit conversions for price display calculation
        $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
        $factor = $product->getFactorToSmallest();

        return view('admin.product.master.variants', compact(
            'product',
            'attributeDefinitions',
            'branchId',
            'companyId',
            'factor'
        ));
    }

    // === Variant CRUD Methods ===

    public function variantData(Request $request, $variantId)
    {
        $variant = ProductVariant::with([
            'variantAttributes.attributeDefinition',
            'variantAttributes.attributeValue',
            'prices',
        ])->findOrFail($variantId);

        return response()->json([
            'variant' => $variant,
            'attributes' => $variant->variantAttributes->map(function ($attr) {
                return [
                    'attribute_definition_id' => $attr->attribute_definition_id,
                    'attribute_value_id' => $attr->attribute_value_id,
                    'definition_name' => $attr->attributeDefinition?->name,
                    'value_name' => $attr->attributeValue?->value,
                ];
            }),
            'prices' => $variant->prices->keyBy('price_list_id')->map(function ($price) {
                return [
                    'purchase_price' => $price->purchase_price,
                    'selling_price' => $price->selling_price,
                ];
            }),
        ]);
    }

    public function storeVariant(Request $request, $productId)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $product = Product::findOrFail($productId);

        $request->validate([
            'barcode' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.attribute_definition_id' => 'required|exists:product.attribute_definitions,id',
            'attributes.*.attribute_value_id' => 'required|exists:product.attribute_values,id',
            'prices' => 'nullable|array',
            'prices.*.price_list_id' => 'required|exists:product.product_price_lists,id',
            'prices.*.purchase_price' => 'nullable|numeric|min:0',
            'prices.*.selling_price' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $user = auth('web')->user();

            // Get units for price calculation
            $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
            $smallUnitId = $product->getSmallestUnitId();
            $defaultUnitId = $product->default_unit_id;
            $factor = $product->getFactorToSmallest();

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'barcode' => $request->barcode,
                'weight' => $request->weight,
                'sort_order' => ProductVariant::where('product_id', $product->id)->max('sort_order') + 1,
                'is_active' => $request->is_active ?? true,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create variant attributes
            if ($request->filled('attributes')) {
                foreach ($request->input('attributes') as $attr) {
                    ProductVariantAttribute::create([
                        'product_variant_id' => $variant->id,
                        'attribute_definition_id' => $attr['attribute_definition_id'],
                        'attribute_value_id' => $attr['attribute_value_id'],
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            // Create variant prices
            if ($request->filled('prices')) {
                foreach ($request->prices as $priceData) {
                    if (isset($priceData['purchase_price']) || isset($priceData['selling_price'])) {
                        $pp = normalize_number_input($priceData['purchase_price'] ?? 0);
                        $sp = isset($priceData['selling_price']) ? normalize_number_input($priceData['selling_price']) : null;

                        // Save price at default unit (From Unit / Satuan Besar)
                        if ($defaultUnitId && ($pp > 0 || ($sp !== null && $sp > 0))) {
                            ProductVariantPrice::create([
                                'variant_id' => $variant->id,
                                'company_id' => $companyId,
                                'branch_id' => $branchId,
                                'unit_id' => $defaultUnitId,
                                'price_list_id' => $priceData['price_list_id'],
                                'purchase_price' => $pp,
                                'selling_price' => $sp,
                                'created_by' => $user->id,
                                'updated_by' => $user->id,
                            ]);
                        }

                        // Also save price at smallest unit (To Unit / Satuan Kecil) if different
                        if ($smallUnitId && $smallUnitId != $defaultUnitId && ($pp > 0 || ($sp !== null && $sp > 0))) {
                            $smallPP = $pp > 0 ? $pp / $factor : 0;
                            $smallSP = $sp !== null ? $sp / $factor : null;
                            ProductVariantPrice::create([
                                'variant_id' => $variant->id,
                                'company_id' => $companyId,
                                'branch_id' => $branchId,
                                'unit_id' => $smallUnitId,
                                'price_list_id' => $priceData['price_list_id'],
                                'purchase_price' => $smallPP,
                                'selling_price' => $smallSP,
                                'created_by' => $user->id,
                                'updated_by' => $user->id,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Variant added successfully',
                'variant' => $variant->load('variantAttributes.attributeValue'),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to add variant: '.$e->getMessage(),
            ], 500);
        }
    }

    public function editVariantView(Request $request, $variantId)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $variant = ProductVariant::with([
            'variantAttributes.attributeDefinition',
            'variantAttributes.attributeValue',
            'prices',
            'product',
        ])->findOrFail($variantId);

        $attributeDefinitions = AttributeDefinition::whereNull('deleted_at')
            ->with('attributeValues')
            ->orderBy('name')
            ->get();

        // Get active price lists
        $priceLists = \App\Models\ProductPriceList::whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get product for unit conversion
        $product = $variant->product;
        $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
        $factor = $product->getFactorToSmallest();

        // Convert prices back to display unit (default unit)
        $prices = $variant->prices->map(function ($price) use ($factor) {
            return [
                'id' => $price->id,
                'price_list_id' => $price->price_list_id,
                'purchase_price' => $price->purchase_price * $factor,
                'selling_price' => $price->selling_price * $factor,
            ];
        })->keyBy('price_list_id');

        return response()->json([
            'variant' => $variant,
            'attributes' => $variant->variantAttributes,
            'prices' => $prices,
            'attributeDefinitions' => $attributeDefinitions,
            'priceLists' => $priceLists,
        ]);
    }

    public function updateVariant(Request $request, $variantId)
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $variant = ProductVariant::findOrFail($variantId);

        $request->validate([
            'barcode' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.attribute_definition_id' => 'required|exists:product.attribute_definitions,id',
            'attributes.*.attribute_value_id' => 'required|exists:product.attribute_values,id',
            'prices' => 'nullable|array',
            'prices.*.price_list_id' => 'required|exists:product.product_price_lists,id',
            'prices.*.purchase_price' => 'nullable|numeric|min:0',
            'prices.*.selling_price' => 'nullable|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $user = auth('web')->user();

            // Get product for unit conversion
            $product = $variant->product;
            $product->load(['unitConversions' => fn ($q) => $q->whereNull('deleted_at')]);
            $smallUnitId = $product->getSmallestUnitId();
            $factor = $product->getFactorToSmallest();

            // Update variant
            $variant->update([
                'barcode' => $request->barcode,
                'weight' => $request->weight,
                'is_active' => $request->is_active ?? true,
                'updated_by' => $user->id,
            ]);

            // Sync variant attributes
            ProductVariantAttribute::where('product_variant_id', $variant->id)->delete();
            if ($request->filled('attributes')) {
                foreach ($request->input('attributes') as $attr) {
                    ProductVariantAttribute::create([
                        'product_variant_id' => $variant->id,
                        'attribute_definition_id' => $attr['attribute_definition_id'],
                        'attribute_value_id' => $attr['attribute_value_id'],
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            // Sync prices
            if ($request->filled('prices')) {
                foreach ($request->prices as $priceData) {
                    $pp = normalize_number_input($priceData['purchase_price'] ?? 0);
                    $sp = isset($priceData['selling_price']) ? normalize_number_input($priceData['selling_price']) : null;

                    // Convert to smallest unit
                    $smallPP = $pp > 0 ? $pp / $factor : 0;
                    $smallSP = $sp !== null ? $sp / $factor : null;

                    ProductVariantPrice::updateOrCreate(
                        [
                            'variant_id' => $variant->id,
                            'branch_id' => $branchId,
                            'unit_id' => $smallUnitId,
                            'price_list_id' => $priceData['price_list_id'],
                        ],
                        [
                            'company_id' => $companyId,
                            'purchase_price' => $smallPP,
                            'selling_price' => $smallSP,
                            'updated_by' => $user->id,
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Variant updated successfully',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update variant: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deleteVariant(Request $request, $variantId)
    {
        $variant = ProductVariant::findOrFail($variantId);

        $user = auth('web')->user();

        $variant->updated_by = $user->id;
        $variant->deleted_by = $user->id;
        $variant->save();
        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully',
        ]);
    }

    public function editData(Request $request)
    {
        $request->merge([
            'min_stock' => normalize_number_input($request->min_stock),
            'max_stock' => normalize_number_input($request->max_stock),
        ]);

        $branchId = auth('web')->user()->current_business_unit_id;
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $request->validate([
            'id' => 'required|exists:product.products,id',
            'nature_id' => 'nullable|exists:product.product_natures,id',
            'category_id' => 'nullable|exists:product.product_categories,id',
            'item_type_id' => 'nullable|exists:public.parameter_details,id',
            'product_nature_id' => 'nullable|exists:public.parameter_details,id',
            'procurement_type_id' => 'nullable|exists:public.parameter_details,id',
            'default_unit_id' => 'required|exists:product.product_units,id',
            'name' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($branchId, $request) {
                    $exists = Product::withTrashed()
                        ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->where('id', '!=', $request->id)
                        ->exists();
                    if ($exists) {
                        $fail('Nama product "'.$value.'" sudah ada.');
                    }
                },
            ],
            'code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('product.products', 'code')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($request->id),
            ],
            'description' => 'nullable|string',
            'min_stock' => 'nullable|numeric|min:0',
            'max_stock' => 'nullable|numeric|min:0',
            'is_stock_item' => 'nullable|boolean',
            'is_sale_item' => 'nullable|boolean',
            'is_purchase_item' => 'nullable|boolean',
            'cogs_account_code' => 'nullable|string|max:50',
            'revenue_account_code' => 'nullable|string|max:50',
        ], [
            'code.unique' => 'Code already exists.',
        ]);

        $user = auth('web')->user();
        $product = Product::where('id', $request->id)->withTrashed()->firstOrFail();
        $product->update([
            'nature_id' => $request->nature_id ?: null,
            'category_id' => $request->category_id ?: null,
            'item_type_id' => $request->item_type_id ?: null,
            'product_nature_id' => $request->product_nature_id ?: null,
            'procurement_type_id' => $request->procurement_type_id ?: null,
            'default_unit_id' => $request->default_unit_id,
            'name' => $request->name,
            'code' => $request->code ?: null,
            'description' => $request->description,
            'is_stock_item' => $request->boolean('is_stock_item'),
            'is_sale_item' => $request->boolean('is_sale_item'),
            'is_purchase_item' => $request->boolean('is_purchase_item'),
            'min_stock' => $request->min_stock ?? 0,
            'max_stock' => $request->max_stock,
            'cogs_account_code' => $request->cogs_account_code ?: null,
            'revenue_account_code' => $request->revenue_account_code ?: null,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.index.view')->with('success', 'Product updated successfully');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'product_id_deleted' => 'required|exists:product.products,id',
        ]);

        $product = Product::findOrFail($request->product_id_deleted);
        $product->updated_by = auth('web')->id();
        $product->deleted_by = auth('web')->id();
        $product->save();
        $product->delete();

        BillOfMaterial::where('product_id', $product->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_by' => auth('web')->id()]);

        return redirect()->route('product.index.view')->with('success', 'Product deleted successfully');
    }

    public function addConversion(Request $request)
    {
        $request->validate([
            'raw_material_id' => 'required|exists:product.products,id',
            'from_unit_id' => 'required|exists:product.product_units,id',
            'to_unit_id' => 'required|exists:product.product_units,id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ]);

        $exists = ProductUnitConversion::where('product_id', $request->raw_material_id)
            ->where('from_unit_id', $request->from_unit_id)
            ->where('to_unit_id', $request->to_unit_id)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return back()->with('error', 'Conversion already exists.');
        }

        ProductUnitConversion::create([
            'product_id' => $request->raw_material_id,
            'from_unit_id' => $request->from_unit_id,
            'to_unit_id' => $request->to_unit_id,
            'conversion_factor' => normalize_number_input($request->conversion_factor),
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return back()->with('success', 'Conversion added successfully');
    }

    public function editConversion(Request $request)
    {
        $request->validate([
            'conversion_id' => 'required|exists:product.product_unit_conversions,id',
            'from_unit_id' => 'required|exists:product.product_units,id',
            'to_unit_id' => 'required|exists:product.product_units,id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ]);

        $conversion = ProductUnitConversion::findOrFail($request->conversion_id);

        $exists = ProductUnitConversion::where('product_id', $conversion->product_id)
            ->where('from_unit_id', $request->from_unit_id)
            ->where('to_unit_id', $request->to_unit_id)
            ->where('id', '!=', $request->conversion_id)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return back()->with('error', 'Conversion already exists.');
        }

        $conversion->update([
            'from_unit_id' => $request->from_unit_id,
            'to_unit_id' => $request->to_unit_id,
            'conversion_factor' => normalize_number_input($request->conversion_factor),
            'updated_by' => auth('web')->id(),
        ]);

        return back()->with('success', 'Conversion updated successfully');
    }

    public function deleteConversion(Request $request)
    {
        $request->validate([
            'conversion_id' => 'required|exists:product.product_unit_conversions,id',
        ]);

        $conversion = ProductUnitConversion::findOrFail($request->conversion_id);
        $conversion->forceDelete();

        return back()->with('success', 'Conversion deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'product_id_restored' => 'required|exists:product.products,id',
        ]);

        $product = Product::withTrashed()->findOrFail($request->product_id_restored);
        $product->updated_by = auth('web')->id();
        $product->deleted_by = null;
        $product->save();
        $product->restore();

        return redirect()->route('product.index.view')->with('success', 'Product restored successfully');
    }

    public function removeTempConversion(Request $request)
    {
        $request->validate([
            'index' => 'required|integer|min:0',
        ]);

        $conversions = session()->get('temp_conversions', []);
        if (isset($conversions[$request->index])) {
            unset($conversions[$request->index]);
            $conversions = array_values($conversions);
            foreach ($conversions as $index => &$conversion) {
                $conversion['conversion_level'] = $index + 1;
            }
            unset($conversion);
            session()->put('temp_conversions', $conversions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Conversion removed successfully.',
        ]);
    }

    public function updateTempConversion(Request $request)
    {
        $request->validate([
            'index' => 'required|integer|min:0',
            'from_unit_id' => 'required|exists:product.product_units,id',
            'to_unit_id' => 'required|exists:product.product_units,id|different:from_unit_id',
            'conversion_factor' => 'required|numeric|min:0.000001',
        ], [
            'conversion_factor.required' => 'Conversion factor is required.',
            'conversion_factor.min' => 'Conversion factor must be greater than 0.',
            'to_unit_id.different' => 'To Unit must be different from From Unit.',
        ]);

        $request->merge([
            'conversion_factor' => normalize_number_input($request->conversion_factor),
        ]);

        $tempProduct = session('temp_product');
        $conversions = session()->get('temp_conversions', []);
        $index = (int) $request->index;

        if (! isset($conversions[$index])) {
            return response()->json([
                'success' => false,
                'message' => 'Conversion not found.',
            ], 422);
        }

        $usedUnitIds = $this->getTempConversionUsedUnitIds(
            (string) ($tempProduct['default_unit_id'] ?? ''),
            $conversions,
            $index
        );

        if (in_array($request->to_unit_id, $usedUnitIds, true)) {
            return response()->json([
                'success' => false,
                'message' => 'To Unit is already used in the conversion chain.',
            ], 422);
        }

        $conversions[$index] = [
            'from_unit_id' => $request->from_unit_id,
            'to_unit_id' => $request->to_unit_id,
            'conversion_factor' => $request->conversion_factor,
            'conversion_level' => $conversions[$index]['conversion_level'] ?? ($index + 1),
        ];
        session()->put('temp_conversions', $conversions);

        return response()->json([
            'success' => true,
            'message' => 'Conversion updated successfully.',
        ]);
    }

    public function importData(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ], [
            'file.required' => 'File Excel wajib diupload.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 5MB.',
        ]);

        $user = auth('web')->user();
        $branchId = $user->current_business_unit_id;
        if (! $branchId) {
            return redirect()->back()->with('error', 'Tidak ada branch yang terkait dengan akun Anda.');
        }

        $companyId = $user->getCompanyIdForProduct();
        $import = new ProductImport($user->id, $branchId, $companyId);
        Excel::import($import, $request->file('file'));

        $errors = $import->getErrors();
        if (! empty($errors)) {
            return redirect()->route('product.index.view')
                ->with('warning', 'Import dibatalkan. Tidak ada data yang disimpan.')
                ->with('import_errors', $errors);
        }

        if ($import->getImported() > 0) {
            return redirect()->route('product.index.view')
                ->with('success', "{$import->getImported()} data berhasil diimport.");
        }

        return redirect()->route('product.index.view')
            ->with('success', 'Tidak ada data baru untuk diimport.');
    }

    public function exportData()
    {
        $branchId = auth('web')->user()->current_business_unit_id;
        $filename = 'product_'.date('Ymd_His').'.xlsx';

        return Excel::download(new ProductExport($branchId), $filename);
    }

    public function downloadTemplate()
    {
        $headers = [
            'No', 'SKU', 'Kode', 'Product', 'Nature', 'Satuan Besar',
            'Konversi', 'Satuan Kecil', 'Jumlah (Satuan Besar)',
            'Harga Beli Satuan Besar', 'Jumlah Minimum (Satuan Besar)', 'Kategori',
            'Item Type', 'Inventory Nature', 'Procurement Type',
            'Stock Item', 'Sales Item', 'Purchase Item',
            'COGS Account', 'Revenue Account',
        ];

        $example = [
            1, '', 'AQ-001', 'Aqua', 'Finished Good', 'Dus',
            24, 'Botol', 10, 50000, 5, 'Minuman',
            'Finished Good', 'Inventory Item', 'Purchase',
            'Yes', 'Yes', 'Yes',
            '5000', '4000',
        ];

        $callback = function () use ($headers, $example) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $headers);
            fputcsv($file, $example);
            fclose($file);
        };

        return response()->streamDownload($callback, 'template_import_product.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Resolve the next From Unit by walking the temp conversion chain from default unit.
     *
     * @param  list<array{from_unit_id: string, to_unit_id: string, conversion_factor: mixed, conversion_level?: int}>  $conversions
     */
    protected function resolveTempConversionFromUnitId(string $defaultUnitId, array $conversions): string
    {
        $currentUnitId = $defaultUnitId;

        foreach ($conversions as $conversion) {
            if (($conversion['from_unit_id'] ?? null) === $currentUnitId) {
                $currentUnitId = (string) $conversion['to_unit_id'];
            }
        }

        return $currentUnitId;
    }

    /**
     * Unit IDs already present in the temp conversion chain (default + linked conversions).
     *
     * @param  list<array{from_unit_id: string, to_unit_id: string, conversion_factor: mixed, conversion_level?: int}>  $conversions
     * @return list<string>
     */
    protected function getTempConversionUsedUnitIds(string $defaultUnitId, array $conversions, ?int $exceptIndex = null): array
    {
        $filtered = collect($conversions)
            ->values()
            ->when($exceptIndex !== null, fn ($items) => $items->forget($exceptIndex)->values())
            ->all();

        $chain = [$defaultUnitId];
        $currentUnitId = $defaultUnitId;

        foreach ($filtered as $conversion) {
            if (($conversion['from_unit_id'] ?? null) === $currentUnitId) {
                $currentUnitId = (string) $conversion['to_unit_id'];
                $chain[] = $currentUnitId;
            }
        }

        return array_values(array_unique($chain));
    }

    protected function upsertPrice(string $productId, string $branchId, ?string $companyId, string $unitId, float $pp, ?float $sp, string $userId): void
    {
        $price = ProductPrice::withTrashed()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('unit_id', $unitId)
            ->first();

        if ($price) {
            if ($price->trashed()) {
                $price->restore();
            }
            $price->update([
                'company_id' => $companyId,
                'purchase_price' => $pp,
                'selling_price' => $sp,
                'updated_by' => $userId,
                'deleted_by' => null,
            ]);
        } elseif ($pp > 0 || ($sp !== null && $sp > 0)) {
            ProductPrice::create([
                'product_id' => $productId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'unit_id' => $unitId,
                'purchase_price' => $pp,
                'selling_price' => $sp,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }
}
