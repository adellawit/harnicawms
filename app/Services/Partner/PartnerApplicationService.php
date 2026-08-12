<?php

namespace App\Services\Partner;

use App\Models\BusinessUnit;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Partner\PartnerApplication;
use App\Models\Partner\PartnerApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PartnerApplicationService
{
    public function insert(Request $request, ?string $companyId, ?string $userId = null): PartnerApplication
    {
        return $this->createApplication($request, $companyId, $userId);
    }

    public function createApplication(Request $request, ?string $companyId, ?string $userId = null): PartnerApplication
    {
        return DB::transaction(function () use ($request, $companyId, $userId) {
            $companyId = $companyId ?: $this->fallbackCompanyId();
            $customer = $this->findOrCreateCustomer($request, $companyId, $userId);

            $application = PartnerApplication::create(array_merge(
                $this->applicationAttributes($request),
                [
                    'company_id' => $companyId,
                    'customer_id' => $customer?->id,
                    'application_number' => $this->generateApplicationNumber(),
                    'status' => 'submitted',
                    'submitted_at' => now(),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            ));

            $this->storeDocuments($application, $request, $userId);
            $this->storeSignature($application, $request, $userId);
            $this->storeSignedForm($application, $request, $userId);

            return $application->fresh(['customer', 'documents']);
        });
    }

    public function update(PartnerApplication $application, Request $request, ?string $userId = null): PartnerApplication
    {
        return DB::transaction(function () use ($application, $request, $userId) {
            $application->update(array_merge(
                $this->applicationAttributes($request),
                ['updated_by' => $userId]
            ));

            $this->syncCustomer($application, $request, $userId);
            $this->storeDocuments($application, $request, $userId);

            if ($request->filled('signature_data')) {
                $this->replaceDocumentsByType($application, 'signature');
                $this->storeSignature($application, $request, $userId);
            }

            if ($request->hasFile('signed_form')) {
                $this->replaceDocumentsByType($application, 'signed_registration_form');
                $this->storeSignedForm($application, $request, $userId);
            }

            return $application->fresh(['customer', 'documents']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationAttributes(Request $request): array
    {
        return [
            'partner_type' => $request->partner_type,
            'name' => $request->name,
            'email' => $request->email ?: null,
            'phone' => $request->phone ?: null,
            'birth_place' => $request->birth_place ?: null,
            'birth_date' => $request->birth_date ?: null,
            'address_ktp' => $request->address_ktp ?: null,
            'requested_purchase_quantity' => $this->resolvePurchaseQuantity($request),
            'address' => $request->address ?: null,
            'city' => $request->city ?: null,
            'province' => $request->province ?: null,
            'postal_code' => $request->postal_code ?: null,
            'latitude' => $request->filled('latitude') ? $request->input('latitude') : null,
            'longitude' => $request->filled('longitude') ? $request->input('longitude') : null,
            'marketplace_tokopedia' => $request->boolean('marketplace_tokopedia'),
            'marketplace_tokopedia_account' => $request->boolean('marketplace_tokopedia')
                ? ($request->input('marketplace_tokopedia_account') ?: null)
                : null,
            'marketplace_shopee' => $request->boolean('marketplace_shopee'),
            'marketplace_shopee_account' => $request->boolean('marketplace_shopee')
                ? ($request->input('marketplace_shopee_account') ?: null)
                : null,
            'marketplace_other' => $request->boolean('marketplace_others')
                ? ($request->input('marketplace_other') ?: null)
                : null,
            'reseller_package' => $request->input('reseller_package') ?: null,
            'terms_accepted' => $request->input('terms_accepted', []),
            'declaration_accepted' => $request->boolean('declaration_accepted'),
            'filled_at' => $request->input('filled_at', now()->toDateString()),
            'notes' => $request->notes ?: null,
        ];
    }

    private function syncCustomer(PartnerApplication $application, Request $request, ?string $userId): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        $customer->update([
            'name' => $request->name,
            'email' => $request->email ?: null,
            'phone' => $request->phone ?: null,
            'mobile' => $request->phone ?: null,
            'address' => $request->address ?: null,
            'city' => $request->city ?: null,
            'province' => $request->province ?: null,
            'postal_code' => $request->postal_code ?: null,
            'lat' => $request->filled('latitude') ? $request->input('latitude') : null,
            'long' => $request->filled('longitude') ? $request->input('longitude') : null,
            'birth_place' => $request->birth_place ?: null,
            'birth_date' => $request->birth_date ?: null,
            'address_ktp' => $request->address_ktp ?: null,
            'updated_by' => $userId,
        ]);
    }

    private function replaceDocumentsByType(PartnerApplication $application, string $documentType): void
    {
        $application->documents()
            ->where('document_type', $documentType)
            ->get()
            ->each(function (PartnerApplicationDocument $document) {
                Storage::disk('public')->delete($document->file_path);
                $document->delete();
            });
    }

    public function addFollowup(PartnerApplication $application, array $data, ?string $userId): PartnerApplication
    {
        $application->followups()->create([
            'followup_by' => $userId,
            'followup_type' => $data['followup_type'] ?? null,
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
            'lat' => $request->filled('latitude') ? $request->input('latitude') : null,
            'long' => $request->filled('longitude') ? $request->input('longitude') : null,
            'birth_place' => $request->birth_place ?: null,
            'birth_date' => $request->birth_date ?: null,
            'address_ktp' => $request->address_ktp ?: null,
            'country' => 'Indonesia',
            'customer_type' => Customer::TYPE_PARTNER_LEAD,
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

    private function storeSignature(PartnerApplication $application, Request $request, ?string $userId): void
    {
        if ($request->hasFile('signature')) {
            $file = $request->file('signature');
            if (! $file->isValid()) {
                return;
            }

            $filename = time() . '_signature_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $path = $file->storeAs('partner/applications/' . $application->id, $filename, 'public');

            PartnerApplicationDocument::create([
                'application_id' => $application->id,
                'document_type' => 'signature',
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'status' => 'submitted',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return;
        }

        if (! $request->filled('signature_data')) {
            return;
        }

        $encoded = preg_replace('#^data:image/\w+;base64,#i', '', $request->signature_data);
        $binary = base64_decode($encoded, true);

        if ($binary === false || strlen($binary) > 5 * 1024 * 1024) {
            return;
        }

        $filename = time() . '_signature_digital.png';
        $path = 'partner/applications/' . $application->id . '/' . $filename;
        Storage::disk('public')->put($path, $binary);

        PartnerApplicationDocument::create([
            'application_id' => $application->id,
            'document_type' => 'signature',
            'file_path' => $path,
            'original_name' => 'tanda-tangan-digital.png',
            'status' => 'submitted',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function storeSignedForm(PartnerApplication $application, Request $request, ?string $userId): void
    {
        if (! $request->hasFile('signed_form')) {
            return;
        }

        $file = $request->file('signed_form');
        if (! $file->isValid()) {
            return;
        }

        $filename = time() . '_signed_form_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
        $path = $file->storeAs('partner/applications/' . $application->id, $filename, 'public');

        PartnerApplicationDocument::create([
            'application_id' => $application->id,
            'document_type' => 'signed_registration_form',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'submitted',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function resolvePurchaseQuantity(Request $request): float
    {
        if ($request->filled('requested_purchase_quantity')) {
            return (float) $request->requested_purchase_quantity;
        }

        return match ($request->input('reseller_package')) {
            'A' => 120,
            'B' => 60,
            'C' => 30,
            default => $request->partner_type === PartnerApplication::TYPE_AGENT ? 600 : 0,
        };
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
