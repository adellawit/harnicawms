<?php

namespace App\Http\Requests\Partner\Concerns;

use App\Models\Partner\PartnerApplication;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesPartnerApplication
{
    /**
     * @return array<string, mixed>
     */
    protected function partnerApplicationRules(bool $isUpdate = false, ?PartnerApplication $application = null): array
    {
        $hasSignature = $application?->documents()->where('document_type', 'signature')->exists() ?? false;

        return [
            'partner_type' => ['required', Rule::in([PartnerApplication::TYPE_AGENT, PartnerApplication::TYPE_RESELLER])],
            'name' => ['required', 'string', 'max:200'],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today'],
            'address_ktp' => ['required', 'string'],
            'address' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[0-9]+$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'marketplace_tokopedia' => ['nullable', 'boolean'],
            'marketplace_shopee' => ['nullable', 'boolean'],
            'marketplace_others' => ['nullable', 'boolean'],
            'marketplace_tokopedia_account' => ['nullable', 'string', 'max:200', 'required_if:marketplace_tokopedia,1'],
            'marketplace_shopee_account' => ['nullable', 'string', 'max:200', 'required_if:marketplace_shopee,1'],
            'marketplace_other' => ['nullable', 'string', 'max:200', 'required_if:marketplace_others,1'],
            'reseller_package' => [
                Rule::requiredIf($this->input('partner_type') === PartnerApplication::TYPE_RESELLER),
                'nullable',
                Rule::in(['A', 'B', 'C']),
            ],
            'terms_accepted' => ['required', 'array'],
            'terms_accepted.*' => ['string', 'max:80'],
            'declaration_accepted' => ['accepted'],
            'signature_data' => [
                Rule::requiredIf(! $isUpdate || ! $hasSignature),
                'nullable',
                'string',
                Rule::when($this->filled('signature_data'), ['regex:/^data:image\/png;base64,/']),
            ],
            'signed_form' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:5120'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function partnerApplicationMessages(): array
    {
        return [
            'declaration_accepted.accepted' => 'Anda harus menyetujui pernyataan kebenaran data.',
            'signature_data.required' => 'Tanda tangan digital wajib diisi.',
            'signature_data.regex' => 'Format tanda tangan digital tidak valid.',
            'signed_form.mimes' => 'Formulir harus berformat PDF, JPG, atau PNG.',
            'signed_form.max' => 'Ukuran formulir maksimal 5MB.',
            'marketplace_tokopedia_account.required_if' => 'Akun Tokopedia wajib diisi jika Tokopedia dipilih.',
            'marketplace_shopee_account.required_if' => 'Akun Shopee wajib diisi jika Shopee dipilih.',
            'marketplace_other.required_if' => 'Nama marketplace lainnya wajib diisi.',
            'reseller_package.required' => 'Pilih salah satu paket pembelian Reseller.',
            'latitude.required' => 'Lokasi pada peta wajib ditentukan.',
            'longitude.required' => 'Lokasi pada peta wajib ditentukan.',
            'latitude.between' => 'Latitude tidak valid.',
            'longitude.between' => 'Longitude tidak valid.',
        ];
    }

    protected function withPartnerApplicationValidator(Validator $validator): void
    {
        $agentTerms = [
            'cooperation_agreement',
            'initial_purchase_2mc',
            'monthly_purchase_1mc',
            'storage_standard',
            'no_consortium',
            'no_undercut_price',
            'serve_reseller_area',
            'guide_resellers',
        ];

        $resellerTerms = [
            'buy_from_official_agent',
            'no_undercut_price',
            'follow_company_rules',
        ];

        $validator->after(function (Validator $validator) use ($agentTerms, $resellerTerms) {
            $accepted = $this->input('terms_accepted', []);
            $required = $this->input('partner_type') === PartnerApplication::TYPE_AGENT ? $agentTerms : $resellerTerms;

            foreach ($required as $term) {
                if (! in_array($term, $accepted, true)) {
                    $validator->errors()->add('terms_accepted', 'Semua syarat dan ketentuan wajib disetujui.');
                    break;
                }
            }

            if (
                ! $this->boolean('marketplace_tokopedia')
                && ! $this->boolean('marketplace_shopee')
                && ! $this->boolean('marketplace_others')
            ) {
                $validator->errors()->add('marketplace', 'Pilih minimal satu marketplace.');
            }
        });
    }
}
