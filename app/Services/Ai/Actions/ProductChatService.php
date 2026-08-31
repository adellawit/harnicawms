<?php

namespace App\Services\Ai\Actions;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Services\Ai\AgentContext;
use App\Services\Product\ProductStockBootstrapService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create produk dari chat (manage_record entity=product).
 *
 * Called from AgentRecordActionService::handle() when operation=create
 * and entity=product. Defaults nullable DB columns; creates a default
 * variant when the product is for sale.
 */
class ProductChatService
{
    public function __construct(
        protected ProductStockBootstrapService $stockBootstrap,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function create(array $arguments, AgentContext $context): array
    {
        $name = ChatFields::string($arguments, ['name', 'nama', 'product_name', 'item'], $arguments['name'] ?? null);
        $sku = ChatFields::string($arguments, ['sku', 'kode', 'code'], $arguments['code'] ?? null);
        $sale = ChatFields::bool($arguments, ['is_sale_item', 'sale', 'dijual', 'for_sale', 'jual']);
        $unitName = ChatFields::string($arguments, ['unit', 'satuan', 'default_unit', 'default_unit_name']);
        $unitId = ChatFields::string($arguments, ['default_unit_id', 'unit_id']);
        $description = ChatFields::string($arguments, ['description', 'deskripsi'], $arguments['description'] ?? null);

        $missing = [];
        $questions = [];

        if ($name === null) {
            $missing[] = 'name';
            $questions[] = 'Nama produknya apa?';
        }

        if ($sale === null) {
            $missing[] = 'is_sale_item';
            $questions[] = 'Produk ini dijual? Jawab ya atau tidak.';
        }

        if ($missing !== []) {
            return ChatFields::missing($missing, implode(' ', $questions));
        }

        $unit = $this->resolveUnit($unitId, $unitName, $context);
        if ($unitName !== null && $unit === null) {
            return ChatFields::missing(['unit'], 'Satuan "'.$unitName.'" tidak ditemukan. Satuannya apa? Misalnya pcs atau box.');
        }

        if ($unit === null) {
            $unit = $this->defaultUnit($context);
        }

        if ($unit === null) {
            return ChatFields::missing(['unit'], 'Satuan default belum ada di cabang ini. Satuannya apa?');
        }

        $existing = Product::query()
            ->when($context->companyId, fn ($q) => $q->where('company_id', $context->companyId))
            ->where(function ($q) use ($name, $sku) {
                $q->where('name', 'ilike', $name);
                if ($sku !== null) {
                    $q->orWhere('sku', 'ilike', $sku)->orWhere('code', 'ilike', $sku);
                }
            })
            ->first();

        if ($existing !== null) {
            $item = $this->serialize($existing);

            return [
                'success' => true,
                'applied' => false,
                'already_exists' => true,
                'entity' => 'product',
                'item' => $item,
                'items' => [$item],
                'message' => 'Produk "'.$item['label'].'" sudah ada.',
            ];
        }

        $code = $sku ?? $this->uniqueCode($name, $context);

        try {
            $product = DB::transaction(function () use ($name, $code, $sale, $unit, $description, $context) {
                $product = Product::query()->create([
                    'company_id' => $context->companyId,
                    'branch_id' => $context->branchId,
                    'default_unit_id' => $unit->id,
                    'name' => $name,
                    'code' => $code,
                    'sku' => $code,
                    'description' => $description,
                    'is_stock_item' => true,
                    'is_sale_item' => $sale,
                    'is_purchase_item' => true,
                    'min_stock' => 0,
                    'created_by' => $context->user->id,
                    'updated_by' => $context->user->id,
                ]);

                ProductVariant::query()->create([
                    'product_id' => $product->id,
                    'sku' => $code,
                    'sort_order' => 1,
                    'is_active' => true,
                    'created_by' => $context->user->id,
                    'updated_by' => $context->user->id,
                ]);

                $this->stockBootstrap->bootstrap($product->fresh(), $context->user->id);

                return $product->fresh();
            });
        } catch (QueryException $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan produk. Periksa nama atau SKU yang sudah dipakai, lalu coba lagi dari chat.',
            ];
        }

        $item = $this->serialize($product);

        return [
            'success' => true,
            'applied' => true,
            'entity' => 'product',
            'item' => $item,
            'items' => [$item],
            'message' => 'Produk "'.$item['label'].'" berhasil ditambahkan'
                .' (SKU '.$item['sku'].', '
                .($item['is_sale_item'] ? 'dijual' : 'tidak dijual')
                .').',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Product $product): array
    {
        $label = (string) $product->name;
        $sku = (string) ($product->sku ?: $product->code);

        return [
            'id' => $product->id,
            'name' => $label,
            'label' => $label,
            'code' => (string) $product->code,
            'sku' => $sku,
            'is_sale_item' => (bool) $product->is_sale_item,
            'default_unit_id' => $product->default_unit_id,
        ];
    }

    protected function resolveUnit(?string $unitId, ?string $unitName, AgentContext $context): ?ProductUnit
    {
        $query = ProductUnit::query()->whereNull('deleted_at');
        if ($context->companyId) {
            $query->where('company_id', $context->companyId);
        }

        if ($unitId !== null) {
            return (clone $query)->find($unitId);
        }

        if ($unitName === null) {
            return null;
        }

        return (clone $query)
            ->where(function ($q) use ($unitName) {
                $q->where('name', 'ilike', $unitName)
                    ->orWhere('code', 'ilike', $unitName)
                    ->orWhere('symbol', 'ilike', $unitName);
            })
            ->first();
    }

    protected function defaultUnit(AgentContext $context): ?ProductUnit
    {
        $query = ProductUnit::query()->whereNull('deleted_at');
        if ($context->companyId) {
            $query->where('company_id', $context->companyId);
        }

        return $query->orderBy('name')->first();
    }

    protected function uniqueCode(string $name, AgentContext $context): string
    {
        $slug = strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '', Str::slug($name, '')));
        $base = substr($slug !== '' ? $slug : 'PRD', 0, 12);
        $code = $base;
        $i = 2;

        $lookup = Product::withTrashed()->when(
            $context->companyId,
            fn ($q) => $q->where('company_id', $context->companyId),
        );

        while ((clone $lookup)->where(function ($q) use ($code) {
            $q->where('code', $code)->orWhere('sku', $code);
        })->exists()) {
            $code = substr($base, 0, 10).$i;
            $i++;
        }

        return $code;
    }
}
