<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\ProductCostLayer;
use App\Models\ProductVariant;
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
                $data['date'] ?? null
            );
        });

        return redirect()->route('inbound.index')->with('success', 'Stok masuk berhasil dicatat & layer HPP (FIFO) dibuat.');
    }

    private function branchOptions()
    {
        $distributor = WmsContext::distributor();
        $options = [];
        if ($distributor) {
            $options[] = ['id' => $distributor->id, 'label' => $distributor->name . ' (Distributor)'];
        }
        foreach (WmsContext::agents(optional($distributor)->id) as $agent) {
            $options[] = ['id' => $agent->id, 'label' => $agent->name . ' (Agen)'];
        }

        return $options;
    }
}
