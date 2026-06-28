<?php

namespace App\Services\Partner;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Partner\PartnerApplication;
use App\Models\Partner\PartnerApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartnerApplicationService
{
    public function createApplication(Request $request, ?string $companyId, ?string $userId = null): PartnerApplication
    {
        return DB::transaction(function () use ($request, $companyId, $userId) {
            $companyId = $companyId ?: $this->fallbackCompanyId();
            $customer = $this->findOrCreateCustomer($request, $companyId, $userId);

            $application = PartnerApplication::create([
                'company_id' => $companyId,
                'customer_id' => $customer?->id,
                'application_number' => $this->generateApplicationNumber(),
                'partner_type' => $request->partner_type,
                'name' => $request->name,
                'email' => $request->email ?: null,
                'phone' => $request->phone ?: null,
                'requested_purchase_quantity' => (float) ($request->requested_purchase_quantity ?? 0),
                'address' => $request->address ?: null,
                'city' => $request->city ?: null,
                'province' => $request->province ?: null,
                'postal_code' => $request->postal_code ?: null,
                'status' => 'submitted',
                'notes' => $request->notes ?: null,
                'submitted_at' => now(),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->storeDocuments($application, $request, $userId);

            return $application->fresh(['customer', 'documents']);
        });
    }

    public function addFollowup(PartnerApplication $application, array $data, ?string $userId): PartnerApplication
    {
        $application->followups()->create([
            'followup_by' => $userId,
            'followup_type' => $data['followup_type'] ?? 'manual',
            'status' => $data['status'] ?? 'open',
            'notes' => $data['notes'] ?? null,
            'next_followup_at' => $data['next_followup_at'] ?? null,
        ]);

        $application->update([
            'status' => $data['application_status'] ?? $application->status,
            'reviewed_at' => $application->reviewed_at ?: now(),
            'updated_by' => $userId,
        ]);

        return $application->fresh(['followups.user']);
    }

    public function assignAgent(PartnerApplication $application, string $agentId, ?string $userId): PartnerApplication
    {
        $application->update([
            'assigned_agent_id' => $agentId,
            'status' => 'assigned',
            'assigned_at' => now(),
            'updated_by' => $userId,
        ]);

        return $application->fresh('assignedAgent');
    }

    private function findOrCreateCustomer(Request $request, string $companyId, ?string $userId): ?Customer
    {
        $group = $this->partnerCustomerGroup($companyId);
        if (! $group) {
            return null;
        }

        if ($request->filled('email') || $request->filled('phone')) {
            $existing = Customer::query()
                ->where('customer_group_id', $group->id)
                ->where(function ($query) use ($request) {
                    $query->when($request->filled('email'), fn ($q) => $q->orWhere('email', $request->email))
                        ->when($request->filled('phone'), fn ($q) => $q->orWhere('phone', $request->phone))
                        ->when($request->filled('phone'), fn ($q) => $q->orWhere('mobile', $request->phone));
                })
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return Customer::create([
            'customer_group_id' => $group->id,
            'code' => $this->generateCustomerCode($group->id),
            'name' => $request->name,
            'email' => $request->email ?: null,
            'phone' => $request->phone ?: null,
            'mobile' => $request->phone ?: null,
            'address' => $request->address ?: null,
            'city' => $request->city ?: null,
            'province' => $request->province ?: null,
            'postal_code' => $request->postal_code ?: null,
            'country' => 'Indonesia',
            'customer_type' => 'PARTNER_LEAD',
            'notes' => 'Created from partner application.',
            'has_app_access' => false,
            'is_active' => true,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function partnerCustomerGroup(string $companyId): ?CustomerGroup
    {
        $branchId = BusinessUnit::where('parent_id', $companyId)
            ->where('type_code', 'BRANCH')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->value('id');

        if (! $branchId) {
            return null;
        }

        return CustomerGroup::firstOrCreate(
            ['branch_id' => $branchId, 'code' => 'PARTNER'],
            [
                'name' => 'Partner Leads',
                'description' => 'Calon Agent dan Reseller dari pendaftaran partner',
                'default_discount' => 0,
                'allow_credit' => false,
                'payment_term_days' => 0,
                'earn_point' => false,
                'point_multiplier' => 1,
                'sort_order' => 20,
                'is_active' => true,
            ]
        );
    }

    private function storeDocuments(PartnerApplication $application, Request $request, ?string $userId): void
    {
        if (! $request->hasFile('documents')) {
            return;
        }

        foreach ($request->file('documents') as $index => $file) {
            if (! $file->isValid()) {
                continue;
            }

            $filename = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $path = $file->storeAs('partner/applications/' . $application->id, $filename, 'public');

            PartnerApplicationDocument::create([
                'application_id' => $application->id,
                'document_type' => $request->input("document_types.{$index}", 'requirement'),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'status' => 'submitted',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function generateApplicationNumber(): string
    {
        $prefix = 'PAPP-' . date('Ym') . '-';
        $last = PartnerApplication::withTrashed()
            ->where('application_number', 'like', $prefix . '%')
            ->orderByDesc('application_number')
            ->value('application_number');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateCustomerCode(string $groupId): string
    {
        $prefix = 'PL-' . date('ym') . '-';
        $last = Customer::withTrashed()
            ->where('customer_group_id', $groupId)
            ->where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->value('code');
        $seq = $last ? ((int) substr($last, strrpos($last, '-') + 1) + 1) : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function fallbackCompanyId(): ?string
    {
        return BusinessUnit::where('type_code', 'COMPANY')
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->value('id');
    }
}
