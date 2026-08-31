<?php

namespace App\Services\Ai\Actions;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\Warehouse;
use App\Services\Ai\AgentContext;
use App\Services\StockMutationService;
use Illuminate\Support\Facades\DB;

/**
 * Penyesuaian stok dari chat lewat StockMutationService.
 *
 * Called from AgentRecordActionService for entity=stock create.
 * Never writes product_variant_stock.quantity directly.
 */
class StockChatService
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function adjust(array $arguments, AgentContext $context, bool $commit = true): array
    {
        if (! $context->branchId) {
            return [
                'success' => false,
                'message' => 'Pilih cabang aktif di profil dulu sebelum menyesuaikan stok.',
            ];
        }

        $query = ChatFields::string(
            $arguments,
            ['product', 'sku', 'name', 'item', 'barang'],
            $arguments['query'] ?? $arguments['name'] ?? $arguments['code'] ?? null,
        );
        $quantity = ChatFields::float($arguments, ['quantity', 'qty', 'jumlah', 'delta', 'physical_qty', 'stok']);
        if ($quantity === null && isset($arguments['quantity']) && is_numeric($arguments['quantity'])) {
            $quantity = (float) $arguments['quantity'];
        }
        $modeRaw = ChatFields::string(
            $arguments,
            ['mode', 'arah', 'jenis', 'adjustment'],
            is_string($arguments['mode'] ?? null) ? $arguments['mode'] : null,
        );
        $notes = ChatFields::string($arguments, ['notes', 'catatan', 'keterangan'], $arguments['description'] ?? null);
        $mode = self::resolveMode($modeRaw, self::intentText($arguments, $query, $notes));
        $warehouseName = ChatFields::string($arguments, ['warehouse', 'gudang', 'warehouse_name']);
        $warehouseId = ChatFields::string($arguments, ['warehouse_id']);

        $missing = [];
        $questions = [];

        if ($query === null) {
            $missing[] = 'sku';
            $questions[] = 'Produk atau SKU mana yang stoknya mau disesuaikan?';
        }

        if ($quantity === null || $quantity < 0) {
            $missing[] = 'quantity';
            $questions[] = 'Jumlah stoknya berapa?';
        }

        if ($missing !== []) {
            return ChatFields::missing($missing, implode(' ', $questions));
        }

        $variant = $this->findVariant($query, $context);
        if ($variant === null) {
            return [
                'success' => false,
                'message' => 'Produk "'.$query.'" tidak ditemukan di cabang aktif.',
            ];
        }

        $warehouse = $this->resolveWarehouse($warehouseId, $warehouseName, $context);
        if ($warehouseName !== null && $warehouse === null) {
            return ChatFields::missing(['warehouse'], 'Gudang "'.$warehouseName.'" tidak ditemukan. Gudangnya mana?');
        }

        $unitId = $variant->product?->default_unit_id
            ?: $variant->product?->getSmallestUnitId();

        if ($unitId === null || $unitId === '') {
            return [
                'success' => false,
                'message' => 'Produk ini belum punya satuan. Lengkapi satuan di master produk dulu.',
            ];
        }

        $current = $this->currentQuantity($variant, $warehouse, $context);
        $delta = self::quantityDelta($mode, $quantity, $current);

        if (abs($delta) < 0.000001) {
            return [
                'success' => true,
                'applied' => false,
                'entity' => 'stock',
                'message' => 'Stok '.$variant->product?->name.' sudah '.$this->formatQty($current).'. Tidak ada perubahan.',
            ];
        }

        $label = (string) ($variant->product?->name ?? $query);
        $summary = self::confirmationBody($delta, $label, $current);

        if (! $commit) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'stock_adjust',
                'title' => self::confirmationTitle($delta),
                'body' => $summary,
                'confirm_label' => 'Konfirmasi',
                'cancel_label' => 'Batal',
                'message' => $summary.' Konfirmasi dulu di kartu. Stok belum diubah.',
            ];
        }

        try {
            DB::transaction(function () use ($variant, $context, $unitId, $delta, $notes, $warehouse) {
                if ($delta > 0) {
                    StockMutationService::inbound(
                        $variant->product_id,
                        $variant->id,
                        $context->companyId,
                        (string) $context->branchId,
                        $unitId,
                        $delta,
                        0.0,
                        'AgentStockAdjustment',
                        null,
                        $context->user->id,
                        $notes ?? 'Penyesuaian stok dari chat',
                        now()->toDateString(),
                        null,
                        $warehouse?->id,
                    );
                } else {
                    StockMutationService::outbound(
                        $variant->product_id,
                        $variant->id,
                        $context->companyId,
                        (string) $context->branchId,
                        $unitId,
                        abs($delta),
                        'AgentStockAdjustment',
                        null,
                        $context->user->id,
                        $notes ?? 'Penyesuaian stok dari chat',
                        $warehouse?->id,
                    );
                }
            });
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        $after = $this->currentQuantity($variant, $warehouse, $context);

        return [
            'success' => true,
            'applied' => true,
            'entity' => 'stock',
            'item' => [
                'id' => $variant->id,
                'name' => $label,
                'label' => $label,
                'sku' => (string) ($variant->sku ?: $variant->product?->sku),
                'stock' => $after,
            ],
            'items' => [[
                'code' => (string) ($variant->sku ?: $variant->product?->sku),
                'name' => $label,
            ]],
            'message' => 'Stok '.$label.' disesuaikan menjadi '.$this->formatQty($after).'.',
        ];
    }

    /**
     * Additive chat language ("tambah 10") must never become an absolute SET
     * (which would compute 10 − current and look like "Kurangi 90").
     */
    public static function resolveMode(?string $mode, ?string $intentText = null): string
    {
        $fromText = self::detectIntent($intentText);
        $fromField = self::parseMode($mode);

        if ($fromText === 'in') {
            return 'in';
        }

        if ($fromText === 'out') {
            return 'out';
        }

        if ($fromField !== null) {
            return $fromField;
        }

        if ($fromText === 'set') {
            return 'set';
        }

        return 'in';
    }

    public static function quantityDelta(string $mode, float $quantity, float $current): float
    {
        return match ($mode) {
            'in' => $quantity,
            'out' => -$quantity,
            default => $quantity - $current,
        };
    }

    public static function confirmationBody(float $delta, string $label, float $current): string
    {
        $qty = self::formatQtyValue($delta > 0 ? $delta : abs($delta));
        $now = self::formatQtyValue($current);

        return $delta > 0
            ? 'Tambah '.$qty.' untuk '.$label.' (sekarang '.$now.').'
            : 'Kurangi '.$qty.' untuk '.$label.' (sekarang '.$now.').';
    }

    public static function confirmationTitle(float $delta): string
    {
        $qty = self::formatQtyValue($delta > 0 ? $delta : abs($delta));

        return $delta > 0 ? 'Tambah '.$qty.'?' : 'Kurangi '.$qty.'?';
    }

    protected static function parseMode(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $key = strtolower((string) preg_replace('/\s+/u', '', $value));

        return match (true) {
            in_array($key, ['in', 'masuk', 'tambah', 'tambahkan', 'inbound', 'plus', 'add', 'increase', 'increment', 'delta', 'nambah', 'restock'], true) => 'in',
            in_array($key, ['out', 'keluar', 'kurang', 'kurangi', 'kurangkan', 'outbound', 'minus', 'decrease', 'reduce'], true) => 'out',
            in_array($key, ['set', 'setel', 'jadi', 'jadikan', 'menjadi', 'target', 'absolute', 'opname'], true) => 'set',
            default => 'set',
        };
    }

    protected static function detectIntent(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $blob = mb_strtolower($text);

        $hasAdd = (bool) preg_match(
            '/\b(tambahkan|tambah|nambah|ditambah|naikkan|menaikkan|increase|increasing|adding|\badd\b|plus|restock|inbound|masukkan|masuk)\b/u',
            $blob
        );
        $hasOut = (bool) preg_match(
            '/\b(kurangkan|kurangi|kurang|dikurangi|turunkan|decrease|reduce|reducing|keluarkan|keluar|outbound|minus)\b/u',
            $blob
        );
        $hasSet = (bool) preg_match(
            '/\b(setel|set to|set ke|jadikan|menjadi|target|opname|absolute)\b|\bset\b/u',
            $blob
        );

        if ($hasAdd && ! $hasSet) {
            return 'in';
        }

        if ($hasOut && ! $hasAdd && ! $hasSet) {
            return 'out';
        }

        if ($hasAdd) {
            return 'in';
        }

        if ($hasSet) {
            return 'set';
        }

        if ($hasOut) {
            return 'out';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected static function intentText(array $arguments, ?string $query, ?string $notes): string
    {
        $extra = ChatFields::extra($arguments);
        $parts = [
            $notes,
            $query,
            $arguments['description'] ?? null,
            $arguments['name'] ?? null,
            $arguments['query'] ?? null,
            $extra['notes'] ?? null,
            $extra['catatan'] ?? null,
            $extra['keterangan'] ?? null,
            $extra['adjustment'] ?? null,
        ];

        return trim(implode(' ', array_filter($parts, fn ($part) => is_string($part) && trim($part) !== '')));
    }

    protected static function formatQtyValue(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');
    }

    protected function findVariant(string $query, AgentContext $context): ?ProductVariant
    {
        $product = Product::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')->orderBy('sort_order')])
            ->when($context->companyId, fn ($q) => $q->where('company_id', $context->companyId))
            ->where(function ($q) use ($query) {
                $q->where('name', 'ilike', $query)
                    ->orWhere('sku', 'ilike', $query)
                    ->orWhere('code', 'ilike', $query)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'ilike', $query));
            })
            ->first();

        if ($product === null) {
            return ProductVariant::query()
                ->with('product')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->where('sku', 'ilike', $query)
                ->first();
        }

        return $product->variants->first();
    }

    protected function resolveWarehouse(?string $id, ?string $name, AgentContext $context): ?Warehouse
    {
        if ($id !== null) {
            return Warehouse::query()->whereKey($id)->first();
        }

        if ($name !== null) {
            return Warehouse::query()
                ->where(function ($q) use ($name) {
                    $q->where('name', 'ilike', $name)->orWhere('code', 'ilike', $name);
                })
                ->first();
        }

        return $context->branchId ? Warehouse::defaultForBranch($context->branchId) : null;
    }

    protected function currentQuantity(ProductVariant $variant, ?Warehouse $warehouse, AgentContext $context): float
    {
        $query = ProductVariantStock::query()
            ->where('product_variant_id', $variant->id)
            ->whereNull('deleted_at');

        if ($warehouse !== null) {
            $query->where('warehouse_id', $warehouse->id);
        } elseif ($context->branchId) {
            $query->where('branch_id', $context->branchId);
        }

        return (float) $query->sum('quantity');
    }

    protected function formatQty(float $value): string
    {
        return self::formatQtyValue($value);
    }
}
