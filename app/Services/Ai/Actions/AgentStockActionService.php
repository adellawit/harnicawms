<?php

namespace App\Services\Ai\Actions;

use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\ProductVariant;
use App\Models\ProductVariantStock;
use App\Models\StockMutationType;
use App\Models\User;
use App\Services\Ai\AgentContext;
use App\Services\Product\ProductSearchService;
use App\Services\Telegram\TelegramProductResolver;
use App\Support\WmsContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AgentStockActionService
{
    public function __construct(
        protected AgentDraftStore $drafts,
        protected SaleDraftCalculator $tokens,
        protected ProductSearchService $productSearch,
        protected TelegramProductResolver $productResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments, AgentContext $context): array
    {
        $conversationId = $context->conversationId;

        if ($conversationId === null || $conversationId === '') {
            return [
                'success' => false,
                'message' => 'Percakapan tidak ditemukan. Mulai chat baru lalu coba lagi.',
            ];
        }

        $operation = strtolower(trim((string) ($arguments['operation'] ?? '')));

        return match ($operation) {
            'set_quantity' => $this->setQuantity($arguments, $context, $conversationId),
            'show' => $this->show($conversationId),
            'clear' => $this->clear($conversationId),
            'propose' => $this->propose($conversationId),
            default => [
                'success' => false,
                'message' => 'Operasi tidak dikenali. Gunakan set_quantity, show, clear, atau propose.',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    public function tokenMatches(?array $draft, string $token): bool
    {
        if ($draft === null) {
            return false;
        }

        return $this->tokens->tokenMatches($draft, $token);
    }

    public function peek(string $conversationId): ?array
    {
        return $this->drafts->get($conversationId, 'stock');
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(User $user, string $conversationId, string $token): array
    {
        $draft = $this->peek($conversationId);

        if ($draft === null || ($draft['items'] ?? []) === []) {
            return [
                'success' => false,
                'message' => 'Tidak ada draf penyesuaian stok yang bisa dikonfirmasi.',
            ];
        }

        if (! $this->tokenMatches($draft, $token)) {
            return [
                'success' => false,
                'message' => 'Konfirmasi tidak valid atau sudah kedaluwarsa. Ajukan ulang draf stok.',
            ];
        }

        $context = AgentContext::fromUser($user, 'web', $conversationId);

        if (! $context->hasPermission(['menu' => 'Stock Adjustment', 'action' => 'is_update'])) {
            return [
                'success' => false,
                'message' => 'Anda tidak memiliki izin menyesuaikan stok.',
            ];
        }

        try {
            $branchId = $context->requireBranch();
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        if (($draft['branch_id'] ?? null) !== $branchId) {
            return [
                'success' => false,
                'message' => 'Cabang draf tidak sama dengan cabang aktif.',
            ];
        }

        $result = $this->applyAdjustment($draft, $user);

        if ($result['success'] ?? false) {
            $this->drafts->forget($conversationId, 'stock');
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $conversationId, string $token): array
    {
        $draft = $this->peek($conversationId);

        if ($draft === null) {
            return [
                'success' => true,
                'message' => 'Tidak ada draf stok yang perlu dibatalkan.',
            ];
        }

        if (! $this->tokenMatches($draft, $token)) {
            return [
                'success' => false,
                'message' => 'Pembatalan tidak valid.',
            ];
        }

        $this->drafts->forget($conversationId, 'stock');

        return [
            'success' => true,
            'message' => 'Draf penyesuaian stok dibatalkan.',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function setQuantity(array $arguments, AgentContext $context, string $conversationId): array
    {
        $branchId = $context->requireBranch();
        $target = (float) ($arguments['target_quantity'] ?? -1);

        if ($target < 0) {
            return [
                'success' => false,
                'message' => 'Jumlah stok tujuan tidak boleh negatif.',
            ];
        }

        $warehouse = WmsContext::defaultWarehouse($branchId);

        if ($warehouse === null) {
            return [
                'success' => false,
                'message' => 'Gudang default cabang tidak ditemukan.',
            ];
        }

        $applyTo = strtolower(trim((string) ($arguments['apply_to'] ?? 'one')));
        $limit = (int) config('agent.stock_adjust_max_items', 40);

        if ($applyTo === 'all_sale_items') {
            $items = $this->saleItemsInWarehouse($branchId, $warehouse->id, $target, $limit);
        } else {
            $items = $this->matchingItems($arguments, $context, $branchId, $warehouse->id, $target, $limit);
        }

        if (isset($items['error'])) {
            return $items['error'];
        }

        if ($items === []) {
            return [
                'success' => false,
                'message' => 'Tidak ada produk stok yang cocok di gudang cabang aktif.',
            ];
        }

        $draft = [
            'kind' => 'stock',
            'branch_id' => $branchId,
            'company_id' => $context->companyId,
            'warehouse_id' => $warehouse->id,
            'warehouse_name' => $warehouse->name,
            'target_quantity' => $target,
            'items' => $items,
            'confirmation_token' => null,
        ];

        $this->drafts->put($conversationId, $draft, 'stock');

        return $this->propose($conversationId, auto: true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function show(string $conversationId): array
    {
        $draft = $this->peek($conversationId);

        if ($draft === null || ($draft['items'] ?? []) === []) {
            return [
                'success' => true,
                'message' => 'Draf penyesuaian stok masih kosong.',
                'items' => [],
            ];
        }

        return $this->draftPayload($draft, 'Draf penyesuaian stok saat ini.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function clear(string $conversationId): array
    {
        $this->drafts->forget($conversationId, 'stock');

        return [
            'success' => true,
            'message' => 'Draf penyesuaian stok dikosongkan.',
            'items' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function propose(string $conversationId, bool $auto = false): array
    {
        $draft = $this->peek($conversationId);

        if ($draft === null || ($draft['items'] ?? []) === []) {
            return [
                'success' => false,
                'message' => 'Draf stok masih kosong. Set jumlah dulu.',
            ];
        }

        $token = Str::random(40);
        $draft = $this->tokens->withConfirmationToken($draft, $token);
        $this->drafts->put($conversationId, $draft, 'stock');

        $payload = $this->draftPayload(
            $draft,
            $auto
                ? 'Draf penyesuaian stok siap. User harus menekan tombol konfirmasi di chat sebelum stok diubah.'
                : 'Draf stok siap dikonfirmasi user.'
        );
        $payload['needs_confirmation'] = true;
        $payload['confirmation_token'] = $token;
        $payload['action'] = 'confirm_stock';

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return list<array<string, mixed>>|array{error: array<string, mixed>}
     */
    protected function matchingItems(
        array $arguments,
        AgentContext $context,
        string $branchId,
        string $warehouseId,
        float $target,
        int $limit,
    ): array {
        $variantId = trim((string) ($arguments['variant_id'] ?? ''));
        $query = trim((string) ($arguments['product_query'] ?? ''));
        $priceListId = $this->productResolver->resolveDefaultPriceListId($branchId, $context->companyId);

        if ($variantId !== '') {
            $variant = ProductVariant::query()->with('product')->where('id', $variantId)->first();
            $mapped = $variant ? $this->productSearch->mapVariant($variant, $branchId, $priceListId) : null;

            if ($mapped === null) {
                return [
                    'error' => [
                        'success' => false,
                        'message' => 'Varian produk tidak ditemukan di cabang aktif.',
                    ],
                ];
            }

            return [$this->lineFromSearch($mapped, $warehouseId, $target)];
        }

        if ($query === '') {
            return [
                'error' => [
                    'success' => false,
                    'message' => 'Sebutkan produk/SKU, atau set apply_to=all_sale_items untuk semua barang jual.',
                ],
            ];
        }

        $matches = $this->productSearch->search($query, $branchId, $context->companyId, $priceListId, min($limit, 10));

        if ($matches->isEmpty()) {
            return [
                'error' => [
                    'success' => false,
                    'message' => "Produk \"{$query}\" tidak ditemukan.",
                ],
            ];
        }

        if ($matches->count() > 1 && strtolower((string) ($arguments['apply_to'] ?? 'one')) === 'one') {
            return [
                'error' => [
                    'success' => false,
                    'needs_choice' => true,
                    'message' => 'Beberapa produk cocok. Pilih variant_id atau set apply_to=matching.',
                    'choices' => $matches->map(fn (array $row) => [
                        'variant_id' => $row['variant_id'],
                        'label' => $row['label'],
                        'sku' => $row['sku'],
                        'stock' => $row['stock'],
                    ])->values()->all(),
                ],
            ];
        }

        return $matches->take($limit)->map(
            fn (array $row) => $this->lineFromSearch($row, $warehouseId, $target)
        )->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function saleItemsInWarehouse(
        string $branchId,
        string $warehouseId,
        float $target,
        int $limit,
    ): array {
        $products = Product::query()
            ->with(['variants' => fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')])
            ->where('branch_id', $branchId)
            ->where('is_sale_item', true)
            ->where('is_stock_item', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $lines = [];

        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $lines[] = $this->lineFromVariant($product, $variant, $warehouseId, $target);
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function lineFromSearch(array $row, string $warehouseId, float $target): array
    {
        $current = ProductVariantStock::query()
            ->where('product_variant_id', $row['variant_id'])
            ->where('warehouse_id', $warehouseId)
            ->whereNull('deleted_at')
            ->value('quantity');

        return [
            'variant_id' => $row['variant_id'],
            'product_id' => $row['product_id'],
            'unit_id' => $row['unit_id'],
            'label' => $row['label'],
            'sku' => $row['sku'],
            'current' => (float) ($current ?? $row['stock'] ?? 0),
            'target' => $target,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function lineFromVariant(Product $product, ProductVariant $variant, string $warehouseId, float $target): array
    {
        $stock = ProductVariantStock::query()
            ->where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouseId)
            ->whereNull('deleted_at')
            ->first();

        return [
            'variant_id' => $variant->id,
            'product_id' => $product->id,
            'unit_id' => $stock?->unit_id ?: $product->default_unit_id,
            'label' => $variant->display_name ?: $product->name,
            'sku' => $variant->sku,
            'current' => (float) ($stock?->quantity ?? 0),
            'target' => $target,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function applyAdjustment(array $draft, User $user): array
    {
        $warehouseId = (string) ($draft['warehouse_id'] ?? '');
        $branchId = (string) ($draft['branch_id'] ?? '');
        $companyId = $draft['company_id'] ?? null;
        $userId = $user->id;
        $notes = 'TITANIE Assistant stock adjustment';

        $adjInType = StockMutationType::query()->where('code', 'STOCK_ADJUSTMENT_IN')->first();
        $adjOutType = StockMutationType::query()->where('code', 'STOCK_ADJUSTMENT_OUT')->first();

        try {
            $adjusted = DB::transaction(function () use ($draft, $warehouseId, $branchId, $companyId, $userId, $notes, $adjInType, $adjOutType) {
                $count = 0;

                foreach ($draft['items'] as $item) {
                    $physicalQty = (float) ($item['target'] ?? 0);

                    if ($physicalQty < 0) {
                        continue;
                    }

                    $variantId = (string) $item['variant_id'];
                    $productId = (string) $item['product_id'];
                    $unitId = (string) $item['unit_id'];

                    $stock = ProductVariantStock::withTrashed()
                        ->where('product_variant_id', $variantId)
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->first();

                    $systemQty = $stock ? (float) $stock->quantity : 0;
                    $diff = $physicalQty - $systemQty;

                    if (abs($diff) < 0.000001) {
                        continue;
                    }

                    if ($stock) {
                        if ($stock->trashed()) {
                            $stock->restore();
                        }
                        $stock->update(['quantity' => $physicalQty, 'updated_by' => $userId]);
                    } else {
                        $stock = ProductVariantStock::create([
                            'product_variant_id' => $variantId,
                            'product_id' => $productId,
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'warehouse_id' => $warehouseId,
                            'unit_id' => $unitId,
                            'quantity' => $physicalQty,
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]);
                    }

                    $isPositive = $diff > 0;
                    ProductStockMovement::create([
                        'product_variant_stock_id' => $stock->id,
                        'product_variant_id' => $variantId,
                        'product_id' => $productId,
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'warehouse_id' => $warehouseId,
                        'unit_id' => $unitId,
                        'stock_mutation_type_id' => ($isPositive ? $adjInType : $adjOutType)?->id,
                        'type' => $isPositive ? 'in' : 'out',
                        'quantity' => $diff,
                        'quantity_before' => $systemQty,
                        'quantity_after' => $physicalQty,
                        'notes' => $notes,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    $count++;
                }

                return $count;
            });
        } catch (\Throwable $e) {
            Log::warning('Assistant stock adjustment failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal menyesuaikan stok. Coba lagi atau gunakan menu Stock Adjustment.',
            ];
        }

        return [
            'success' => true,
            'message' => $adjusted === 0
                ? 'Tidak ada perubahan. Semua stok sudah sesuai target.'
                : "{$adjusted} item stok berhasil disesuaikan.",
            'adjusted' => $adjusted,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function draftPayload(array $draft, string $message): array
    {
        $items = collect($draft['items'] ?? [])->map(fn (array $item) => [
            'label' => $item['label'] ?? '-',
            'sku' => $item['sku'] ?? '-',
            'current' => $item['current'] ?? 0,
            'target' => $item['target'] ?? 0,
        ])->values()->all();

        return [
            'success' => true,
            'message' => $message,
            'warehouse' => $draft['warehouse_name'] ?? null,
            'item_count' => count($items),
            'items' => $items,
        ];
    }
}
