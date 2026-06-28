<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner\Agent;
use App\Models\Partner\PartnerApplication;
use App\Models\Partner\Reseller;
use App\Models\ProductVariantStock;
use App\Models\ReplenishmentOrder;
use App\Support\WmsContext;
use Illuminate\Support\Facades\Auth;

class PartnerReportController extends Controller
{
    public function index()
    {
        $companyId = WmsContext::distributor()?->id;
        $currentAgent = Auth::user()?->partnerAgent;

        $agents = Agent::query()
            ->with('defaultWarehouse')
            ->when($currentAgent, fn ($q) => $q->whereKey($currentAgent->id))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();

        $summary = [
            'applications' => PartnerApplication::forCompany($companyId)
                ->when($currentAgent, fn ($q) => $q->where('assigned_agent_id', $currentAgent->id))
                ->count(),
            'agents' => Agent::when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->when($currentAgent, fn ($q) => $q->whereKey($currentAgent->id))
                ->count(),
            'resellers' => Reseller::when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->when($currentAgent, fn ($q) => $q->where('agent_id', $currentAgent->id))
                ->count(),
            'replenishment_total' => ReplenishmentOrder::when($companyId, fn ($q) => $q->where('distributor_id', $companyId))
                ->when($currentAgent, fn ($q) => $q->where('agent_id', $currentAgent->id))
                ->sum('total'),
        ];

        $agentStocks = $agents->map(function (Agent $agent) {
            $warehouse = $agent->defaultWarehouse;
            $stockQty = $warehouse
                ? ProductVariantStock::where('warehouse_id', $warehouse->id)->sum('quantity')
                : 0;

            return [
                'agent' => $agent,
                'warehouse' => $warehouse,
                'stock_qty' => $stockQty,
            ];
        });

        return view('admin.partner.reports.index', compact('summary', 'agentStocks'));
    }
}
