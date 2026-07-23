# FG Barcode & Stock Ready — Design

Date: 2026-07-22  
Status: Approved (scope) — proceed to implementation

## Goal

Monitor finished-goods warehouse stock alongside printable label serials that are still available (not assigned to a sales order), and highlight mismatches.

## Placement

- Menu: **Reporting → Inventory & Warehouse** (no Reporting → Product parent exists in menu tree; Inventory is the correct home next to Stock On Hand)
- Name: **FG Barcode & Stock**
- Permission: `FG Barcode & Stock` (`is_read`) — default Administrator (same pattern as Barcode Dispatch)
- View path: `admin/reporting/product/fg-barcode-stock` (folder convention shared with barcode-tracking)

## Data rules

- **FG stock**: `product.product_variant_stock` for warehouse `warehouse_type_code = FG` (selectable FG warehouses in context; default `WmsContext::finishedGoodsWarehouse`).
- **Serial ready**: rows in `product.product_label_serials` with **no** row in `transaction.sales_order_item_serial_assignments`.
- Grain: **product + variant (nullable) + unit**.
- **Selisih** = `serial_ready_qty − stock_fg_qty`
  - `0` → OK
  - `> 0` → Serial surplus
  - `< 0` → Serial shortage
- Serials are not warehouse-bound in DB; “ready in FG” means ready-for-sale serials compared against FG stock qty.

## UI

1. Filter bar: warehouse (FG), product, variant, unit, mismatch status (All / Mismatch only).
2. Summary table columns: Product, Variant, Unit, Warehouse, Stock FG, Serial Ready, Selisih, Status, action “Serials”.
3. Drill-down (modal or dedicated panel): list ready serial numbers for that product/variant/unit.
4. Export Excel:
   - Summary sheet/file
   - Ready serials sheet/file (or second export action)

## Out of scope

- Mutating stock or serials
- Replacing Barcode Dispatch (sold/assigned serials)
- Non-FG warehouses

## Architecture

Controller → Service → Repository (mirror Barcode Dispatch report):

- `ReportFgBarcodeStockController`
- `FgBarcodeStockReportService` / `FgBarcodeStockReportRepository`
- `FgBarcodeStockExport` (+ serial detail export)
- Views under `resources/views/admin/reporting/product/fg-barcode-stock/`
- Routes: `reporting.fg-barcode-stock.*`
