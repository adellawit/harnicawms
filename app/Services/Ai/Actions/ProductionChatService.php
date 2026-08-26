<?php

namespace App\Services\Ai\Actions;

use App\Models\BillOfMaterial;
use App\Models\ProductionOrder;
use App\Services\Ai\AgentContext;
use App\Services\Manufacturing\ProductionService;
use App\Support\ManufacturingWarehouseResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Draf production order dari chat. Tidak memotong stok bahan (status draft).
 *
 * Called from AgentRecordActionService for entity=production_order create.
 * Submit/consumeMaterials is not exposed — that mutates stock.
 */
class ProductionChatService
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function createDraft(array $arguments, AgentContext $context, bool $commit = true): array
    {
        $productQuery = ChatFields::string(
            $arguments,
            ['product', 'sku', 'name', 'item', 'barang'],
            $arguments['query'] ?? $arguments['name'] ?? $arguments['code'] ?? null,
        );
        $qty = ChatFields::float($arguments, ['planned_qty', 'quantity', 'qty', 'jumlah']);
        $notes = ChatFields::string($arguments, ['notes', 'catatan'], $arguments['description'] ?? null);

        $missing = [];
        $questions = [];

        if ($productQuery === null) {
            $missing[] = 'product';
            $questions[] = 'Produksi untuk produk atau SKU mana?';
        }

        if ($qty === null || $qty <= 0) {
            $missing[] = 'planned_qty';
            $questions[] = 'Rencana jumlah produksinya berapa?';
        }

        if ($missing !== []) {
            return ChatFields::missing($missing, implode(' ', $questions));
        }

        $bom = $this->findBom($productQuery, $context);
        if ($bom === null) {
            return [
                'success' => false,
                'message' => 'Produk "'.$productQuery.'" belum punya BOM aktif. Buat Bill of Materials dulu, atau sebut SKU yang benar.',
            ];
        }

        $summary = 'Draf production order '.$bom->name.' × '.$qty.'. Status draft — stok bahan belum dipotong.';

        if (! $commit) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'production_draft',
                'title' => 'Buat draf produksi?',
                'body' => $summary,
                'confirm_label' => 'Buat draf',
                'cancel_label' => 'Batal',
                'message' => $summary.' Konfirmasi dulu di kartu.',
            ];
        }

        [$sourceWarehouseId] = ManufacturingWarehouseResolver::resolveMaterialWarehouse($context->companyId);
        [$outputWarehouseId] = ManufacturingWarehouseResolver::resolveOutputWarehouse($context->companyId);

        try {
            $order = DB::transaction(function () use ($bom, $qty, $notes, $context, $sourceWarehouseId, $outputWarehouseId) {
                return ProductionOrder::query()->create([
                    'order_number' => ProductionService::generateNumber(),
                    'production_date' => now()->toDateString(),
                    'company_id' => $context->companyId,
                    'branch_id' => $context->branchId,
                    'source_warehouse_id' => $sourceWarehouseId,
                    'output_warehouse_id' => $outputWarehouseId,
                    'bom_id' => $bom->id,
                    'product_id' => $bom->product_id,
                    'product_variant_id' => $bom->product_variant_id,
                    'output_unit_id' => $bom->output_unit_id,
                    'planned_qty' => $qty,
                    'overhead_cost' => 0,
                    'status' => 'draft',
                    'notes' => $notes,
                    'created_by' => $context->user->id,
                    'updated_by' => $context->user->id,
                ]);
            });
        } catch (QueryException $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan draf production order. Coba lagi dari chat.',
            ];
        }

        $item = [
            'id' => $order->id,
            'name' => $order->order_number,
            'label' => $order->order_number,
            'code' => $order->order_number,
            'status' => $order->status,
            'planned_qty' => (float) $order->planned_qty,
        ];

        return [
            'success' => true,
            'applied' => true,
            'entity' => 'production_order',
            'item' => $item,
            'items' => [$item],
            'message' => 'Draf production order '.$order->order_number.' tersimpan. Proses/submit yang memotong stok tetap di modul Production Order.',
        ];
    }

    protected function findBom(string $query, AgentContext $context): ?BillOfMaterial
    {
        return BillOfMaterial::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($context->companyId, fn ($q) => $q->where('company_id', $context->companyId))
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', $query)
                    ->orWhereHas('product', function ($p) use ($query) {
                        $p->where('name', 'ilike', $query)
                            ->orWhere('sku', 'ilike', $query)
                            ->orWhere('code', 'ilike', $query);
                    })
                    ->orWhereHas('variant', fn ($v) => $v->where('sku', 'ilike', $query));
            })
            ->first();
    }
}
