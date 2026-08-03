<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountMapping;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountMappingService
{
    /**
     * @return array<string, array{label:string,description?:string,icon?:string}>
     */
    public function modules(): array
    {
        return config('accounting.modules', []);
    }

    /**
     * @return array<string, array{label:string,module:string,expected_type:string,required:bool}>
     */
    public function mappingKeys(): array
    {
        return config('accounting.mapping_keys', []);
    }

    /**
     * Keys grouped by module, preserving module order from config.
     *
     * @return array<string, array{meta: array, keys: array<string, array>}>
     */
    public function mappingKeysGroupedByModule(): array
    {
        $modules = $this->modules();
        $grouped = [];

        foreach ($modules as $moduleKey => $moduleMeta) {
            $grouped[$moduleKey] = [
                'meta' => $moduleMeta,
                'keys' => [],
            ];
        }

        foreach ($this->mappingKeys() as $key => $meta) {
            $module = $meta['module'] ?? 'general';
            if (! isset($grouped[$module])) {
                $grouped[$module] = [
                    'meta' => ['label' => ucfirst($module), 'description' => '', 'icon' => 'ti ti-folder'],
                    'keys' => [],
                ];
            }
            $grouped[$module]['keys'][$key] = $meta;
        }

        return array_filter($grouped, fn (array $g) => $g['keys'] !== []);
    }

    public function assertAccountUsable(ChartOfAccount $account, string $mappingKey): void
    {
        $meta = $this->mappingKeys()[$mappingKey] ?? null;
        if (! $meta) {
            throw ValidationException::withMessages([
                $mappingKey => 'Unknown mapping key.',
            ]);
        }

        if ($account->is_header) {
            throw ValidationException::withMessages([
                $mappingKey => 'Header accounts cannot be used for mapping.',
            ]);
        }

        if (! $account->is_active) {
            throw ValidationException::withMessages([
                $mappingKey => 'Account is inactive.',
            ]);
        }

        if (($meta['expected_type'] ?? null) && $account->account_type !== $meta['expected_type']) {
            $expected = ChartOfAccount::ACCOUNT_TYPES[$meta['expected_type']] ?? $meta['expected_type'];
            throw ValidationException::withMessages([
                $mappingKey => "Account for {$meta['label']} must be type {$expected}.",
            ]);
        }
    }

    /**
     * Upsert mappings for a company. Empty account_id removes soft-deleted or deletes existing.
     *
     * @param  array<string, string|null>  $keyToAccountId
     */
    public function saveAll(string $companyId, array $keyToAccountId, ?string $userId = null): int
    {
        $keys = $this->mappingKeys();
        $saved = 0;

        DB::transaction(function () use ($companyId, $keyToAccountId, $userId, $keys, &$saved) {
            foreach ($keys as $key => $meta) {
                $accountId = $keyToAccountId[$key] ?? null;
                $accountId = is_string($accountId) && $accountId !== '' ? $accountId : null;

                $existing = AccountMapping::query()
                    ->where('company_id', $companyId)
                    ->where('mapping_key', $key)
                    ->first();

                if (! $accountId) {
                    if ($existing) {
                        $existing->deleted_by = $userId;
                        $existing->save();
                        $existing->delete();
                    }

                    continue;
                }

                $account = ChartOfAccount::query()
                    ->where('company_id', $companyId)
                    ->where('id', $accountId)
                    ->first();

                if (! $account) {
                    throw ValidationException::withMessages([
                        $key => 'Account not found for this company.',
                    ]);
                }

                $this->assertAccountUsable($account, $key);

                if ($existing) {
                    $existing->update([
                        'account_id' => $account->id,
                        'updated_by' => $userId,
                    ]);
                } else {
                    AccountMapping::create([
                        'company_id' => $companyId,
                        'mapping_key' => $key,
                        'account_id' => $account->id,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                $saved++;
            }
        });

        return $saved;
    }

    /**
     * @param  array<string, string>  $codeToId
     */
    public function seedDefaultsFromCodes(string $companyId, array $codeToId, ?string $userId = null): int
    {
        $defaults = config('accounting.default_mapping_codes', []);
        $payload = [];

        foreach ($defaults as $code => $mappingKey) {
            if (! isset($codeToId[$code])) {
                continue;
            }
            $payload[$mappingKey] = $codeToId[$code];
        }

        $keyCodes = config('accounting.default_mapping_key_codes', []);
        foreach ($keyCodes as $mappingKey => $code) {
            if (! isset($codeToId[$code])) {
                continue;
            }
            $payload[$mappingKey] = $codeToId[$code];
        }

        if ($payload === []) {
            return 0;
        }

        // Merge with existing selections so we don't wipe manually set empty keys
        $existing = AccountMapping::query()
            ->where('company_id', $companyId)
            ->pluck('account_id', 'mapping_key')
            ->all();

        foreach ($this->mappingKeys() as $key => $_) {
            if (! array_key_exists($key, $payload) && isset($existing[$key])) {
                $payload[$key] = $existing[$key];
            }
        }

        return $this->saveAll($companyId, $payload, $userId);
    }
}
