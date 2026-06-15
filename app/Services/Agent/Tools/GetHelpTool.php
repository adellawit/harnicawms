<?php

namespace App\Services\Agent\Tools;

use App\Services\Agent\AgentContext;

class GetHelpTool extends AbstractAgentTool
{
    public function name(): string
    {
        return 'get_help';
    }

    public function description(): string
    {
        return 'Return assistant capabilities and example questions for the WMS admin user.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [],
            'properties' => [],
        ];
    }

    public function requiredPermission(): ?array
    {
        return null;
    }

    public function execute(array $arguments, AgentContext $context): array
    {
        return [
            'success' => true,
            'branch' => $this->branchLabel($context),
            'capabilities' => [
                'Cari produk (nama/SKU) beserta harga dan stok',
                'Cek stok produk di cabang aktif',
                'Cari customer berdasarkan nama',
                'Ringkasan penjualan per tanggal',
            ],
            'examples' => [
                'Cari produk kopi arabica',
                'Stok susu UHT berapa?',
                'Customer Budi',
                'Penjualan hari ini',
            ],
            'limitations' => [
                'Read-only — tidak bisa membuat transaksi atau mengubah data',
                'Data dibatasi ke cabang aktif user',
            ],
        ];
    }
}
