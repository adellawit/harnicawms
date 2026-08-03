<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\HelperController;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Journal;
use App\Models\BusinessUnit;
use Illuminate\Http\Request;

class JurnalUmumController extends Controller
{
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
        $companyId = $request->get('company_id') ?: $companies->first()?->id;
        $status = $request->get('status', 'all');
        $search = trim((string) $request->get('q', ''));
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $isFilter = $status !== 'all' || $search !== '' || $dateFrom || $dateTo;

        $journals = collect();
        if ($companyId) {
            $this->assertCompanyAccess($companyId);

            $query = Journal::query()
                ->withCount(['lines', 'attachments'])
                ->with(['fiscalPeriod:id,code,name'])
                ->where('company_id', $companyId);

            if ($status === 'draft') {
                $query->where('status', Journal::STATUS_DRAFT);
            } elseif ($status === 'posted') {
                $query->where('status', Journal::STATUS_POSTED);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('journal_no', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            }

            if ($dateFrom) {
                $query->whereDate('journal_date', '>=', HelperController::parseDate($dateFrom));
            }
            if ($dateTo) {
                $query->whereDate('journal_date', '<=', HelperController::parseDate($dateTo));
            }

            $journals = $query->orderByDesc('journal_date')->orderByDesc('journal_no')->paginate(25)->withQueryString();
        }

        return view('admin.finance.jurnal-umum.index', compact(
            'companies',
            'companyId',
            'journals',
            'status',
            'search',
            'dateFrom',
            'dateTo',
            'isFilter'
        ));
    }

    public function showView(string $id)
    {
        $journal = Journal::with(['lines.account', 'attachments', 'company', 'fiscalPeriod', 'fiscalCalendar'])
            ->findOrFail($id);
        $this->assertCompanyAccess($journal->company_id);

        return view('admin.finance.jurnal-umum.show', [
            'journal' => $journal,
        ]);
    }
}
