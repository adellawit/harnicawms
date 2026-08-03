<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountMapping;
use App\Models\Accounting\ChartOfAccount;
use App\Models\BusinessUnit;
use App\Services\Accounting\AccountMappingService;
use Illuminate\Http\Request;

class AccountMappingController extends Controller
{
    public function __construct(
        private readonly AccountMappingService $mappingService
    ) {}

    protected function companiesQuery()
    {
        $user = auth('web')->user();

        $query = BusinessUnit::query()
            ->whereNull('deleted_at')
            ->where('type_code', 'COMPANY')
            ->where('is_active', true);

        if (! $user->is_super_admin) {
            $userBu = $user->businessUnit;
            if (! $userBu) {
                return $query->whereRaw('1=0');
            }

            match ($userBu->type_code) {
                'HOLDING' => $query->where('parent_id', $userBu->id),
                'COMPANY' => $query->where('id', $userBu->id),
                'BRANCH' => $query->where('id', $userBu->parent_id),
                default => $query->whereRaw('1=0'),
            };
        }

        return $query;
    }

    protected function assertCompanyAccess(string $companyId): void
    {
        if (! $this->companiesQuery()->where('id', $companyId)->exists()) {
            abort(403, 'Company is not accessible.');
        }
    }

    public function indexView(Request $request)
    {
        $companies = $this->companiesQuery()->orderBy('name')->get();
        $companyId = $request->get('company_id', $companies->first()?->id);

        $mappings = [];
        $accounts = collect();

        if ($companyId) {
            $this->assertCompanyAccess($companyId);

            $mappings = AccountMapping::query()
                ->where('company_id', $companyId)
                ->pluck('account_id', 'mapping_key')
                ->all();

            $accounts = ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->where('is_header', false)
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        }

        $grouped = $this->mappingService->mappingKeysGroupedByModule();
        $activeModule = $request->get('module', array_key_first($grouped) ?: 'general');
        if (! isset($grouped[$activeModule])) {
            $activeModule = array_key_first($grouped) ?: 'general';
        }

        return view('admin.finance.account-mapping.index', [
            'companies' => $companies,
            'companyId' => $companyId,
            'groupedKeys' => $grouped,
            'activeModule' => $activeModule,
            'mappings' => $mappings,
            'accounts' => $accounts,
            'accountTypes' => ChartOfAccount::ACCOUNT_TYPES,
        ]);
    }

    public function saveData(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|uuid',
            'module' => 'nullable|string|max:50',
            'mappings' => 'nullable|array',
            'mappings.*' => 'nullable|uuid',
        ]);

        $this->assertCompanyAccess($validated['company_id']);

        // Merge with existing so saving one module tab does not wipe other modules
        $existing = AccountMapping::query()
            ->where('company_id', $validated['company_id'])
            ->pluck('account_id', 'mapping_key')
            ->all();

        $incoming = $validated['mappings'] ?? [];
        $merged = $existing;

        foreach ($this->mappingService->mappingKeys() as $key => $_) {
            if (array_key_exists($key, $incoming)) {
                $merged[$key] = $incoming[$key] ?: null;
            }
        }

        $saved = $this->mappingService->saveAll(
            $validated['company_id'],
            $merged,
            auth('web')->id()
        );

        return redirect()
            ->route('finance.account-mapping.index.view', [
                'company_id' => $validated['company_id'],
                'module' => $validated['module'] ?? 'general',
            ])
            ->with('success', "Account mapping saved ({$saved} keys set).");
    }
}
