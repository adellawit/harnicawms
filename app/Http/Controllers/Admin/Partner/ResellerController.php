<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\UpdateResellerMappingRequest;
use App\Models\Partner\Agent;
use App\Models\Partner\Reseller;
use App\Services\Partner\ResellerMappingService;
use App\Support\WmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResellerController extends Controller
{
    public function __construct(
        private readonly ResellerMappingService $mapping,
    ) {}

    public function index(Request $request): View
    {
        $currentAgent = $this->currentAgent();
        $companyId = WmsContext::distributor()?->id;

        $resellers = Reseller::query()
            ->with(['agent', 'customer'])
            ->when($currentAgent, fn ($q) => $q->where('agent_id', $currentAgent->id))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('agent_id'), fn ($q) => $q->where('agent_id', $request->agent_id))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $agents = Agent::query()
            ->whereNull('deleted_at')
            ->when($currentAgent, fn ($q) => $q->whereKey($currentAgent->id))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();

        return view('admin.partner.resellers.index', compact('resellers', 'agents'));
    }

    public function show(string $id): View
    {
        $reseller = Reseller::with(['company', 'agent.defaultWarehouse', 'customer', 'activeAssignment'])
            ->findOrFail($id);
        $currentAgent = $this->currentAgent();

        // Agent may open own resellers + unassigned (to claim).
        abort_if(
            $currentAgent
            && $reseller->agent_id
            && $reseller->agent_id !== $currentAgent->id,
            403
        );

        $companyId = WmsContext::distributor()?->id;
        $agents = Agent::query()
            ->active()
            ->when($currentAgent, fn ($q) => $q->whereKey($currentAgent->id))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get();

        return view('admin.partner.resellers.show', [
            'reseller' => $reseller,
            'agents' => $agents,
            'currentAgent' => $currentAgent,
            'canUnassign' => $currentAgent === null,
        ]);
    }

    public function updateMapping(UpdateResellerMappingRequest $request, string $id): RedirectResponse
    {
        $reseller = Reseller::query()->findOrFail($id);
        $user = Auth::user();
        $currentAgent = $user?->partnerAgent;

        abort_if(
            $currentAgent
            && $reseller->agent_id
            && $reseller->agent_id !== $currentAgent->id,
            403
        );

        try {
            $action = $request->validated('action') ?: 'assign';
            if ($action === 'unassign') {
                $this->mapping->unassign([$reseller->id], $user);
                $message = 'Mapping Reseller dilepas (Unassigned).';
            } else {
                $agentId = $request->validated('agent_id');
                if ($currentAgent) {
                    $agentId = $currentAgent->id;
                }
                if (! $agentId) {
                    return back()->withErrors(['agent_id' => 'Pilih Agent tujuan.']);
                }
                $this->mapping->assign($agentId, [$reseller->id], $user);
                $message = 'Reseller berhasil dipetakan ke Agent.';
            }
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['mapping' => $exception->getMessage()]);
        }

        return redirect()
            ->route('partner.resellers.show', $reseller->id)
            ->with('success', $message);
    }

    private function currentAgent(): ?Agent
    {
        return Auth::user()?->partnerAgent;
    }
}
