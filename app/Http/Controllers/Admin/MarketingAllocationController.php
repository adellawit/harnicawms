<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingStockAllocation;
use App\Services\Distribution\MarketingAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class MarketingAllocationController extends Controller
{
    public function index()
    {
        $allocations = MarketingStockAllocation::with([
            'fromWarehouse:id,code,name',
            'toWarehouse:id,code,name',
            'items',
        ])
            ->orderByDesc('allocation_date')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.marketing-allocation.index', compact('allocations'));
    }

    public function create()
    {
        $from = MarketingAllocationService::resolveProductWarehouse(
            optional(\App\Support\WmsContext::distributor())->id
        );
        $to = MarketingAllocationService::resolveMarketingWarehouse(
            optional(\App\Support\WmsContext::distributor())->id
        );

        $stockLines = $from
            ? MarketingAllocationService::availableStockLines($from)
            : [];

        return view('admin.marketing-allocation.create', [
            'fromWarehouse' => $from,
            'toWarehouse' => $to,
            'stockLines' => $stockLines,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'allocation_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.variant_id' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.000001'],
        ]);

        try {
            $allocation = MarketingAllocationService::createAndTransfer(
                $data['lines'],
                $data['notes'] ?? null,
                $data['allocation_date'] ?? null,
                Auth::id()
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('marketing-allocation.show', $allocation->id)
            ->with('success', 'Marketing allocation completed. Stock has been transferred.');
    }

    public function show(string $id)
    {
        $allocation = MarketingStockAllocation::with([
            'fromWarehouse',
            'toWarehouse',
            'items.variant.product',
            'items.unit',
        ])->findOrFail($id);

        return view('admin.marketing-allocation.show', compact('allocation'));
    }
}
