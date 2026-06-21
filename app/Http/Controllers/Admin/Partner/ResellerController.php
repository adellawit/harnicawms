<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner\Agent;
use App\Models\Partner\Reseller;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResellerController extends Controller
{
    public function index(Request $request)
    {
        $resellers = Reseller::query()
            ->with(['agent', 'customer'])
            ->when($currentAgent = $this->currentAgent(), fn ($q) => $q->where('agent_id', $currentAgent->id))
            ->when($companyId = WmsContext::distributor()?->id, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('agent_id'), fn ($q) => $q->where('agent_id', $request->agent_id))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
        $agents = Agent::active()
            ->when($currentAgent, fn ($q) => $q->whereKey($currentAgent->id))
            ->when($companyId = WmsContext::distributor()?->id, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();

        return view('admin.partner.resellers.index', compact('resellers', 'agents'));
    }

    public function show(string $id)
    {
        $reseller = Reseller::with(['company', 'agent.defaultWarehouse', 'customer', 'activeAssignment'])
            ->findOrFail($id);
        $currentAgent = $this->currentAgent();
        abort_if($currentAgent && $reseller->agent_id !== $currentAgent->id, 403);

        return view('admin.partner.resellers.show', compact('reseller'));
    }

    private function currentAgent(): ?Agent
    {
        return Auth::user()?->partnerAgent;
    }
}
