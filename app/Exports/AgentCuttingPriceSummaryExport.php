<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AgentCuttingPriceSummaryExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    private int $rowNumber = 0;

    public function __construct(
        private readonly Collection $rows,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'No',
            'Agent Code',
            'Agent Name',
            'Compliance %',
            'Status',
            'Transactions',
            'Transactions Violating',
            'Cutting Items',
            'Total Qty',
            'Margin Loss (Rp)',
            'Avg Gap %',
        ];
    }

    /**
     * @return list<mixed>
     */
    public function map($row): array
    {
        $this->rowNumber++;
        $compliance = (float) ($row->compliance_percent ?? 0);
        $status = $compliance >= 95
            ? 'Sangat Patuh'
            : ($compliance >= 90 ? 'Perlu Perhatian' : 'Sering Cutting Price');

        return [
            $this->rowNumber,
            $row->agent_code,
            $row->agent_name,
            $compliance,
            $status,
            (int) $row->transaction_count,
            (int) ($row->transactions_violating ?? 0),
            (int) $row->cutting_items,
            (float) $row->total_qty,
            (float) $row->total_gap_amount,
            (float) $row->avg_gap_percent,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
