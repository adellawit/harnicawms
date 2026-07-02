<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCostLayer;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\Warehouse;
use App\Services\StockMutationService;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InboundController extends Controller
{
    public function index()
    {
        $layers = ProductCostLayer::with(['variant.product', 'branch'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('admin.inbound.index', compact('layers'));
    }

    public function create()
    {
        $variants = WmsContext::variantOptions();
        $branches = $this->branchOptions();

        return view('admin.inbound.create', compact('variants', 'branches'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
            'branch_id' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.000001'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);
        $product = $variant->product;
        $unitId = $product->default_unit_id;
        $companyId = optional(WmsContext::distributor())->id;
        $userId = Auth::id();

        if (! $unitId) {
            return back()->withInput()->with('error', 'Produk belum punya satuan default.');
        }

        DB::transaction(function () use ($product, $variant, $companyId, $data, $unitId, $userId) {
            StockMutationService::inbound(
                $product->id,
                $variant->id,
                $companyId,
                $data['branch_id'],
                $unitId,
                (float) $data['quantity'],
                (float) $data['unit_cost'],
                'Inbound',
                null,
                $userId,
                $data['notes'] ?? 'Stok masuk manual',
                $data['date'] ?? null,
                $data['expiry_date'] ?? null
            );
        });

        return redirect()->route('inbound.index')->with('success', 'Stok masuk berhasil dicatat & layer HPP (FIFO) dibuat.');
    }

    public function transferCreate(Request $request)
    {
        $variants = WmsContext::variantOptions();
        $branches = $this->branchOptions();
        $stocksByVariant = ProductVariantStock::query()
            ->with('unit:id,symbol,name')
            ->whereNull('deleted_at')
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'warehouse_id' => $row->warehouse_id ?: $row->branch_id,
                'quantity' => (float) $row->quantity,
                'unit_label' => $row->unit?->symbol ?: $row->unit?->name,
            ])->values());

        return view('admin.inbound.transfer', [
            'variants' => $variants,
            'branches' => $branches,
            'stocksByVariant' => $stocksByVariant,
            'prefillVariantId' => $request->get('product_variant_id'),
            'prefillFromBranchId' => $request->get('from_branch_id'),
            'prefillToBranchId' => $request->get('to_branch_id'),
        ]);
    }

    public function transferStore(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'string'],
            'from_branch_id' => ['required', 'string', 'different:to_branch_id'],
            'to_branch_id' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.000001'],
            'notes' => ['nullable', 'string'],
        ]);

        $allowedWarehouseIds = collect($this->branchOptions())->pluck('id')->all();
        if (! in_array($data['from_branch_id'], $allowedWarehouseIds, true)
            || ! in_array($data['to_branch_id'], $allowedWarehouseIds, true)) {
            return back()->withInput()->with('error', 'Gudang asal atau tujuan tidak valid.');
        }

        $fromWarehouse = Warehouse::query()->findOrFail($data['from_branch_id']);
        $toWarehouse = Warehouse::query()->findOrFail($data['to_branch_id']);

        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);
        $product = $variant->product;
        $companyId = optional(WmsContext::distributor())->id;
        $userId = Auth::id();
        $quantity = (float) $data['quantity'];

        $sourceStock = ProductVariantStock::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $fromWarehouse->id)
            ->where('quantity', '>', 0)
            ->whereNull('deleted_at')
            ->orderByDesc('quantity')
            ->first();

        if (! $sourceStock) {
            return back()->withInput()->with('error', 'Tidak ada stok di gudang asal.');
        }

        $unitId = $sourceStock->unit_id;
        if (! $unitId) {
            return back()->withInput()->with('error', 'Baris stok asal belum memiliki satuan.');
        }

        $available = (float) ProductVariantStock::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $fromWarehouse->id)
            ->where('unit_id', $unitId)
            ->whereNull('deleted_at')
            ->value('quantity');

        if ($quantity > $available) {
            return back()->withInput()->with('error', "Stok tidak cukup di gudang asal. Tersedia: {$available}.");
        }

        $fromOperationalBranchId = $fromWarehouse->branch_id ?: $fromWarehouse->company_id;
        $toOperationalBranchId = $toWarehouse->branch_id ?: $toWarehouse->company_id;

        if (! $fromOperationalBranchId || ! $toOperationalBranchId) {
            return back()->withInput()->with('error', 'Gudang asal atau tujuan belum memiliki konteks cabang/company.');
        }

        DB::transaction(function () use (
            $product,
            $variant,
            $companyId,
            $data,
            $unitId,
            $userId,
            $quantity,
            $fromOperationalBranchId,
            $toOperationalBranchId,
            $fromWarehouse,
            $toWarehouse
        ) {
            $notes = $data['notes'] ?? 'Pindah gudang (transfer stok)';

            $result = StockMutationService::outbound(
                $product->id,
                $variant->id,
                $companyId,
                $fromOperationalBranchId,
                $unitId,
                $quantity,
                'TransferOut',
                null,
                $userId,
                $notes,
                $fromWarehouse->id
            );

            StockMutationService::inbound(
                $product->id,
                $variant->id,
                $companyId,
                $toOperationalBranchId,
                $unitId,
                $quantity,
                $result['unit_cost'],
                'TransferIn',
                null,
                $userId,
                $notes,
                null,
                $result['earliest_expiry'],
                $toWarehouse->id
            );
        });

        return redirect()->route('inbound.index')->with('success', 'Stok berhasil dipindahkan ke gudang tujuan.');
    }

    private function branchOptions()
    {
        $distributor = WmsContext::distributor();
        $distId = optional($distributor)->id;
        $options = [];

        foreach (WmsContext::accessibleWarehouses() as $wh) {
            $label = $wh->name;
            if ($wh->warehouse_type_code) {
                $label .= ' (' . $wh->warehouse_type_code . ')';
            }
            if ($wh->branch?->name) {
                $label .= ' - ' . $wh->branch->name;
            }

            $options[] = ['id' => $wh->id, 'label' => $label];
        }

        foreach (WmsContext::agents($distId) as $agent) {
            $warehouseId = $agent->default_warehouse_id ?: optional(WmsContext::defaultAgentWarehouse($agent->id))->id;
            if ($warehouseId) {
                $options[] = ['id' => $warehouseId, 'label' => $agent->name . ' (Gudang Agent)'];
            }
        }

        return $options;
    }
}
