<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCostHistory;
use App\Models\ProductCostLayer;
use Illuminate\Support\Facades\DB;

class HppReportController extends Controller
{
    public function index()
    {
        // Layer FIFO berjalan (stok + nilai persediaan)
        $layers = ProductCostLayer::with(['variant.product', 'branch'])
            ->where('quantity_remaining', '>', 0)
            ->orderBy('branch_id')
            ->orderByDesc('effective_date')
            ->limit(200)
            ->get();

        // Histori perubahan HPP
        $history = ProductCostHistory::with(['product', 'branch'])
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $totalInventoryValue = $layers->sum(fn ($l) => (float) $l->quantity_remaining * (float) $l->unit_cost);

        return view('admin.hpp.index', compact('layers', 'history', 'totalInventoryValue'));
    }
}
