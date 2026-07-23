<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\BulkResellerMappingRequest;
use App\Models\Partner\Agent;
use App\Models\Partner\Reseller;
use App\Services\Partner\ResellerMappingService;
use App\Support\WmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ResellerMappingController extends Controller
{
    public function __construct(
        private readonly ResellerMappingService $mapping,
    ) {}

    public function index(Request $request): View
    {
        $currentAgent = Auth::user()?->partnerAgent;
        $companyId = WmsContext::distributor()?->id;

        $agents = Agent::query()
            ->active()
            ->when($currentAgent, fn ($q) => $q->whereKey($currentAgent->id))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $resellers = Reseller::query()
            ->with(['agent:id,code,name', 'customer:id,code,name'])
            ->whereNull('deleted_at')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($currentAgent, function ($q) use ($currentAgent): void {
                // Agent sees unassigned + own resellers only.
                $q->where(function ($inner) use ($currentAgent): void {
                    $inner->whereNull('agent_id')
                        ->orWhere('agent_id', $currentAgent->id);
                });
            })
            ->when($request->filled('agent_id'), function ($q) use ($request): void {
                if ($request->agent_id === 'unassigned') {
                    $q->whereNull('agent_id');
                } else {
                    $q->where('agent_id', $request->agent_id);
                }
            })
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = '%'.trim((string) $request->q).'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('code', 'ilike', $term)
                        ->orWhere('name', 'ilike', $term)
                        ->orWhere('email', 'ilike', $term);
                });
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.partner.resellers.mapping', [
            'resellers' => $resellers,
            'agents' => $agents,
            'currentAgent' => $currentAgent,
            'canUnassign' => $currentAgent === null,
            'filters' => [
                'agent_id' => $request->agent_id,
                'q' => $request->q,
            ],
        ]);
    }

    public function update(BulkResellerMappingRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $currentAgent = $user?->partnerAgent;
        $action = $request->validated('action');
        $resellerIds = $request->validated('reseller_ids');

        try {
            if ($action === 'unassign') {
                $count = $this->mapping->unassign($resellerIds, $user);
                $message = "{$count} Reseller dilepas dari Agent (Unassigned).";
            } else {
                $agentId = $request->validated('agent_id');
                if ($currentAgent) {
                    $agentId = $currentAgent->id;
                }
                if (! $agentId) {
                    return back()->withErrors(['agent_id' => 'Pilih Agent tujuan.']);
                }
                $count = $this->mapping->assign($agentId, $resellerIds, $user);
                $message = "{$count} Reseller berhasil dipetakan.";
            }
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['mapping' => $exception->getMessage()]);
        }

        return redirect()
            ->route('partner.resellers.mapping.index', $request->only(['agent_id', 'q']))
            ->with('success', $message);
    }
}
