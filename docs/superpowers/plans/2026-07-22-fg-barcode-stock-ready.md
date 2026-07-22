# FG Barcode & Stock Ready — Implementation Plan

> **For agentic workers:** Use this as the executable task list for the FG barcode/stock monitoring report.

**Goal:** Shipping a Reporting → Inventory & Warehouse page that compares FG warehouse stock vs unassigned label serials, with drill-down and Excel export.

**Architecture:** Controller → Service → Repository (same pattern as Barcode Dispatch report). Summary grain = product + variant + unit against `product_variant_stock` in FG warehouse. Ready serial = `product_label_serials` without assignment.

**Tech note:** Menu parent is Inventory & Warehouse (no Reporting → Product parent exists). Views live under `reporting/product/fg-barcode-stock` for folder consistency with barcode-tracking.

## File map

- `app/Repositories/FgBarcodeStockReportRepository.php`
- `app/Services/Reporting/FgBarcodeStockReportService.php`
- `app/Http/Controllers/Admin/ReportFgBarcodeStockController.php`
- `app/Http/Requests/FgBarcodeStockReportRequest.php`
- `app/Exports/FgBarcodeStockSummaryExport.php`
- `app/Exports/FgBarcodeStockSerialExport.php`
- `resources/views/admin/reporting/product/fg-barcode-stock/index.blade.php`
- `database/seeders/FgBarcodeStockAccessSeeder.php`
- routes + DatabaseSeeder + MenuSeeder entry
- `scripts/fg-barcode-stock-test.php`

## Tasks

1. Repository summary + serial detail + KPIs
2. Service filters/options/report payload
3. Controller + request + exports + routes
4. Blade UI (filters, KPIs, table, serial modal, exports)
5. Menu/access seeder + wire DatabaseSeeder
6. Smoke script + pint
