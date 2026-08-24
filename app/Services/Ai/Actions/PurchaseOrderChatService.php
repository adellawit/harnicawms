<?php

namespace App\Services\Ai\Actions;

use App\Models\ProductPurchaseOrder;
use App\Models\Supplier;
use App\Services\Ai\AgentContext;
use App\Services\PurchaseOrderHierarchyService;
use Illuminate\Database\QueryException;

/**
 * Buat draf PO header dari chat. Tidak menerima barang dan tidak mengubah stok.
 *
 * Called from AgentRecordActionService for entity=purchase_order create.
 */
class PurchaseOrderChatService
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function createDraft(array $arguments, AgentContext $context, bool $commit = true): array
    {
        $supplierName = ChatFields::string(
            $arguments,
            ['supplier', 'supplier_name', 'pemasok', 'vendor'],
            $arguments['name'] ?? $arguments['query'] ?? null,
        );
        $supplierId = ChatFields::string($arguments, ['supplier_id']);
        $notes = ChatFields::string($arguments, ['notes', 'catatan', 'keterangan'], $arguments['description'] ?? null);

        if ($supplierName === null && $supplierId === null) {
            return ChatFields::missing(['supplier'], 'PO untuk supplier mana?');
        }

        $supplier = $this->resolveSupplier($supplierId, $supplierName, $context);
        if ($supplier === null) {
            $label = $supplierName ?? $supplierId;

            return ChatFields::missing(['supplier'], 'Supplier "'.$label.'" tidak ditemukan. Nama supplier yang benar apa?');
        }

        if (! $context->branchId) {
            return [
                'success' => false,
                'message' => 'Pilih cabang aktif di profil dulu sebelum membuat PO.',
            ];
        }

        $summary = 'Draf PO untuk '.$supplier->name.($notes ? ' — '.$notes : '').'. Belum ada baris item; lanjutkan di modul Purchase Order.';

        if (! $commit) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'purchase_order_draft',
                'title' => 'Buat draf PO?',
                'body' => $summary,
                'confirm_label' => 'Buat draf',
                'cancel_label' => 'Batal',
                'message' => $summary.' Konfirmasi dulu di kartu.',
            ];
        }

        try {
            $purchase = ProductPurchaseOrder::query()->create([
                'purchase_number' => $this->generateNumber(),
                'purchase_date' => now()->toDateString(),
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'supplier_contact' => $supplier->contact,
                'supplier_address' => $supplier->address,
                'company_id' => $context->companyId,
                'branch_id' => $context->branchId,
                'po_kind' => PurchaseOrderHierarchyService::KIND_STANDALONE,
                'status' => 'draft',
                'notes' => $notes,
                'subtotal' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'other_cost_amount' => 0,
                'total' => 0,
                'created_by' => $context->user->id,
                'updated_by' => $context->user->id,
            ]);
        } catch (QueryException $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan draf PO. Coba lagi dari chat.',
            ];
        }

        $item = [
            'id' => $purchase->id,
            'name' => $purchase->purchase_number,
            'label' => $purchase->purchase_number,
            'code' => $purchase->purchase_number,
            'status' => $purchase->status,
            'supplier_name' => $purchase->supplier_name,
            'notes' => $purchase->notes,
        ];

        return [
            'success' => true,
            'applied' => true,
            'entity' => 'purchase_order',
            'item' => $item,
            'items' => [$item],
            'message' => 'Draf PO '.$purchase->purchase_number.' untuk '.$supplier->name.' tersimpan. Tambah item dan proses di halaman Purchase Order.',
        ];
    }

    protected function resolveSupplier(?string $id, ?string $name, AgentContext $context): ?Supplier
    {
        $query = Supplier::query()->whereNull('deleted_at');
        if ($context->companyId) {
            $query->where('company_id', $context->companyId);
        }

        if ($id !== null) {
            return (clone $query)->find($id);
        }

        if ($name === null) {
            return null;
        }

        return (clone $query)
            ->where(function ($q) use ($name) {
                $q->where('name', 'ilike', $name)->orWhere('code', 'ilike', $name);
            })
            ->first();
    }

    protected function generateNumber(): string
    {
        $prefix = 'PO-'.date('Ym').'-';
        $last = ProductPurchaseOrder::withTrashed()
            ->where('purchase_number', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(purchase_number) DESC, purchase_number DESC')
            ->value('purchase_number');
        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
