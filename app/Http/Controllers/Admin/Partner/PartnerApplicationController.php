<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner\Agent;
use App\Models\Partner\PartnerApplication;
use App\Services\Partner\PartnerApplicationService;
use App\Services\Partner\PartnerConversionService;
use App\Support\WmsContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PartnerApplicationController extends Controller
{
    public function __construct(
        private readonly PartnerApplicationService $applicationService,
        private readonly PartnerConversionService $conversionService
    ) {}

    public function publicCreate()
    {
        return view('admin.partner.applications.public-register');
    }

    public function publicStore(Request $request)
    {
        $this->validateApplication($request);

        $application = $this->applicationService->createApplication($request, WmsContext::distributor()?->id);

        return redirect()->route('partner.register.thank-you', $application->application_number);
    }

    public function thankYou(string $number)
    {
        $application = PartnerApplication::where('application_number', $number)->firstOrFail();

        return view('admin.partner.applications.thank-you', compact('application'));
    }

    public function index(Request $request)
    {
        $companyId = WmsContext::distributor()?->id;
        $currentAgent = $this->currentAgent();
        $applications = PartnerApplication::query()
            ->with(['customer', 'assignedAgent', 'convertedAgent', 'convertedReseller'])
            ->forCompany($companyId)
            ->when($currentAgent, function ($query) use ($currentAgent) {
                $query->where(function ($q) use ($currentAgent) {
                    $q->where('assigned_agent_id', $currentAgent->id)
                        ->orWhere('converted_agent_id', $currentAgent->id);
                });
            })
            ->when($request->filled('type'), fn ($q) => $q->where('partner_type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.partner.applications.index', compact('applications'));
    }

    public function create()
    {
        return view('admin.partner.applications.create');
    }

    public function store(Request $request)
    {
        if ($agent = $this->currentAgent()) {
            $request->merge(['partner_type' => PartnerApplication::TYPE_RESELLER]);
        }

        $this->validateApplication($request);

        $application = $this->applicationService->createApplication(
            $request,
            WmsContext::distributor()?->id,
            Auth::id()
        );

        if ($agent ?? null) {
            $this->applicationService->assignAgent($application, $agent->id, Auth::id());
        }

        return redirect()->route('partner.applications.show', $application->id)
            ->with('success', 'Pendaftaran partner berhasil dibuat.');
    }

    public function show(string $id)
    {
        $application = PartnerApplication::with([
            'company',
            'customer',
            'documents',
            'followups.user',
            'assignedAgent',
            'convertedAgent.defaultWarehouse',
            'convertedReseller.agent',
        ])->findOrFail($id);
        $this->authorizeAgentApplication($application);
        $agents = Agent::active()
            ->where('company_id', $application->company_id)
            ->orderBy('name')
            ->get();
        $variants = WmsContext::variantOptions();

        return view('admin.partner.applications.show', compact('application', 'agents', 'variants'));
    }

    public function followup(Request $request, string $id)
    {
        $application = PartnerApplication::findOrFail($id);
        $this->authorizeAgentApplication($application);
        $data = $request->validate([
            'followup_type' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:30'],
            'application_status' => ['nullable', Rule::in(['submitted', 'in_review', 'assigned', 'qualified', 'rejected'])],
            'notes' => ['required', 'string'],
            'next_followup_at' => ['nullable', 'date'],
        ]);

        $this->applicationService->addFollowup($application, $data, Auth::id());

        return back()->with('success', 'Follow-up berhasil dicatat.');
    }

    public function assignAgent(Request $request, string $id)
    {
        abort_if($this->currentAgent(), 403, 'Agent tidak dapat assign application.');
        $application = PartnerApplication::where('partner_type', PartnerApplication::TYPE_RESELLER)->findOrFail($id);
        $data = $request->validate([
            'agent_id' => ['required', Rule::exists(Agent::class, 'id')->where('company_id', $application->company_id)],
        ]);

        $this->applicationService->assignAgent($application, $data['agent_id'], Auth::id());

        return back()->with('success', 'Calon reseller berhasil di-assign ke Agent.');
    }

    public function convertAgent(Request $request, string $id)
    {
        abort_if($this->currentAgent(), 403, 'Agent tidak dapat convert calon Agent.');
        $application = PartnerApplication::where('partner_type', PartnerApplication::TYPE_AGENT)->findOrFail($id);
        $payload = $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'invoice_number' => ['nullable', 'string', 'max:80'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'partial', 'paid'])],
            'lines' => ['nullable', 'array'],
            'lines.*.variant_id' => ['nullable', 'string'],
            'lines.*.qty' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payload['initial_purchase'] = [
            'invoice_number' => $payload['invoice_number'] ?? null,
            'payment_reference' => $payload['payment_reference'] ?? null,
            'payment_status' => $payload['payment_status'] ?? 'unpaid',
            'lines' => $payload['lines'] ?? [],
        ];

        $agent = $this->conversionService->convertAgent($application, $payload, Auth::id());

        return redirect()->route('partner.agents.show', $agent->id)
            ->with('success', 'Application berhasil dikonversi menjadi Agent.');
    }

    public function convertReseller(Request $request, string $id)
    {
        $application = PartnerApplication::where('partner_type', PartnerApplication::TYPE_RESELLER)->findOrFail($id);
        $currentAgent = $this->currentAgent();
        $data = $request->validate([
            'agent_id' => [$currentAgent ? 'nullable' : 'required', Rule::exists(Agent::class, 'id')->where('company_id', $application->company_id)],
            'code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $agentId = $currentAgent?->id ?: $data['agent_id'];
        if ($currentAgent && $application->assigned_agent_id !== $currentAgent->id) {
            abort(403, 'Application reseller ini tidak ditugaskan ke Agent Anda.');
        }

        $reseller = $this->conversionService->convertReseller($application, $agentId, $data, Auth::id());

        return redirect()->route('partner.resellers.show', $reseller->id)
            ->with('success', 'Application berhasil dikonversi menjadi Reseller.');
    }

    private function validateApplication(Request $request): void
    {
        $request->validate([
            'partner_type' => ['required', Rule::in([PartnerApplication::TYPE_AGENT, PartnerApplication::TYPE_RESELLER])],
            'name' => ['required', 'string', 'max:200'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:50'],
            'requested_purchase_quantity' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:5120'],
            'document_types' => ['nullable', 'array'],
        ]);
    }

    private function currentAgent(): ?Agent
    {
        return Auth::user()?->partnerAgent;
    }

    private function authorizeAgentApplication(PartnerApplication $application): void
    {
        $agent = $this->currentAgent();
        if (! $agent) {
            return;
        }

        abort_unless(
            $application->assigned_agent_id === $agent->id || $application->converted_agent_id === $agent->id,
            403,
            'Application ini tidak berada dalam scope Agent Anda.'
        );
    }
}
