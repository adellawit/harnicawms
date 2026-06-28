<?php

namespace App\Services\MasterData;

use App\Models\BusinessUnit;
use InvalidArgumentException;

class BusinessUnitCodeService
{
    public function generate(string $typeCode, ?string $parentId = null): string
    {
        return match ($typeCode) {
            'HOLDING' => $this->generateHoldingCode(),
            'COMPANY' => $this->generateCompanyCode($parentId),
            'BRANCH' => $this->generateBranchCode($parentId),
            default => throw new InvalidArgumentException("Unsupported business unit type: {$typeCode}"),
        };
    }

    public function generateHoldingCode(): string
    {
        $prefix = 'HLD-';

        $last = BusinessUnit::withTrashed()
            ->where('type_code', 'HOLDING')
            ->where('code', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(code) DESC, code DESC')
            ->value('code');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function generateCompanyCode(?string $holdingId): string
    {
        $holding = $holdingId ? BusinessUnit::query()->find($holdingId) : null;
        $prefix = ($holding?->code ?: 'CO').'-CO-';

        $last = BusinessUnit::withTrashed()
            ->where('type_code', 'COMPANY')
            ->when($holdingId, fn ($query) => $query->where('parent_id', $holdingId))
            ->where('code', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(code) DESC, code DESC')
            ->value('code');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function generateBranchCode(?string $companyId): string
    {
        $company = $companyId ? BusinessUnit::query()->find($companyId) : null;
        $prefix = ($company?->code ?: 'BR').'-BR-';

        $last = BusinessUnit::withTrashed()
            ->where('type_code', 'BRANCH')
            ->when($companyId, fn ($query) => $query->where('parent_id', $companyId))
            ->where('code', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(code) DESC, code DESC')
            ->value('code');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
