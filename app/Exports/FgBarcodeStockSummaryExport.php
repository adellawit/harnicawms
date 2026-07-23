<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FgBarcodeStockSummaryExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
            'Product Code',
            'Product',
            'Variant SKU',
            'Unit',
            'Warehouse',
            'Stock FG',
            'Serial Ready',
            'Selisih',
            'Status',
        ];
    }

    /**
     * @return list<mixed>
     */
    public function map($row): array
    {
        $this->rowNumber++;

        $status = match ($row->status ?? '') {
            'surplus' => 'Serial surplus',
            'shortage' => 'Serial shortage',
            default => 'OK',
        };

        return [
            $this->rowNumber,
            $row->product_code,
            $row->product_name,
            $row->variant_sku,
            $row->unit_symbol ?: $row->unit_name,
            $row->warehouse_code
                ? $row->warehouse_code.($row->warehouse_name ? ' · '.$row->warehouse_name : '')
                : null,
            (float) $row->stock_qty,
            (int) $row->serial_ready,
            (float) $row->variance,
            $status,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
