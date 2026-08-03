<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\CampaignRequest;
use App\Models\Marketing\Campaign;
use App\Models\Promotion;
use App\Support\WmsContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $companyId = optional(WmsContext::distributor())->id;

        $campaigns = Campaign::with('promotion:id,code,name')
            ->when($companyId, fn ($q) => $q->where(fn ($qq) => $qq->whereNull('company_id')->orWhere('company_id', $companyId)))
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.marketing.campaigns.index', compact('campaigns'));
    }

    public function create(): View
    {
        $companyId = optional(WmsContext::distributor())->id;

        return view('admin.marketing.campaigns.create', [
            'promotions' => $this->promotionOptions($companyId),
            'previewCode' => Campaign::generateCode($companyId),
        ]);
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        $companyId = optional(WmsContext::distributor())->id;
        $data = $this->payload($request);
        $data['company_id'] = $companyId;
        $data['code'] = Campaign::generateCode($companyId);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        if ($request->hasFile('banner')) {
            $data['banner_path'] = $request->file('banner')->store('marketing/campaigns', 'public');
        }

        Campaign::create($data);

        return redirect()
            ->route('marketing.campaigns.index')
            ->with('success', 'Campaign ditambahkan.');
    }

    public function edit(string $id): View
    {
        $campaign = Campaign::findOrFail($id);
        $companyId = optional(WmsContext::distributor())->id;

        return view('admin.marketing.campaigns.edit', [
            'campaign' => $campaign,
            'promotions' => $this->promotionOptions($companyId, $campaign->promotion_id),
            'previewCode' => $campaign->code,
        ]);
    }

    public function update(CampaignRequest $request, string $id): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);
        $data = $this->payload($request);
        unset($data['code']);
        $data['updated_by'] = Auth::id();

        if ($request->hasFile('banner')) {
            if ($campaign->banner_path) {
                Storage::disk('public')->delete($campaign->banner_path);
            }
            $data['banner_path'] = $request->file('banner')->store('marketing/campaigns', 'public');
        }

        $campaign->update($data);

        return redirect()
            ->route('marketing.campaigns.index')
            ->with('success', 'Campaign diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $campaign = Campaign::findOrFail($id);
        $campaign->update(['deleted_by' => Auth::id()]);
        $campaign->delete();

        return redirect()
            ->route('marketing.campaigns.index')
            ->with('success', 'Campaign dihapus.');
    }

    protected function promotionOptions(?string $companyId, ?string $includeId = null)
    {
        return Promotion::query()
            ->when($companyId, fn ($q) => $q->where(fn ($qq) => $qq->whereNull('company_id')->orWhere('company_id', $companyId)))
            ->where(function ($q) use ($includeId) {
                $q->activeNow();
                if ($includeId) {
                    $q->orWhere('id', $includeId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    protected function payload(CampaignRequest $request): array
    {
        $data = $request->validated();
        $data['promotion_id'] = $request->input('promotion_id') ?: null;
        $data['reactivates_reseller'] = $request->boolean('reactivates_reseller');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['priority'] = (int) ($request->input('priority') ?: 0);
        $data['starts_at'] = $this->parseDateInput(trim((string) $request->input('starts_at')));
        $data['ends_at'] = $this->parseDateInput(trim((string) $request->input('ends_at')), endOfDay: true);

        if ($data['starts_at'] && $data['ends_at'] && $data['ends_at']->lt($data['starts_at'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ends_at' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            ]);
        }

        return $data;
    }

    protected function parseDateInput(?string $value, bool $endOfDay = false): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                return $endOfDay ? $date->endOfDay() : $date->startOfDay();
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            $date = Carbon::parse($value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
