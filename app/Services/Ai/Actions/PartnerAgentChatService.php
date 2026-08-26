<?php

namespace App\Services\Ai\Actions;

use App\Models\Partner\Agent;
use App\Models\Partner\PartnerApplication;
use App\Services\Ai\AgentContext;
use App\Services\Partner\PartnerApplicationService;
use App\Services\Partner\PartnerConversionService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Buat agen partner dari chat (manage_record entity=partner_agent create).
 *
 * Agen tidak punya form Tambah di UI: satu-satunya jalur sah adalah pendaftaran
 * partner lalu Convert Agent. Service ini menjalankan dua langkah itu berurutan
 * dalam satu transaksi memakai service yang sama dengan UI:
 * PartnerApplicationService (customer partner + nomor application + kuota
 * pembelian awal) lalu PartnerConversionService::convertAgent (kode
 * AG-yymm-NNNN, gudang agen, akun login, status active/approved).
 *
 * Called from AgentRecordActionService for entity=partner_agent create.
 * Dokumen registrasi (tanda tangan, formulir bertanda tangan) tidak bisa
 * diunggah dari chat: application dibuat tanpa dokumen, tanpa mengarang
 * persetujuan syarat, dan catatan menyebut sisa pekerjaannya.
 */
class PartnerAgentChatService
{
    protected const CHAT_NOTE = 'Dibuat lewat asisten TITANIE (chat). Dokumen registrasi (tanda tangan & formulir) dan pembayaran awal di POS belum dilengkapi.';

    public function __construct(
        protected PartnerApplicationService $applications,
        protected PartnerConversionService $conversions,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function create(array $arguments, AgentContext $context, bool $commit = true): array
    {
        $name = ChatFields::string(
            $arguments,
            ['name', 'nama', 'nama_agen', 'nama_toko', 'agent_name', 'store_name', 'toko'],
            $arguments['name'] ?? $arguments['query'] ?? null,
        );

        if ($name === null) {
            return ChatFields::missing(
                ['name'],
                'Nama agen atau tokonya apa? Contoh: "Toko Makmur Jaya".',
            );
        }

        $email = ChatFields::string($arguments, ['email']);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ChatFields::missing(['email'], 'Email "'.$email.'" tidak valid. Email yang benar apa?');
        }

        $phone = $this->digits(ChatFields::string($arguments, ['phone', 'telepon', 'no_hp', 'hp', 'nomor_telepon', 'whatsapp']));
        $address = ChatFields::string($arguments, ['address', 'alamat']);
        $city = ChatFields::string($arguments, ['city', 'kota', 'kabupaten']);
        $province = ChatFields::string($arguments, ['province', 'provinsi']);
        $postalCode = ChatFields::string($arguments, ['postal_code', 'kode_pos']);
        $code = ChatFields::string($arguments, ['code', 'kode'], $arguments['code'] ?? null);
        $notes = ChatFields::string($arguments, ['notes', 'catatan', 'keterangan'], $arguments['description'] ?? null);
        $quantity = ChatFields::float($arguments, ['requested_purchase_quantity', 'kuota_pembelian', 'target_pembelian']);

        if (! $context->companyId) {
            return [
                'success' => false,
                'message' => 'Company aktif tidak ditemukan. Pilih cabang di profil dulu sebelum membuat agen.',
            ];
        }

        if ($context->user->partnerAgent !== null) {
            return [
                'success' => false,
                'message' => 'Akun agen tidak bisa membuat agen lain. Dari akun agen hanya bisa mengajukan calon reseller.',
            ];
        }

        $existing = Agent::query()
            ->whereNull('deleted_at')
            ->where('company_id', $context->companyId)
            ->where('name', 'ilike', $name)
            ->first();

        if ($existing !== null) {
            $item = $this->serialize($existing);

            return [
                'success' => true,
                'applied' => false,
                'already_exists' => true,
                'entity' => 'partner_agent',
                'item' => $item,
                'items' => [$item],
                'message' => 'Agen "'.$item['label'].'" sudah ada dengan kode '.$item['code'].'.',
            ];
        }

        $summary = 'Agen "'.$name.'"'
            .($city !== null ? ' di '.$city : '')
            .' dibuat lewat pendaftaran partner + Convert Agent: kode agen, gudang agen, dan akun login otomatis.';

        if (! $commit) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'partner_agent_create',
                'title' => 'Buat agen baru?',
                'body' => $summary.' Dokumen registrasi dan pembayaran awal di POS dilengkapi menyusul.',
                'confirm_label' => 'Buat agen',
                'cancel_label' => 'Batal',
                'message' => $summary.' Konfirmasi dulu di kartu. Belum ada data yang dibuat.',
            ];
        }

        try {
            $agent = DB::transaction(fn (): Agent => $this->conversions->convertAgent(
                $this->applications->createFromAttributes([
                    'partner_type' => PartnerApplication::TYPE_AGENT,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                    'province' => $province,
                    'postal_code' => $postalCode,
                    'requested_purchase_quantity' => $quantity,
                    'notes' => $notes !== null ? $notes.' — '.self::CHAT_NOTE : self::CHAT_NOTE,
                ], $context->companyId, $context->user->id),
                $code !== null ? ['code' => $code] : [],
                $context->user->id,
            ));
        } catch (QueryException $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan agen. Periksa nama atau kode yang mungkin sudah dipakai lalu coba lagi dari chat.',
            ];
        }

        $item = $this->serialize($agent);

        return [
            'success' => true,
            'applied' => true,
            'entity' => 'partner_agent',
            'item' => $item,
            'items' => [$item],
            'message' => 'Agen "'.$item['label'].'" berhasil dibuat dengan kode '.$item['code']
                .'. Gudang '.($item['warehouse'] ?? '-').' dan akun login '.($item['username'] ?? '-')
                .' ikut dibuat. Draf replenishment untuk agen ini bisa langsung dibuat dari chat; pembayaran awal dan dokumen registrasi diselesaikan di POS dan halaman Partner Application.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Agent $agent): array
    {
        $agent->loadMissing(['defaultWarehouse', 'customer', 'user']);

        $label = (string) $agent->name;

        return [
            'id' => $agent->id,
            'name' => $label,
            'label' => $label,
            'code' => (string) $agent->code,
            'status' => $agent->status,
            'approval_status' => $agent->approval_status,
            'email' => $agent->email,
            'phone' => $agent->phone,
            'city' => $agent->city,
            'warehouse' => $agent->defaultWarehouse?->name,
            'customer' => $agent->customer?->name,
            'username' => $agent->user?->username,
        ];
    }

    protected function digits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }
}
