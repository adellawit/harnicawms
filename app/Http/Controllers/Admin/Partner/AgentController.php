<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner\Agent;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $agents = Agent::query()
            ->with(['customer', 'defaultWarehouse'])
            ->when($currentAgent = $this->currentAgent(), fn ($q) => $q->whereKey($currentAgent->id))
            ->when($companyId = WmsContext::distributor()?->id, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.partner.agents.index', compact('agents'));
    }

    public function show(string $id)
    {
        $agent = Agent::with(['company', 'customer', 'user', 'defaultWarehouse', 'warehouses', 'resellers'])
            ->findOrFail($id);
        $currentAgent = $this->currentAgent();
        abort_if($currentAgent && $currentAgent->id !== $agent->id, 403);

        return view('admin.partner.agents.show', compact('agent'));
    }

    private function currentAgent(): ?Agent
    {
        return Auth::user()?->partnerAgent;
    }
}
