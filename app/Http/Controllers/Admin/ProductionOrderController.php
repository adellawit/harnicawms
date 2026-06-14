<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillOfMaterial;
use App\Models\ProductionOrder;
use App\Services\Manufacturing\ProductionService;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductionOrderController extends Controller
{
    public function index()
    {
        $orders = ProductionOrder::with(['product', 'variant', 'branch'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.production.index', compact('orders'));
    }

    public function create()
    {
        $boms = BillOfMaterial::with(['product', 'variant', 'items.componentVariant.product'])
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();
        $distributor = WmsContext::distributor();

        // Data ringkas untuk preview kebutuhan bahan (dipakai JS)
        $bomData = $boms->map(function (BillOfMaterial $b) {
            return [
                'id' => $b->id,
                'output_quantity' => (float) $b->output_quantity,
                'items' => $b->items->map(fn ($i) => [
                    'label' => $i->componentVariant?->display_name ?? $i->componentProduct?->name,
                    'qty' => (float) $i->quantity,
                ])->values(),
            ];
        })->values();

        return view('admin.production.create', compact('boms', 'distributor', 'bomData'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bom_id' => ['required', 'string'],
            'planned_qty' => ['required', 'numeric', 'min:0.000001'],
            'overhead_cost' => ['nullable', 'numeric', 'min:0'],
            'production_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'complete' => ['nullable'],
        ]);

        $bom = BillOfMaterial::with('product')->findOrFail($data['bom_id']);
        $distributor = WmsContext::distributor();
        $userId = Auth::id();

        $order = ProductionOrder::create([
            'order_number' => ProductionService::generateNumber(),
            'production_date' => $data['production_date'] ?? now()->toDateString(),
            'company_id' => optional($distributor)->id,
            'branch_id' => optional($distributor)->id, // gudang distributor = company id
            'bom_id' => $bom->id,
            'product_id' => $bom->product_id,
            'product_variant_id' => $bom->product_variant_id,
            'output_unit_id' => $bom->output_unit_id,
            'planned_qty' => (float) $data['planned_qty'],
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

    public function show(string $id)
    {
        $order = ProductionOrder::with([
            'product', 'variant', 'branch',
            'bom.items.componentVariant.product',
            'materials.componentVariant.product',
            'outputs.variant.product',
        ])->findOrFail($id);

        return view('admin.production.show', compact('order'));
    }

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
}
