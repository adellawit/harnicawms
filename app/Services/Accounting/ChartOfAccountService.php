<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountMapping;
use App\Models\Accounting\CashFlowCategory;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChartOfAccountService
{
    /**
     * Flatten COA into depth-first tree rows for list display.
     *
     * @param  \Illuminate\Support\Collection<int, ChartOfAccount>  $accounts
     * @return list<array{account: ChartOfAccount, depth: int}>
     */
    public function buildTreeRows($accounts): array
    {
        $byParent = $accounts->groupBy(fn (ChartOfAccount $a) => $a->parent_id ?? 'root');

        $rows = [];
        $walk = function (?string $parentId, int $depth) use (&$walk, &$rows, $byParent) {
            $key = $parentId ?? 'root';
            $children = ($byParent->get($key) ?? collect())
                ->sortBy('code', SORT_NATURAL)
                ->values();

            foreach ($children as $account) {
                $rows[] = [
                    'account' => $account,
                    'depth' => $depth,
                ];
                $walk($account->id, $depth + 1);
            }
        };

        $walk(null, 0);

        // Orphan nodes (parent missing from filtered set) — append at root
        $seen = collect($rows)->map(fn ($r) => $r['account']->id)->all();
        foreach ($accounts->sortBy('code', SORT_NATURAL) as $account) {
            if (! in_array($account->id, $seen, true)) {
                $rows[] = [
                    'account' => $account,
                    'depth' => 0,
                ];
            }
        }

        return $rows;
    }

    /**
     * Suggest next account code for company (+ optional parent / account type).
     * Ensures uniqueness among non-deleted accounts.
     */
    public function suggestNextCode(string $companyId, ?string $parentId = null, ?string $accountType = null): string
    {
        $existing = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->pluck('code')
            ->all();
        $existingSet = array_fill_keys($existing, true);

        if ($parentId) {
            $parent = ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->findOrFail($parentId);

            $prefix = $parent->code;
            $siblings = ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->where('parent_id', $parentId)
                ->pluck('code');

            $maxSeq = 0;
            $pad = strlen($prefix) <= 1 ? 1 : 2;

            foreach ($siblings as $code) {
                if (! str_starts_with((string) $code, $prefix) || strlen((string) $code) <= strlen($prefix)) {
                    continue;
                }
                $suffix = substr((string) $code, strlen($prefix));
                if (ctype_digit($suffix)) {
                    $maxSeq = max($maxSeq, (int) $suffix);
                    $pad = max($pad, strlen($suffix));
                }
            }

            for ($i = 1; $i <= 9999; $i++) {
                $candidate = $prefix.str_pad((string) ($maxSeq + $i), $pad, '0', STR_PAD_LEFT);
                if (! isset($existingSet[$candidate])) {
                    return $candidate;
                }
            }
        }

        $typeRoots = [
            'asset' => '1',
            'liability' => '2',
            'equity' => '3',
            'revenue' => '4',
            'expense' => '5',
        ];
        $base = $typeRoots[$accountType ?? ''] ?? '9';

        if (! isset($existingSet[$base])) {
            return $base;
        }

        for ($i = 1; $i <= 99; $i++) {
            $candidate = $base.(string) $i;
            if (! isset($existingSet[$candidate])) {
                return $candidate;
            }
        }

        return $base.substr((string) time(), -4);
    }

    /**
     * Template COA dasar (PSAK-style headers + detail accounts for mapping).
     *
     * @return list<array<string, mixed>>
     */
    public function templateAccounts(): array
    {
        $base = fn (array $row) => array_merge([
            'cash_flow_category_code' => 'none',
            'is_contra_account' => false,
            'is_cash_bank' => false,
        ], $row);

        return [
            $base(['code' => '1', 'name' => 'ASET', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_header' => true, 'parent_code' => null]),
            $base(['code' => '11', 'name' => 'Aset Lancar', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_header' => true, 'parent_code' => '1']),
            $base(['code' => '1101', 'name' => 'Kas', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_header' => false, 'parent_code' => '11', 'cash_flow_category_code' => 'operating', 'is_cash_bank' => true]),
            $base(['code' => '1102', 'name' => 'Bank', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_header' => false, 'parent_code' => '11', 'cash_flow_category_code' => 'operating', 'is_cash_bank' => true]),
            $base(['code' => '1103', 'name' => 'Piutang Usaha', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_header' => false, 'parent_code' => '11', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '1104', 'name' => 'PPN Masukan', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_header' => false, 'parent_code' => '11', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '1105', 'name' => 'PPh 23 Dibayar di Muka', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_header' => false, 'parent_code' => '11', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '12', 'name' => 'Persediaan', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_header' => true, 'parent_code' => '1']),
            $base(['code' => '1201', 'name' => 'Persediaan Barang', 'account_type' => 'asset', 'normal_balance' => 'debit', 'is_header' => false, 'parent_code' => '12', 'cash_flow_category_code' => 'operating']),

            $base(['code' => '2', 'name' => 'LIABILITAS', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => true, 'parent_code' => null]),
            $base(['code' => '21', 'name' => 'Liabilitas Jangka Pendek', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => true, 'parent_code' => '2']),
            $base(['code' => '2101', 'name' => 'Hutang Usaha', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '21', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '2102', 'name' => 'PPN Keluaran', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '21', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '2103', 'name' => 'Utang PPN', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '21', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '2104', 'name' => 'Utang PPh 21', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '21', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '2105', 'name' => 'Utang PPh 22', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '21', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '2106', 'name' => 'Utang PPh 23', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '21', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '2107', 'name' => 'Utang PPh Final 4(2)', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '21', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '2108', 'name' => 'Transit Pembelian (GRNI)', 'account_type' => 'liability', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '21', 'cash_flow_category_code' => 'operating']),

            $base(['code' => '3', 'name' => 'EKUITAS', 'account_type' => 'equity', 'normal_balance' => 'credit', 'is_header' => true, 'parent_code' => null]),
            $base(['code' => '3101', 'name' => 'Modal Disetor', 'account_type' => 'equity', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '3', 'cash_flow_category_code' => 'financing']),
            $base(['code' => '3201', 'name' => 'Laba Ditahan', 'account_type' => 'equity', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '3', 'cash_flow_category_code' => 'none']),

            $base(['code' => '4', 'name' => 'PENDAPATAN', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'is_header' => true, 'parent_code' => null]),
            $base(['code' => '4101', 'name' => 'Pendapatan Penjualan', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '4', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '4102', 'name' => 'Potongan Penjualan', 'account_type' => 'revenue', 'normal_balance' => 'debit', 'is_header' => false, 'parent_code' => '4', 'cash_flow_category_code' => 'operating', 'is_contra_account' => true]),
            $base(['code' => '4201', 'name' => 'Pendapatan Penyesuaian Stok', 'account_type' => 'revenue', 'normal_balance' => 'credit', 'is_header' => false, 'parent_code' => '4', 'cash_flow_category_code' => 'operating']),

            $base(['code' => '5', 'name' => 'BEBAN', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_header' => true, 'parent_code' => null]),
            $base(['code' => '5101', 'name' => 'Harga Pokok Penjualan', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_header' => false, 'parent_code' => '5', 'cash_flow_category_code' => 'operating']),
            $base(['code' => '5201', 'name' => 'Beban Penyesuaian Stok', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_header' => false, 'parent_code' => '5', 'cash_flow_category_code' => 'operating']),
        ];
    }

    public function resolveLevel(?string $parentId): int
    {
        if (! $parentId) {
            return 1;
        }

        $parent = ChartOfAccount::query()->findOrFail($parentId);

        return ((int) $parent->level) + 1;
    }

    public function assertCanDelete(ChartOfAccount $account): void
    {
        $hasChildren = ChartOfAccount::query()
            ->where('parent_id', $account->id)
            ->exists();

        if ($hasChildren) {
            throw ValidationException::withMessages([
                'account' => 'Akun tidak dapat dihapus karena masih memiliki sub-akun.',
            ]);
        }

        $usedInMapping = AccountMapping::query()
            ->where('account_id', $account->id)
            ->exists();

        if ($usedInMapping) {
            throw ValidationException::withMessages([
                'account' => 'Akun tidak dapat dihapus karena masih dipakai di Account Mapping.',
            ]);
        }
    }

    /**
     * Seed template COA for a company (skip existing codes). Optionally fill default mappings.
     *
     * @return array{created:int,skipped:int,mappings:int}
     */
    public function seedTemplate(string $companyId, ?string $userId = null, bool $seedMappings = true): array
    {
        $created = 0;
        $skipped = 0;
        $mappings = 0;
        $codeToId = [];

        DB::transaction(function () use ($companyId, $userId, $seedMappings, &$created, &$skipped, &$mappings, &$codeToId) {
            $categoryIds = CashFlowCategory::query()
                ->pluck('id', 'code')
                ->all();
            $noneId = $categoryIds['none'] ?? null;

            $existing = ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->get()
                ->keyBy('code');

            foreach ($this->templateAccounts() as $row) {
                if ($existing->has($row['code'])) {
                    $codeToId[$row['code']] = $existing->get($row['code'])->id;
                    $skipped++;

                    continue;
                }

                $parentId = null;
                if ($row['parent_code']) {
                    $parentId = $codeToId[$row['parent_code']]
                        ?? $existing->get($row['parent_code'])?->id;

                    if (! $parentId) {
                        throw ValidationException::withMessages([
                            'template' => "Parent code {$row['parent_code']} belum tersedia untuk {$row['code']}.",
                        ]);
                    }
                }

                $level = $parentId
                    ? $this->resolveLevel($parentId)
                    : 1;

                $cfCode = $row['cash_flow_category_code'] ?? 'none';
                $cfId = $categoryIds[$cfCode] ?? $noneId;

                $account = ChartOfAccount::create([
                    'company_id' => $companyId,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'account_type' => $row['account_type'],
                    'normal_balance' => $row['normal_balance'],
                    'cash_flow_category_id' => $cfId,
                    'parent_id' => $parentId,
                    'level' => $level,
                    'is_header' => $row['is_header'],
                    'is_contra_account' => (bool) ($row['is_contra_account'] ?? false),
                    'is_cash_bank' => (bool) ($row['is_cash_bank'] ?? false),
                    'is_active' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $codeToId[$row['code']] = $account->id;
                $created++;
            }

            if ($seedMappings) {
                $mappings = app(AccountMappingService::class)
                    ->seedDefaultsFromCodes($companyId, $codeToId, $userId);
            }
        });

        return compact('created', 'skipped', 'mappings');
    }
}
