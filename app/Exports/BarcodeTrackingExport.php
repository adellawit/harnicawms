<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BarcodeTrackingExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
            'Serial Barcode',
            'Product Code',
            'Product',
            'Variant SKU',
            'Unit',
            'Sales Order',
            'Sales Date',
            'Dispatch Date',
            'Customer',
            'Reseller',
            'Agent Code',
            'Agent',
            'Branch',
            'Scanned By',
            'Scanned At',
        ];
    }

    /**
     * @return list<mixed>
     */
    public function map($row): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $row->serial_number,
            $row->product_code,
            $row->product_name,
            $row->variant_sku,
            $row->unit_symbol ?: $row->unit_name,
            $row->sales_number,
            $row->sales_date ? date('d/m/Y', strtotime($row->sales_date)) : null,
            $row->dispatched_at ? date('d/m/Y H:i', strtotime($row->dispatched_at)) : null,
            $row->customer_name,
            $row->reseller_code
                ? $row->reseller_code.($row->reseller_name ? ' · '.$row->reseller_name : '')
                : null,
            $row->agent_code,
            $row->agent_name,
            $row->branch_name,
            trim(($row->scanned_by_first_name ?? '').' '.($row->scanned_by_last_name ?? '')),
            $row->scanned_at ? date('d/m/Y H:i:s', strtotime($row->scanned_at)) : null,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
