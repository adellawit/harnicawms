<?php

namespace App\Imports;

use App\Models\Parameter;
use App\Models\ParameterDetail;
use App\Models\Product;
use App\Models\ProductNature;
use App\Models\ProductPrice;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantStock;
use App\Models\ProductStockMovement;
use App\Models\ProductUnit;
use App\Models\ProductUnitConversion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductImport implements ToCollection, WithHeadingRow
{
    protected string $userId;

    protected string $branchId;

    protected ?string $companyId;

    protected array $errors = [];

    protected int $imported = 0;

    protected int $skipped = 0;

    public function __construct(string $userId, string $branchId, ?string $companyId = null)
    {
        $this->userId = $userId;
        $this->branchId = $branchId;
        $this->companyId = $companyId;
    }

    public function collection(Collection $rows)
    {
        $unitCache = collect(ProductUnit::whereNull('deleted_at')->get()->all());
        $natureCache = collect(ProductNature::whereNull('deleted_at')->get()->all());

        $itemTypeId = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'ITEM_TYPE'))
            ->where('key', 'raw_material')->value('id');
        $productNatureId = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PRODUCT_NATURE'))
            ->where('key', 'inventory')->value('id');
        $procurementTypeId = ParameterDetail::whereHas('parameter', fn ($q) => $q->where('code', 'PROCUREMENT_TYPE'))
            ->where('key', 'purchase')->value('id');

        $validatedRows = [];
        $namesInFile = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $name = trim($row['bahan_baku'] ?? '');
            if (empty($name)) {
                continue;
            }

            $bigUnitName = trim($row['satuan_besar'] ?? '');
            $smallUnitName = trim($row['satuan_kecil'] ?? '');
            $conversionFactor = $this->parseNumber($row['konversi'] ?? '');
            $quantity = $this->parseNumber($row['jumlah_satuan_besar'] ?? '');
            $purchasePrice = $this->parseNumber($row['harga_beli_satuan_besar'] ?? '');
            $minStock = $this->parseNumber($row['jumlah_minimum_satuan_besar'] ?? '');
            $natureName = trim($row['kategori'] ?? $row['nature'] ?? '');
            $code = trim($row['kode'] ?? '');
            $sku = trim($row['sku'] ?? '');

            if (empty($bigUnitName)) {
                $this->errors[] = "Baris {$rowNum}: Satuan Besar kosong untuk '{$name}'.";
                continue;
            }

            $nameLower = mb_strtolower($name);
            if (in_array($nameLower, $namesInFile)) {
                $this->errors[] = "Baris {$rowNum}: Nama '{$name}' duplikat dalam file.";
                continue;
            }

            if (Product::withTrashed()->where('branch_id', $this->branchId)->whereRaw('LOWER(name) = ?', [$nameLower])->exists()) {
                $this->errors[] = "Baris {$rowNum}: Nama '{$name}' sudah ada di database.";
                continue;
            }

            if (! empty($sku) && Product::withTrashed()->where('sku', $sku)->exists()) {
                $this->errors[] = "Baris {$rowNum}: SKU '{$sku}' sudah digunakan.";
                continue;
            }

            if (! empty($code) && Product::withTrashed()->where('code', $code)->exists()) {
                $this->errors[] = "Baris {$rowNum}: Kode '{$code}' sudah digunakan.";
                continue;
            }

            $namesInFile[] = $nameLower;
            $validatedRows[] = [
                'name' => $name,
                'big_unit_name' => $bigUnitName,
                'small_unit_name' => $smallUnitName,
                'conversion_factor' => $conversionFactor,
                'quantity' => $quantity,
                'purchase_price' => $purchasePrice,
                'min_stock' => $minStock,
                'nature_name' => $natureName,
                'code' => $code,
                'sku' => $sku,
            ];
        }

        if (! empty($this->errors)) {
            $this->skipped = count($this->errors);

            return;
        }

        if (empty($validatedRows)) {
            return;
        }

        DB::beginTransaction();
        try {
            foreach ($validatedRows as $data) {
                $bigUnit = $this->findUnit($unitCache, $data['big_unit_name']);
                if (! $bigUnit) {
                    $bigUnit = ProductUnit::create([
                        'company_id' => $this->companyId,
                        'branch_id' => $this->branchId,
                        'name' => $data['big_unit_name'],
                        'code' => strtolower(str_replace(' ', '_', $data['big_unit_name'])),
                        'symbol' => $data['big_unit_name'],
                        'created_by' => $this->userId,
                        'updated_by' => $this->userId,
                    ]);
                    $unitCache->push($bigUnit);
                }

                $nature = null;
                if (! empty($data['nature_name'])) {
                    $nature = $natureCache->first(fn ($n) => mb_strtolower($n->name) === mb_strtolower($data['nature_name']));
                    if (! $nature) {
                        $nature = ProductNature::create([
                            'company_id' => $this->companyId,
                            'branch_id' => $this->branchId,
                            'name' => $data['nature_name'],
                            'code' => strtolower(str_replace(' ', '_', $data['nature_name'])),
                            'created_by' => $this->userId,
                            'updated_by' => $this->userId,
                        ]);
                        $natureCache->push($nature);
                    }
                }

                $product = Product::create([
                    'company_id' => $this->companyId,
                    'branch_id' => $this->branchId,
                    'nature_id' => $nature?->id,
                    'item_type_id' => $itemTypeId,
                    'product_nature_id' => $productNatureId,
                    'procurement_type_id' => $procurementTypeId,
                    'default_unit_id' => $bigUnit->id,
                    'name' => $data['name'],
                    'code' => $data['code'] ?: null,
                    'sku' => ! empty($data['sku']) ? $data['sku'] : $this->generateSku(),
                    'is_stock_item' => true,
                    'is_sale_item' => false,
                    'is_purchase_item' => true,
                    'min_stock' => $data['min_stock'] ?? 0,
                    'max_stock' => null,
                    'created_by' => $this->userId,
                    'updated_by' => $this->userId,
                ]);

                $smallUnit = null;
                if (! empty($data['small_unit_name']) && $data['conversion_factor'] && $data['conversion_factor'] > 0) {
                    $smallUnit = $this->findUnit($unitCache, $data['small_unit_name']);
                    if (! $smallUnit) {
                        $smallUnit = ProductUnit::create([
                            'company_id' => $this->companyId,
                            'branch_id' => $this->branchId,
                            'name' => $data['small_unit_name'],
                            'code' => strtolower(str_replace(' ', '_', $data['small_unit_name'])),
                            'symbol' => $data['small_unit_name'],
                            'created_by' => $this->userId,
                            'updated_by' => $this->userId,
                        ]);
                        $unitCache->push($smallUnit);
                    }

                    ProductUnitConversion::create([
                        'product_id' => $product->id,
                        'from_unit_id' => $bigUnit->id,
                        'to_unit_id' => $smallUnit->id,
                        'conversion_factor' => $data['conversion_factor'],
                        'conversion_level' => 1,
                        'created_by' => $this->userId,
                        'updated_by' => $this->userId,
                    ]);
                }

                $convFactor = ($smallUnit && $data['conversion_factor'] > 0) ? $data['conversion_factor'] : 1;
                $storageUnitId = $smallUnit ? $smallUnit->id : $bigUnit->id;

                // Create default variant for the product
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'barcode' => $product->code ?? substr($product->id, 0, 13),
                    'purchase_price' => 0,
                    'selling_price' => 0,
                    'is_active' => true,
                    'created_by' => $this->userId,
                    'updated_by' => $this->userId,
                ]);

                if ($data['quantity'] !== null && $data['quantity'] > 0) {
                    $qtySmall = $data['quantity'] * $convFactor;
                    $stock = ProductVariantStock::create([
                        'product_variant_id' => $variant->id,
                        'product_id' => $product->id,
                        'company_id' => $this->companyId,
                        'branch_id' => $this->branchId,
                        'unit_id' => $storageUnitId,
                        'quantity' => $qtySmall,
                        'created_by' => $this->userId,
                        'updated_by' => $this->userId,
                    ]);

                    ProductStockMovement::create([
                        'product_variant_stock_id' => $stock->id,
                        'product_variant_id' => $variant->id,
                        'product_id' => $product->id,
                        'company_id' => $this->companyId,
                        'branch_id' => $this->branchId,
                        'unit_id' => $storageUnitId,
                        'stock_mutation_type_id' => $this->getInitialBalanceMutationId(),
                        'type' => 'in',
                        'quantity' => $qtySmall,
                        'quantity_before' => 0,
                        'quantity_after' => $qtySmall,
                        'notes' => 'Import Excel - Saldo Awal',
                        'created_by' => $this->userId,
                        'updated_by' => $this->userId,
                    ]);
                }

                if ($data['purchase_price'] !== null && $data['purchase_price'] > 0) {
                    $priceSmall = $data['purchase_price'] / $convFactor;

                    // Update variant purchase price
                    $variant->update([
                        'purchase_price' => $priceSmall,
                        'updated_by' => $this->userId,
                    ]);

                    // Create variant price
                    ProductVariantPrice::updateOrCreate(
                        [
                            'variant_id' => $variant->id,
                            'branch_id' => $this->branchId,
                            'unit_id' => $storageUnitId,
                        ],
                        [
                            'company_id' => $this->companyId,
                            'purchase_price' => $priceSmall,
                            'selling_price' => 0,
                            'created_by' => $this->userId,
                            'updated_by' => $this->userId,
                        ]
                    );
                }

                if ($data['min_stock'] !== null && $data['min_stock'] > 0) {
                    $product->update(['min_stock' => $data['min_stock'] * $convFactor]);
                }

                $this->imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->imported = 0;
            $this->errors[] = "Gagal import: {$e->getMessage()}";
        }
    }

    protected function findUnit(Collection $units, string $name): ?ProductUnit
    {
        if (empty($name)) {
            return null;
        }
        $lower = mb_strtolower($name);

        return $units->first(fn ($u) =>
            mb_strtolower($u->name) === $lower ||
            mb_strtolower($u->symbol ?? '') === $lower ||
            mb_strtolower($u->code ?? '') === $lower
        );
    }

    protected function parseNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        $str = str_replace(' ', '', trim((string) $value));
        $lastComma = strrpos($str, ',');
        $lastDot = strrpos($str, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                $str = str_replace(',', '', $str);
            }
        } elseif ($lastComma !== false) {
            $afterComma = substr($str, $lastComma + 1);
            if (strlen($afterComma) === 3 && ctype_digit($afterComma)) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        }

        return is_numeric($str) ? (float) $str : null;
    }

    protected function generateSku(): string
    {
        $prefix = date('dmy') . 'TH';
        $last = Product::withTrashed()
            ->where('sku', 'like', $prefix . '%')
            ->orderByRaw('LENGTH(sku) DESC, sku DESC')
            ->value('sku');
        $seq = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    protected function getInitialBalanceMutationId(): ?string
    {
        return DB::table('public.stock_mutation_types')
            ->where('code', 'INITIAL_BALANCE')
            ->value('id');
    }

    public function getImported(): int
    {
        return $this->imported;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
