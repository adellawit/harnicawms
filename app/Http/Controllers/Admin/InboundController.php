<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\ProductCostLayer;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
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
            ->whereNull('deleted_at')
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'branch_id' => $row->branch_id,
                'quantity' => (float) $row->quantity,
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

        $allowedBranchIds = collect($this->branchOptions())->pluck('id')->all();
        if (! in_array($data['from_branch_id'], $allowedBranchIds, true)
            || ! in_array($data['to_branch_id'], $allowedBranchIds, true)) {
            return back()->withInput()->with('error', 'Gudang asal atau tujuan tidak valid.');
        }

        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);
        $product = $variant->product;
        $unitId = $product->default_unit_id;
        $companyId = optional(WmsContext::distributor())->id;
        $userId = Auth::id();
        $quantity = (float) $data['quantity'];

        if (! $unitId) {
            return back()->withInput()->with('error', 'Produk belum punya satuan default.');
        }

        $available = (float) ProductVariantStock::query()
            ->where('product_variant_id', $variant->id)
            ->where('branch_id', $data['from_branch_id'])
            ->whereNull('deleted_at')
            ->value('quantity');

        if ($quantity > $available) {
            return back()->withInput()->with('error', "Stok tidak cukup di gudang asal. Tersedia: {$available}.");
        }

        DB::transaction(function () use ($product, $variant, $companyId, $data, $unitId, $userId, $quantity) {
            $notes = $data['notes'] ?? 'Pindah gudang (transfer stok)';

            $result = StockMutationService::outbound(
                $product->id,
                $variant->id,
                $companyId,
                $data['from_branch_id'],
                $unitId,
                $quantity,
                'TransferOut',
                null,
                $userId,
                $notes
            );

            StockMutationService::inbound(
                $product->id,
                $variant->id,
                $companyId,
                $data['to_branch_id'],
                $unitId,
                $quantity,
                $result['unit_cost'],
                'TransferIn',
                null,
                $userId,
                $notes,
                null,
                $result['earliest_expiry']
            );
        });

        return redirect()->route('inbound.index')->with('success', 'Stok berhasil dipindahkan ke gudang tujuan.');
    }

    private function branchOptions()
    {
        $distributor = WmsContext::distributor();
        $distId = optional($distributor)->id;
        $options = [];

        // Gudang distributor (WIP & Barang Jadi) - utama untuk penerimaan bahan baku
        foreach (WmsContext::warehouses($distId) as $wh) {
            $options[] = ['id' => $wh->id, 'label' => $wh->name];
        }
        // Agen (opsional)
        foreach (WmsContext::agents($distId) as $agent) {
            $options[] = ['id' => $agent->id, 'label' => $agent->name . ' (Agen)'];
        }

        return $options;
    }
}
