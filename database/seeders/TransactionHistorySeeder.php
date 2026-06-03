<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\MethodPayment;
use App\Models\Product;
use App\Models\ProductPriceList;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderPayment;
use App\Services\Import\TransactionHistoryExcelParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TransactionHistorySeeder extends Seeder
{
    private const BRANCH_CODE = 'WWW-BDG-001';

    private const COMPANY_CODE = 'WWW-001';

    private const PRICE_LIST_CODE = 'REGULER';

    private const DEFAULT_CUSTOMER_GROUP_CODE = 'UMUM';

    private const WALK_IN_NAMES = [
        'general customer',
        'walk-in customer',
        'walk in customer',
        '',
    ];

    /**
     * Nama produk di Excel → [nama produk master, label varian atau null untuk default].
     *
     * @var array<string, array{0: string, 1: ?string}>
     */
    private const PRODUCT_ALIASES = [
        'ice bold 1 liter' => ['Any Menu 1 Liter', null],
        'menu 1 liter' => ['Any Menu 1 Liter', null],
        'iced cappucino (hot)' => ['Cappucino', 'Hot'],
        'iced cappucino (ice)' => ['Cappucino', 'Ice'],
        'iced long black (hot)' => ['Hot Long Black', null],
        'iced long black (ice)' => ['Hot Long Black', null],
        'orange americano' => ['Iced Orange Black', null],
        'espresso on the rock' => ['On The Rock', null],
        'iced mochaccino (ice)' => ['Mochaccino', 'Ice'],
        'iced mochaccino (hot)' => ['Mochaccino', 'Hot'],
        'latte (hot)' => ['Latte (Hot)', null],
        'latte (ice)' => ['Latte (Hot)', 'Ice'],
        'magic (hot)' => ['Magic (Hot)', null],
        'magic (ice)' => ['Magic (Hot)', 'Ice'],
        'matcha (hot)' => ['Matcha (Hot)', null],
        'matcha (ice)' => ['Matcha (Hot)', 'Ice'],
        'hot matcha latte' => ['Matcha (Hot)', null],
        'iced bold' => ['Iced Bold', null],
        'filter coffee (ice)' => ['Filter Coffee (Hot)', 'Ice'],
    ];

    /** @var array<string, string> */
    private const PAYMENT_METHOD_MAP = [
        'OVO' => 'QRIS',
        'DEBIT CARD' => 'TRANSFER',
        'CASH' => 'CASH',
        'ONLINE ORDER' => 'EWALLET',
    ];

    /** @var array<string, string> */
    private array $customerCache = [];

    private int $customersCreated = 0;

    public function run(): void
    {
        $file = base_path('docs/Transaksi 1 October 2024 - 2 June 2026.xlsx');

        $this->import($file, reset: true, exportJson: true);
    }

    public function import(string $filePath, bool $reset = false, bool $exportJson = false): bool
    {
        if (! is_file($filePath)) {
            $this->command?->error("File tidak ditemukan: {$filePath}");

            return false;
        }

        try {
            $parser = new TransactionHistoryExcelParser;
            $payload = $parser->parse($filePath);
        } catch (\Throwable $e) {
            $this->command?->error('Gagal parse Excel: '.$e->getMessage());

            return false;
        }

        if ($exportJson) {
            $jsonPath = database_path('seeders/data/transactions_history.json');
            file_put_contents(
                $jsonPath,
                json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL
            );
            $this->command?->info("JSON diekspor: {$jsonPath} ({$payload['count']} order)");
        }

        if ($reset) {
            $this->resetTransactionTables();
        }

        return $this->importPayload($payload);
    }

    private function resetTransactionTables(): void
    {
        $this->command?->warn('Mereset transaction.sales_orders dan relasinya...');

        if (Schema::hasTable('crm.customer_membership_points')) {
            DB::table('crm.customer_membership_points')->delete();
        }

        if (Schema::hasTable('transaction.payment_gateway_callbacks')) {
            DB::table('transaction.payment_gateway_callbacks')->delete();
        }

        DB::table('transaction.sales_order_item_modifiers')->delete();
        DB::table('transaction.sales_order_payments')->delete();
        DB::table('transaction.sales_order_items')->delete();
        DB::table('transaction.sales_orders')->delete();

        $this->command?->info('Tabel transaksi direset.');
    }

    /**
     * @param  array{rows: list<array<string, mixed>>}  $payload
     */
    private function importPayload(array $payload): bool
    {
        if (! is_array($payload['rows'] ?? null) || $payload['rows'] === []) {
            $this->command?->error('Data transaksi kosong atau tidak valid.');

            return false;
        }

        $branch = DB::table('master_data.business_units')
            ->where('code', self::BRANCH_CODE)
            ->where('type_code', 'BRANCH')
            ->first();

        $company = DB::table('master_data.business_units')
            ->where('code', self::COMPANY_CODE)
            ->where('type_code', 'COMPANY')
            ->first();

        if (! $branch || ! $company) {
            $this->command?->error('Cabang atau company tidak ditemukan. Jalankan BusinessUnitSeeder.');

            return false;
        }

        $priceListId = ProductPriceList::query()
            ->where('code', self::PRICE_LIST_CODE)
            ->where('is_active', true)
            ->value('id');

        if (! $priceListId) {
            $this->command?->error('Price list REGULER tidak ditemukan.');

            return false;
        }

        $methodPayments = MethodPayment::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->pluck('id', 'code');

        $variantLookup = $this->buildVariantLookup($company->id, $branch->id);
        $customVariant = $variantLookup['custom'] ?? null;
        $defaultCustomerGroupId = $this->resolveDefaultCustomerGroupId($branch->id, $priceListId);

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($payload['rows'] as $row) {
            $salesNumber = (string) ($row['legacy_id'] ?? '');

            if ($salesNumber === '') {
                $failed++;

                continue;
            }

            if (SalesOrder::where('sales_number', $salesNumber)->exists()) {
                $skipped++;

                continue;
            }

            $items = $row['items'] ?? [];

            if ($items === []) {
                $failed++;

                continue;
            }

            $resolvedItems = [];

            foreach ($items as $item) {
                $resolved = $this->resolveProduct($item['produk'] ?? '', $variantLookup, $customVariant);

                if ($resolved === null) {
                    $this->command?->warn("Produk tidak ditemukan: {$item['produk']} ({$salesNumber})");
                    $failed++;

                    continue 2;
                }

                $resolvedItems[] = array_merge($resolved, [
                    'unit_price' => (float) ($item['unit_price'] ?? $item['price'] ?? 0),
                    'discount' => (float) ($item['discount'] ?? 0),
                    'subtotal' => (float) ($item['subtotal'] ?? max(0, (float) ($item['price'] ?? 0) - (float) ($item['discount'] ?? 0))),
                ]);
            }

            $paymentCode = $this->mapPaymentCode($row['payment_method'] ?? '');
            $methodPaymentId = $methodPayments[$paymentCode] ?? $methodPayments['CASH'] ?? null;

            $paidAt = $row['paid_at'] ?? null;
            $salesDate = $row['sales_date'] ?? now()->toDateString();
            $fulfilledAt = $paidAt ?? ($salesDate.' 23:59:59');

            $grandTotal = (float) ($row['grand_total'] ?? 0);
            $itemDiscountTotal = (float) ($row['item_discount_total'] ?? 0);

            $customerResolution = $this->resolveCustomer(
                (string) ($row['customer'] ?? ''),
                $defaultCustomerGroupId,
            );

            $notes = trim((string) ($row['keterangan'] ?? ''));
            $importLabels = collect($resolvedItems)
                ->pluck('import_label')
                ->filter()
                ->unique()
                ->values();

            if ($importLabels->isNotEmpty()) {
                $importNote = 'Imported: '.$importLabels->implode(', ');
                $notes = $notes !== '' ? "{$notes} | {$importNote}" : $importNote;
            }

            try {
                DB::transaction(function () use (
                    $salesNumber,
                    $salesDate,
                    $company,
                    $branch,
                    $priceListId,
                    $methodPaymentId,
                    $paidAt,
                    $fulfilledAt,
                    $grandTotal,
                    $itemDiscountTotal,
                    $notes,
                    $row,
                    $resolvedItems,
                    $customerResolution,
                ) {
                    $order = SalesOrder::create([
                        'sales_number' => $salesNumber,
                        'sales_date' => $salesDate,
                        'order_type' => 'pos',
                        'customer_id' => $customerResolution['customer_id'],
                        'customer_name' => $customerResolution['customer_name'],
                        'company_id' => $company->id,
                        'branch_id' => $branch->id,
                        'price_list_id' => $priceListId,
                        'method_payment_id' => $methodPaymentId,
                        'status' => 'completed',
                        'payment_status' => 'paid',
                        'subtotal' => $grandTotal,
                        'tax_enabled' => false,
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'discount_type' => 'percent',
                        'discount_value' => 0,
                        'discount_amount' => 0,
                        'item_discount_total' => $itemDiscountTotal,
                        'shipping_amount' => 0,
                        'total' => $grandTotal,
                        'reference' => $salesNumber,
                        'notes' => $notes !== '' ? $notes : null,
                        'paid_at' => $paidAt,
                        'fulfilled_at' => $fulfilledAt,
                    ]);

                    foreach ($resolvedItems as $resolvedItem) {
                        SalesOrderItem::create([
                            'sales_order_id' => $order->id,
                            'product_id' => $resolvedItem['product_id'],
                            'product_variant_id' => $resolvedItem['variant_id'],
                            'unit_id' => $resolvedItem['unit_id'],
                            'quantity' => 1,
                            'unit_price' => $resolvedItem['unit_price'],
                            'discount_type' => 'nominal',
                            'discount_value' => $resolvedItem['discount'],
                            'discount_amount' => $resolvedItem['discount'],
                            'subtotal' => $resolvedItem['subtotal'],
                            'notes' => $resolvedItem['import_label'] !== null
                                ? 'Imported: '.$resolvedItem['import_label']
                                : null,
                        ]);
                    }

                    SalesOrderPayment::create([
                        'sales_order_id' => $order->id,
                        'method_payment_id' => $methodPaymentId,
                        'payment_code' => 'PAY-'.$salesNumber,
                        'amount' => $grandTotal,
                        'change_amount' => 0,
                        'status' => 'completed',
                        'notes' => $row['pembayaran'] ?? null,
                    ]);
                });

                $imported++;
            } catch (\Throwable $e) {
                $this->command?->warn("Gagal {$salesNumber}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->command?->info(
            "Transaksi historis: {$imported} diimpor, {$skipped} dilewati (sudah ada), {$failed} gagal, {$this->customersCreated} customer baru."
        );

        return $failed === 0;
    }

    /**
     * @return array{customer_id: ?string, customer_name: string}
     */
    private function resolveCustomer(string $excelCustomerName, string $defaultCustomerGroupId): array
    {
        $excelCustomerName = trim($excelCustomerName);
        $normalized = mb_strtolower($excelCustomerName);

        if (in_array($normalized, self::WALK_IN_NAMES, true)) {
            return [
                'customer_id' => null,
                'customer_name' => 'Walk-in Customer',
            ];
        }

        $cacheKey = mb_strtolower($excelCustomerName);

        if (isset($this->customerCache[$cacheKey])) {
            return $this->customerCache[$cacheKey];
        }

        $existing = Customer::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();

        if (! $existing) {
            $existing = Customer::query()
                ->where('name', 'ilike', $excelCustomerName)
                ->orderBy('created_at')
                ->first();
        }

        if ($existing) {
            $result = [
                'customer_id' => $existing->id,
                'customer_name' => $existing->name,
            ];
            $this->customerCache[$cacheKey] = $result;

            return $result;
        }

        $code = $this->generateCustomerCode($excelCustomerName, $defaultCustomerGroupId);

        $created = Customer::create([
            'customer_group_id' => $defaultCustomerGroupId,
            'code' => $code,
            'name' => $excelCustomerName,
            'customer_type' => 'UMUM',
            'is_active' => true,
            'has_app_access' => false,
        ]);

        $this->customersCreated++;

        $result = [
            'customer_id' => $created->id,
            'customer_name' => $created->name,
        ];
        $this->customerCache[$cacheKey] = $result;

        return $result;
    }

    private function resolveDefaultCustomerGroupId(string $branchId, string $priceListId): string
    {
        $group = CustomerGroup::query()
            ->where('branch_id', $branchId)
            ->where('code', self::DEFAULT_CUSTOMER_GROUP_CODE)
            ->first();

        if ($group) {
            return $group->id;
        }

        $group = CustomerGroup::create([
            'branch_id' => $branchId,
            'code' => self::DEFAULT_CUSTOMER_GROUP_CODE,
            'name' => 'Umum',
            'description' => 'Pelanggan umum — auto-created saat impor transaksi',
            'price_list_id' => $priceListId,
            'default_discount' => 0,
            'allow_credit' => false,
            'payment_term_days' => 0,
            'earn_point' => true,
            'point_multiplier' => 1,
            'sort_order' => 20,
            'is_active' => true,
        ]);

        $this->command?->info('Customer group UMUM dibuat untuk cabang Bandung.');

        return $group->id;
    }

    private function generateCustomerCode(string $name, string $customerGroupId): string
    {
        $base = Str::upper(Str::slug($name, '-'));

        if ($base === '') {
            $base = 'CUSTOMER';
        }

        $base = Str::limit($base, 40, '');

        $code = $base;
        $suffix = 1;

        while (Customer::query()
            ->where('customer_group_id', $customerGroupId)
            ->where('code', $code)
            ->exists()) {
            $code = Str::limit($base, 36, '').'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * @return array<string, array{product_id: string, variant_id: string, unit_id: string}>
     */
    private function buildVariantLookup(string $companyId, string $branchId): array
    {
        $lookup = [];

        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->with(['variants.variantAttributes.attributeValue', 'defaultUnit'])
            ->get();

        foreach ($products as $product) {
            foreach ($product->variants as $variant) {
                $varianLabel = $variant->variantAttributes
                    ->map(fn ($va) => $va->attributeValue?->value)
                    ->filter()
                    ->first();

                $keys = [mb_strtolower($product->name)];

                if ($varianLabel) {
                    $keys[] = mb_strtolower("{$product->name} ({$varianLabel})");
                }

                $entry = [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'unit_id' => $product->default_unit_id,
                ];

                foreach ($keys as $key) {
                    $lookup[$key] = $entry;
                }

                if (mb_strtolower($product->name) === 'custom') {
                    $lookup['custom'] = $entry;
                }
            }
        }

        return $lookup;
    }

    /**
     * @param  array<string, array{product_id: string, variant_id: string, unit_id: string}>  $lookup
     * @param  array{product_id: string, variant_id: string, unit_id: string}|null  $customVariant
     * @return array{product_id: string, variant_id: string, unit_id: string, import_label: ?string}|null
     */
    private function resolveProduct(string $excelName, array $lookup, ?array $customVariant): ?array
    {
        $excelName = trim($excelName);

        if ($excelName === '') {
            return null;
        }

        $key = mb_strtolower($excelName);

        if (isset($lookup[$key])) {
            return array_merge($lookup[$key], ['import_label' => null]);
        }

        if (isset(self::PRODUCT_ALIASES[$key])) {
            [$productName, $varian] = self::PRODUCT_ALIASES[$key];
            $aliasKey = $varian
                ? mb_strtolower("{$productName} ({$varian})")
                : mb_strtolower($productName);

            if (isset($lookup[$aliasKey])) {
                return array_merge($lookup[$aliasKey], ['import_label' => $excelName]);
            }
        }

        if (preg_match('/^(.+?)\s*\((Hot|Ice|Plain|Chocolate|Almond|Ham Cheese)\)\s*$/i', $excelName, $m)) {
            $parsedKey = mb_strtolower(trim($m[1]).' ('.ucfirst(strtolower($m[2])).')');

            if (isset($lookup[$parsedKey])) {
                return array_merge($lookup[$parsedKey], ['import_label' => null]);
            }
        }

        if ($customVariant !== null) {
            return array_merge($customVariant, ['import_label' => $excelName]);
        }

        return null;
    }

    private function mapPaymentCode(string $excelMethod): string
    {
        $normalized = strtoupper(trim($excelMethod));

        return self::PAYMENT_METHOD_MAP[$normalized] ?? 'CASH';
    }
}
