<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReplenishmentOrder;
use App\Models\ReplenishmentOrderItem;
use App\Models\Shipment;
use App\Models\ProductVariant;
use App\Services\Distribution\ReplenishmentStockService;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReplenishmentOrderController extends Controller
{
    public function index()
    {
        $orders = ReplenishmentOrder::with(['distributor', 'agent', 'items'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.replenishment.index', compact('orders'));
    }

    public function create()
    {
        $distributor = WmsContext::distributor();
        $agents = WmsContext::agents(optional($distributor)->id);
        $variants = WmsContext::variantOptions();

        return view('admin.replenishment.create', compact('distributor', 'agents', 'variants'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'agent_id' => ['required', 'string'],
            'order_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.variant_id' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.000001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $distributor = WmsContext::distributor();
        $userId = Auth::id();

        $order = DB::transaction(function () use ($data, $distributor, $userId) {
            $order = ReplenishmentOrder::create([
                'order_number' => $this->generateNumber(),
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'distributor_id' => optional($distributor)->id,
                'agent_id' => $data['agent_id'],
                'status' => 'submitted',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $subtotal = 0;
            foreach ($data['lines'] as $line) {
                $variant = ProductVariant::with('product')->find($line['variant_id']);
                if (! $variant) {
                    continue;
                }
                $lineSubtotal = (float) $line['qty'] * (float) $line['unit_price'];
                $subtotal += $lineSubtotal;

                ReplenishmentOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'unit_id' => $variant->product->default_unit_id,
                    'qty_ordered' => (float) $line['qty'],
                    'unit_price' => (float) $line['unit_price'],
                    'subtotal' => $lineSubtotal,
                ]);
            }

            $order->update(['subtotal' => $subtotal, 'total' => $subtotal]);

            return $order;
        });

        return redirect()->route('replenishment.show', $order->id)
            ->with('success', 'Pesanan replenishment dibuat. Lanjutkan dengan Pengiriman.');
    }

    public function show(string $id)
    {
        $order = ReplenishmentOrder::with([
            'distributor', 'agent',
            'items.variant.product',
            'shipments.items', 'receipts.items', 'returns.items',
        ])->findOrFail($id);

        return view('admin.replenishment.show', compact('order'));
    }

    public function approve(string $id)
    {
        $order = ReplenishmentOrder::findOrFail($id);
        $order->update(['status' => 'approved', 'updated_by' => Auth::id()]);

        return back()->with('success', 'Pesanan disetujui.');
    }

    public function ship(Request $request, string $id)
    {
        $order = ReplenishmentOrder::with('items')->findOrFail($id);
        $data = $request->validate([
            'ship_date' => ['nullable', 'date'],
            'carrier' => ['required', 'string'],
            'tracking_number' => ['required', 'string'], // nomor resi wajib agar agen bisa lacak
            'notes' => ['nullable', 'string'],
            'qty' => ['required', 'array'],
        ], [
            'carrier.required' => 'Kurir wajib diisi.',
            'tracking_number.required' => 'Nomor resi wajib diisi agar pengiriman bisa dilacak agen.',
        ]);

        try {
            ReplenishmentStockService::ship($order, $data, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengirim: ' . $e->getMessage());
        }

        return redirect()->route('replenishment.show', $order->id)
            ->with('success', 'Pengiriman dibuat. Stok Distributor berkurang (FIFO).');
    }

    public function receive(string $id, string $shipmentId)
    {
        $shipment = Shipment::with('items.orderItem', 'order')->findOrFail($shipmentId);

        try {
            ReplenishmentStockService::receive($shipment, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menerima: ' . $e->getMessage());
        }

        return redirect()->route('replenishment.show', $id)
            ->with('success', 'Barang diterima. Stok Agen bertambah + layer HPP (harga transfer).');
    }

    public function returnGoods(Request $request, string $id)
    {
        $order = ReplenishmentOrder::with('items')->findOrFail($id);
        $data = $request->validate([
            'return_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'qty' => ['required', 'array'],
        ]);

        try {
            ReplenishmentStockService::returnGoods($order, $data, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal retur: ' . $e->getMessage());
        }

        return redirect()->route('replenishment.show', $order->id)
            ->with('success', 'Retur diproses. Stok Agen berkurang, stok Distributor bertambah.');
    }

    private function generateNumber(): string
    {
        $prefix = 'RPO-' . date('Ym') . '-';
        $last = ReplenishmentOrder::withTrashed()
            ->where('order_number', 'like', $prefix . '%')
            ->orderByDesc('order_number')
            ->value('order_number');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
