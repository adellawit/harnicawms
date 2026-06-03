<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected string $branchId;

    protected int $rowNumber = 0;

    public function __construct(string $branchId)
    {
        $this->branchId = $branchId;
    }

    public function collection()
    {
        return Product::with([
            'nature:id,name',
            'defaultUnit:id,name,symbol',
            'unitConversions' => fn ($q) => $q->where('conversion_level', 1)->with('toUnit:id,name,symbol'),
            'stocks' => fn ($q) => $q->where('branch_id', $this->branchId),
            'prices' => fn ($q) => $q->where('branch_id', $this->branchId),
        ])
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'SKU',
            'Kode',
            'Product',
            'Satuan Besar',
            'Konversi',
            'Satuan Kecil',
            'Jumlah (Satuan Besar)',
            'Harga Beli Satuan Besar',
            'Jumlah Minimum (Satuan Besar)',
            'Nature',
        ];
    }

    public function map($product): array
    {
        $this->rowNumber++;

        $conversion = $product->unitConversions->first();
        $stock = $product->stocks->first();
        $price = $product->prices->first();

        $stockQty = $stock ? (float) $stock->quantity : 0;

        if ($stock && $conversion && $product->default_unit_id && $stock->unit_id !== $product->default_unit_id) {
            $converted = $product->convertQuantity($stockQty, $stock->unit_id, $product->default_unit_id);
            if ($converted !== null) {
                $stockQty = $converted;
            }
        }

        $purchasePrice = 0;
        if ($price) {
            $purchasePrice = (float) $price->purchase_price;
            if ($price->unit_id !== $product->default_unit_id && $conversion) {
                $converted = $product->convertQuantity($purchasePrice, $price->unit_id, $product->default_unit_id);
                if ($converted !== null) {
                    $purchasePrice = $converted;
                }
            }
        }

        return [
            $this->rowNumber,
            $product->sku ?? '',
            $product->code ?? '',
            $product->name,
            $product->defaultUnit?->name ?? '',
            $conversion ? (float) $conversion->conversion_factor : '',
            $conversion?->toUnit?->name ?? '',
            $stockQty ?: '',
            $purchasePrice ?: '',
            (float) ($product->min_stock ?? 0) ?: '',
            $product->nature?->name ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
