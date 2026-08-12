<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\StoreAgentPksRequest;
use App\Models\Partner\Agent;
use App\Models\Partner\AgentPks;
use App\Services\Partner\AgentPksService;
use App\Support\WmsContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentController extends Controller
{
    public function __construct(
        protected AgentPksService $pksService
    ) {}

    public function index(Request $request)
    {
        $today = now()->toDateString();
        $horizon = now()->addDays(AgentPksService::REMINDER_DAYS)->toDateString();

        $agents = Agent::query()
            ->with(['customer', 'defaultWarehouse', 'activePks', 'pksDocuments'])
            ->when($currentAgent = $this->currentAgent(), fn ($q) => $q->whereKey($currentAgent->id))
            ->when($companyId = WmsContext::distributor()?->id, fn ($q) => $q->where('company_id', $companyId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->input('pks_status') === 'expiring', function ($q) use ($today, $horizon) {
                $q->whereHas('activePks', function ($p) use ($today, $horizon) {
                    $p->whereDate('end_date', '>=', $today)
                        ->whereDate('end_date', '<=', $horizon);
                });
            })
            ->when($request->input('pks_status') === 'expired', function ($q) use ($today) {
                $q->where(function ($inner) use ($today) {
                    $inner->whereHas('activePks', fn ($p) => $p->whereDate('end_date', '<', $today))
                        ->orWhere(function ($o) use ($today) {
                            $o->whereDoesntHave('activePks')
                                ->whereHas('pksDocuments', fn ($p) => $p->where('status', AgentPks::STATUS_EXPIRED)
                                    ->orWhereDate('end_date', '<', $today));
                        });
                });
            })
            ->when($request->input('pks_status') === 'missing', function ($q) {
                $q->whereDoesntHave('activePks')
                    ->whereDoesntHave('pksDocuments', fn ($p) => $p->where('status', AgentPks::STATUS_EXPIRED))
                    ->whereNotNull('customer_id')
                    ->whereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('transaction.sales_orders as so')
                            ->whereColumn('so.customer_id', 'partner.agents.customer_id')
                            ->where('so.payment_status', 'paid')
                            ->whereNull('so.deleted_at');
                    });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $pksService = $this->pksService;
        $agents->getCollection()->transform(function (Agent $agent) use ($pksService) {
            $agent->setAttribute('pks_badge', $pksService->pksBadge($agent));

            return $agent;
        });

        return view('admin.partner.agents.index', compact('agents'));
    }

    public function show(string $id)
    {
        $agent = Agent::with([
            'company',
            'customer',
            'user',
            'defaultWarehouse',
            'warehouses',
            'resellers',
            'activePks',
            'pksDocuments.uploader',
        ])->findOrFail($id);

        $currentAgent = $this->currentAgent();
        abort_if($currentAgent && $currentAgent->id !== $agent->id, 403);

        $canUploadPks = $this->pksService->hasCompletedFirstPurchase($agent);
        $pksBadge = $this->pksService->pksBadge($agent);

        return view('admin.partner.agents.show', compact('agent', 'canUploadPks', 'pksBadge'));
    }

    public function storePks(StoreAgentPksRequest $request, string $id): RedirectResponse
    {
        $agent = Agent::query()->findOrFail($id);
        $currentAgent = $this->currentAgent();
        abort_if($currentAgent && $currentAgent->id !== $agent->id, 403);

        $this->pksService->store(
            $agent,
            $request->file('file'),
            Carbon::parse($request->validated('start_date'))->startOfDay(),
            Carbon::parse($request->validated('end_date'))->startOfDay(),
            $request->validated('notes'),
            (string) Auth::id()
        );

        return redirect()
            ->route('partner.agents.show', $agent->id)
            ->with('success', 'PKS berhasil diunggah.');
    }

    public function downloadPks(string $id, string $pksId): StreamedResponse|\Illuminate\Http\Response
    {
        $agent = Agent::query()->findOrFail($id);
        $currentAgent = $this->currentAgent();
        abort_if($currentAgent && $currentAgent->id !== $agent->id, 403);

        $pks = AgentPks::query()
            ->where('agent_id', $agent->id)
            ->whereKey($pksId)
            ->firstOrFail();

        abort_unless($this->pksService->fileExists($pks), 404, 'File PKS tidak ditemukan.');

        return response()->download(
            $this->pksService->absoluteFilePath($pks),
            $pks->file_name
        );
    }

    private function currentAgent(): ?Agent
    {
        return Auth::user()?->partnerAgent;
    }
}
