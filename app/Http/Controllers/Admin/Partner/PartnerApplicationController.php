<?php

namespace App\Http\Controllers\Admin\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\StorePartnerApplicationRequest;
use App\Http\Requests\Partner\UpdatePartnerApplicationRequest;
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

    public function publicStore(StorePartnerApplicationRequest $request)
    {
        $application = $this->applicationService->insert(
            $request,
            WmsContext::distributor()?->id
        );

        return redirect()->route('partner.register.thank-you', $application->application_number);
    }

    public function thankYou(string $number)
    {
        $application = PartnerApplication::where('application_number', $number)->firstOrFail();

        return view('admin.partner.applications.thank-you', compact('application'));
    }

    public function downloadAgentForm()
    {
        $path = $this->partnerFormPath('form-registrasi-agen-harnica.pdf');
        abort_unless(is_file($path), 404, 'Formulir registrasi agen tidak ditemukan.');

        return response()->download($path, 'Form-Registrasi-Agen-Harnica.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadResellerForm()
    {
        $path = $this->partnerFormPath('form-registrasi-reseller-harnica.pdf');
        abort_unless(is_file($path), 404, 'Formulir registrasi reseller tidak ditemukan.');

        return response()->download($path, 'Form-Registrasi-Reseller-Harnica.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function partnerFormPath(string $filename): string
    {
        return public_path('downloads/partner/' . $filename);
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
        return view('admin.partner.applications.create', [
            'lockPartnerTypeReseller' => (bool) $this->currentAgent(),
        ]);
    }

    public function store(StorePartnerApplicationRequest $request)
    {
        $application = $this->applicationService->insert(
            $request,
            WmsContext::distributor()?->id,
            Auth::id()
        );

        if ($agent = $this->currentAgent()) {
            $this->applicationService->assignAgent($application, $agent->id, Auth::id());
        }

        return redirect()->route('partner.applications.show', $application->id)
            ->with('success', 'Pendaftaran partner berhasil dibuat.');
    }

    public function edit(PartnerApplication $application)
    {
        $this->authorizeAgentApplication($application);
        abort_unless($application->isEditable(), 403, 'Application yang sudah dikonversi tidak dapat diubah.');

        $application->load('documents');

        return view('admin.partner.applications.edit', [
            'application' => $application,
            'lockPartnerTypeReseller' => (bool) $this->currentAgent(),
        ]);
    }

    public function update(UpdatePartnerApplicationRequest $request, PartnerApplication $application)
    {
        $this->authorizeAgentApplication($application);
        abort_unless($application->isEditable(), 403, 'Application yang sudah dikonversi tidak dapat diubah.');

        $this->applicationService->update($application, $request, Auth::id());

        return redirect()->route('partner.applications.show', $application->id)
            ->with('success', 'Pendaftaran partner berhasil diperbarui.');
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

        return view('admin.partner.applications.show', compact('application', 'agents'));
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
