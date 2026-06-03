<?php

namespace App\Services\Import;

use DateTime;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionHistoryExcelParser
{
    /**
     * @return array{
     *   source: string,
     *   sheet: string,
     *   branch_default: string,
     *   count: int,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function parse(string $filePath, string $sheetName = 'Net Revenue'): array
    {
        if (! is_file($filePath)) {
            throw new \InvalidArgumentException("File tidak ditemukan: {$filePath}");
        }

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName($sheetName);

        if (! $sheet instanceof Worksheet) {
            throw new \InvalidArgumentException("Sheet tidak ditemukan: {$sheetName}");
        }

        $orders = [];
        $currentIndex = null;

        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            $legacyId = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getValue());
            $productName = trim((string) $sheet->getCellByColumnAndRow(8, $row)->getValue());

            if ($productName === '') {
                continue;
            }

            $item = [
                'produk' => $productName,
                'price' => $this->parseNumber($sheet->getCellByColumnAndRow(10, $row)->getValue()),
                'discount' => $this->parseNumber($sheet->getCellByColumnAndRow(11, $row)->getValue()),
            ];

            if ($legacyId !== '') {
                $orders[] = [
                    'legacy_id' => $legacyId,
                    'sales_date' => $this->parseDate($sheet->getCellByColumnAndRow(3, $row)->getValue()),
                    'jenis' => trim((string) $sheet->getCellByColumnAndRow(5, $row)->getValue()),
                    'status' => trim((string) $sheet->getCellByColumnAndRow(6, $row)->getValue()),
                    'customer' => trim((string) $sheet->getCellByColumnAndRow(7, $row)->getValue()),
                    'grand_total' => $this->parseNumber($sheet->getCellByColumnAndRow(12, $row)->getValue()),
                    'pembayaran' => trim((string) $sheet->getCellByColumnAndRow(13, $row)->getValue()),
                    'keterangan' => trim((string) $sheet->getCellByColumnAndRow(14, $row)->getValue()),
                    'payment_method' => trim((string) $sheet->getCellByColumnAndRow(13, $row)->getValue()),
                    'paid_at' => $this->parseDateTime($sheet->getCellByColumnAndRow(4, $row)->getValue()),
                    'items' => [$item],
                ];
                $currentIndex = count($orders) - 1;
            } elseif ($currentIndex !== null) {
                $orders[$currentIndex]['items'][] = $item;
            }
        }

        foreach ($orders as $index => $order) {
            $orders[$index] = $this->normalizeOrderTotals($order);
        }

        return [
            'source' => basename($filePath),
            'sheet' => $sheetName,
            'branch_default' => 'WWW-BDG-001',
            'count' => count($orders),
            'rows' => $orders,
        ];
    }

    /**
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>
     */
    private function normalizeOrderTotals(array $order): array
    {
        $items = $order['items'] ?? [];
        $lineSubtotals = [];

        foreach ($items as $item) {
            $lineSubtotals[] = max(0, (float) ($item['price'] ?? 0) - (float) ($item['discount'] ?? 0));
        }

        $itemsSum = array_sum($lineSubtotals);
        $grandTotal = (float) ($order['grand_total'] ?? 0);

        if (count($items) === 1 && abs($grandTotal - $itemsSum) > 0.01 && $grandTotal > 0) {
            $items[0]['subtotal'] = $grandTotal;
            $orderTotal = $grandTotal;
            $totalDiscount = (float) ($items[0]['discount'] ?? 0);
            $items[0]['unit_price'] = $grandTotal + $totalDiscount;
        } else {
            foreach ($items as $idx => $item) {
                $items[$idx]['subtotal'] = $lineSubtotals[$idx];
                $items[$idx]['unit_price'] = (float) ($item['price'] ?? 0);
            }
            $orderTotal = $itemsSum > 0 ? $itemsSum : $grandTotal;
        }

        $order['items'] = $items;
        $order['grand_total'] = round($orderTotal, 4);
        $order['subtotal'] = round($orderTotal, 4);
        $order['item_discount_total'] = round(array_sum(array_map(
            fn (array $item) => (float) ($item['discount'] ?? 0),
            $items
        )), 4);

        return $order;
    }

    private function parseNumber(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $normalized = str_replace([',', ' '], '', trim((string) $value));

        return $normalized === '' ? 0.0 : (float) $normalized;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d+(\.\d+)?$/', $raw)) {
            return $this->excelSerialToDateTime((float) $raw)->format('Y-m-d');
        }

        $timestamp = strtotime($raw);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    private function parseDateTime(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s');
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d+(\.\d+)?$/', $raw)) {
            return $this->excelSerialToDateTime((float) $raw)->format('Y-m-d\TH:i:s');
        }

        $timestamp = strtotime($raw);

        return $timestamp ? date('Y-m-d\TH:i:s', $timestamp) : null;
    }

    private function excelSerialToDateTime(float $serial): DateTime
    {
        $days = (int) floor($serial);
        $seconds = (int) round(($serial - $days) * 86400);
        $date = new DateTime('1899-12-30');
        $date->modify("+{$days} days");
        $date->modify("+{$seconds} seconds");

        return $date;
    }
}
