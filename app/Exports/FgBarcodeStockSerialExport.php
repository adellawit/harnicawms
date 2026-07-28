<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FgBarcodeStockSerialExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
            'Created At',
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
            $row->created_at ? date('d/m/Y H:i', strtotime((string) $row->created_at)) : null,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
