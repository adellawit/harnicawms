# Epic & Task by Module (Based on Existing Features)

> Mapping epic di bawah ini merepresentasikan fitur yang sudah tersedia di project saat ini.
> Kolom status bisa diisi tim sesuai kondisi delivery.

## 1. Authentication & Profile

### Epic AUTH-01: Account Session & Profile

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `AUTH-01-T01` | Login/logout/register/forgot-password flow | Backend+Frontend | P0 | Existing |
| `AUTH-01-T02` | Profile update & change password | Backend+Frontend | P0 | Existing |
| `AUTH-01-T03` | Branch switching per user context | Backend+Frontend | P1 | Existing |

## 2. Human Resources

### Epic HR-01: Employee Management

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `HR-01-T01` | Employee list, create, edit, delete, restore | Backend+Frontend | P0 | Existing |
| `HR-01-T02` | Employee detail & import/template | Backend+Frontend | P1 | Existing |
| `HR-01-T03` | Impersonation (`login as`) | Backend+Frontend | P1 | Existing |

### Epic HR-02: Division & Position

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `HR-02-T01` | Division CRUD | Backend+Frontend | P1 | Existing |
| `HR-02-T02` | Position CRUD | Backend+Frontend | P1 | Existing |

## 3. Business & Master Data

### Epic BIZ-01: Business Unit Structure

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `BIZ-01-T01` | Holding CRUD | Backend+Frontend | P1 | Existing |
| `BIZ-01-T02` | Company CRUD | Backend+Frontend | P1 | Existing |
| `BIZ-01-T03` | Branch CRUD | Backend+Frontend | P1 | Existing |

### Epic MD-01: Parameters & Settings

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `MD-01-T01` | Parameter CRUD | Backend+Frontend | P1 | Existing |
| `MD-01-T02` | Parameter detail CRUD | Backend+Frontend | P1 | Existing |
| `MD-01-T03` | Dashboard configuration per role | Backend+Frontend | P2 | Existing |

## 4. Access Management & Notification

### Epic IAM-01: Access Control

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `IAM-01-T01` | Role CRUD | Backend+Frontend | P0 | Existing |
| `IAM-01-T02` | Menu CRUD | Backend+Frontend | P1 | Existing |

### Epic NOTIF-01: Notification Center

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `NOTIF-01-T01` | Notification config page | Backend+Frontend | P2 | Existing |
| `NOTIF-01-T02` | Notification APIs (list/count/mark read) | Backend+Frontend | P1 | Existing |

## 5. Customer

### Epic CUST-01: Customer Domain

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `CUST-01-T01` | Customer group CRUD | Backend+Frontend | P1 | Existing |
| `CUST-01-T02` | Customer CRUD | Backend+Frontend | P1 | Existing |
| `CUST-01-T03` | Remove customer attachment | Backend+Frontend | P2 | Existing |

## 6. Product Master

### Epic PROD-01: Product Reference Master

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `PROD-01-T01` | Nature/unit/category CRUD | Backend+Frontend | P0 | Existing |
| `PROD-01-T02` | Attribute + value CRUD | Backend+Frontend | P1 | Existing |
| `PROD-01-T03` | Tag & collection CRUD | Backend+Frontend | P1 | Existing |
| `PROD-01-T04` | Price list CRUD + active endpoint | Backend+Frontend | P1 | Existing |

### Epic PROD-02: Product Item Lifecycle

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `PROD-02-T01` | Product item CRUD | Backend+Frontend | P0 | Existing |
| `PROD-02-T02` | 3-step insert flow | Backend+Frontend | P0 | Existing |
| `PROD-02-T03` | Variant CRUD & variant data API | Backend+Frontend | P0 | Existing |
| `PROD-02-T04` | Unit conversion add/edit/delete | Backend+Frontend | P1 | Existing |
| `PROD-02-T05` | Import/export/template | Backend+Frontend | P1 | Existing |

## 7. Inventory & Purchasing

### Epic INV-01: Inventory Operations

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `INV-01-T01` | Stock view | Backend+Frontend | P1 | Existing |
| `INV-01-T02` | Stock opname save | Backend+Frontend | P1 | Existing |
| `INV-01-T03` | Stock adjustment save | Backend+Frontend | P1 | Existing |
| `INV-01-T04` | Product price save | Backend+Frontend | P1 | Existing |

### Epic PUR-01: Purchasing

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `PUR-01-T01` | Supplier CRUD | Backend+Frontend | P1 | Existing |
| `PUR-01-T02` | Purchase order CRUD | Backend+Frontend | P0 | Existing |
| `PUR-01-T03` | Purchase order receiving flow | Backend+Frontend | P0 | Existing |

## 8. POS & Transaction

### Epic POS-01: Point of Sales

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `POS-01-T01` | POS page and cart flow | Backend+Frontend | P0 | Existing |
| `POS-01-T02` | Variant pricing API by price list | Backend+Frontend | P0 | Existing |
| `POS-01-T03` | Payment processing + stock deduction | Backend+Frontend | P0 | Existing |

### Epic TRX-01: Transaction Admin

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `TRX-01-T01` | Transaction list page | Backend+Frontend | P1 | Existing |
| `TRX-01-T02` | Transaction detail page | Backend+Frontend | P1 | Existing |
| `TRX-01-T03` | Method payment CRUD | Backend+Frontend | P1 | Existing |

## 9. Reporting

### Epic RPT-01: Sales & Transaction Reporting

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `RPT-01-T01` | Summary sales report | Backend+Frontend | P1 | Existing |
| `RPT-01-T02` | Transaction report | Backend+Frontend | P1 | Existing |

### Epic RPT-02: Purchasing & Inventory Reporting

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `RPT-02-T01` | Purchase order report | Backend+Frontend | P1 | Existing |
| `RPT-02-T02` | Stock movement / stock card report | Backend+Frontend | P1 | Existing |
| `RPT-02-T03` | Stock history report | Backend+Frontend | P1 | Existing |

## 10. CRM & Membership (Planned)

### Epic CRM-01: Membership Configuration & Points

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `CRM-01-T01` | Migration schema `crm` + tabel membership point configuration | Backend | P1 | Planned |
| `CRM-01-T02` | Seeder menu **Configuration** untuk CRM Membership | Backend | P1 | Planned |
| `CRM-01-T03` | CRUD backend membership point configuration (validation, business rule 1 poin = 100 dan kelipatan nominal transaksi) | Backend | P1 | Planned |
| `CRM-01-T04` | CRUD frontend membership point configuration (list/create/edit/delete) | Frontend | P1 | Planned |

### Epic CRM-02: Customer Membership Integration

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `CRM-02-T01` | Link akun membership ke customer (relasi `customer` ↔ `crm.membership_accounts`) | Backend+Frontend | P1 | Planned |
| `CRM-02-T02` | Penambahan informasi membership & poin pada detail customer | Backend+Frontend | P2 | Planned |

### Epic CRM-03: POS Points Accrual

| Task ID | Task (Existing Capability) | Type | Priority | Status |
|---|---|---|---|---|
| `CRM-03-T01` | Hitung poin membership otomatis saat transaksi POS (berdasarkan konfigurasi poin) | Backend+Frontend | P1 | Planned |
| `CRM-03-T02` | Simpan riwayat poin per transaksi pada schema `crm` | Backend | P1 | Planned |
| `CRM-03-T03` | Tampilan riwayat poin pada halaman customer / membership | Frontend | P2 | Planned |

## 11. Warehouse & Multi-Location WMS (New)

> Modul terpisah dari Business & Master Data existing.
> Scope: pemisahan master gudang (`master_data.warehouses`), stok per lokasi fisik, dan integrasi ke modul operasional.
>
> **Aturan data:** `branch_id` = cabang operasional · `warehouse_id` = lokasi fisik stok

### Epic WH-01: Warehouse Master Schema (Migration)

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `WH-01-T01` | Migration `warehouse_types` lookup (RAW_MATERIAL, WIP, PRODUCTION, FG, GENERAL, TRANSIT, QUARANTINE) | Backend | P0 | Done |
| `WH-01-T02` | Migration `master_data.warehouses` (company_id, branch_id, warehouse_type_code, is_default, legacy_business_unit_id) | Backend | P0 | Done |
| `WH-01-T03` | Migration `branch_warehouse_assignments` (gudang pusat/shared per cabang) | Backend | P0 | Done |
| `WH-01-T04` | Data migration legacy `business_units` WAREHOUSE → `warehouses` + auto default gudang per cabang (`WarehouseBootstrapService`) | Backend | P0 | Done |
| `WH-01-T05` | Seeder `WarehouseSeeder` (sync setelah `DistributionDemoSeeder`) | Backend | P1 | Done |

#### Detail Task WH-01

**`WH-01-T01` — warehouse_types**
- Tabel lookup `master_data.warehouse_types` dengan kode: RAW_MATERIAL, WIP, PRODUCTION, FG, GENERAL, TRANSIT, QUARANTINE
- Kolom: `code` (PK), `name`, `description`, `is_active`

**`WH-01-T02` — warehouses**
- Tabel `master_data.warehouses`: `id`, `company_id`, `branch_id`, `code`, `name`, `warehouse_type_code`, `is_default`, `legacy_business_unit_id`
- FK: `company_id` → `business_units`, `branch_id` → `business_units` (BRANCH), `warehouse_type_code` → `warehouse_types`
- Index: `(branch_id, is_default)`, `(company_id, warehouse_type_code)`

**`WH-01-T03` — branch_warehouse_assignments**
- Pivot gudang pusat/shared: `branch_id`, `warehouse_id`, `is_primary`
- Satu gudang bisa melayani banyak cabang (DC, gudang korporat)

**`WH-01-T04` — Migrasi legacy**
- `WarehouseBootstrapService`: copy baris `business_units` type WAREHOUSE → `warehouses`
- Set `legacy_business_unit_id` untuk mapping FK lama
- Auto-create gudang default per cabang yang belum punya gudang

**`WH-01-T05` — WarehouseSeeder**
- Jalankan setelah `DistributionDemoSeeder` untuk sync gudang demo
- Idempotent: tidak duplikasi jika sudah ada

### Epic WH-02: Warehouse-aware Inventory Schema (Migration)

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `WH-02-T01` | Tambah `warehouse_id` + FK ke `product_variant_stock`, `product_stock_movements`, `product_batch_stock` | Backend | P0 | Done |
| `WH-02-T02` | Tambah `warehouse_id` + FK ke `product_cost_layers` (jika tabel ada) | Backend | P0 | Done |
| `WH-02-T03` | Migrasi data: `warehouse_id` dari legacy `branch_id`; `branch_id` disinkronkan dari `warehouses.branch_id` | Backend | P0 | Done |
| `WH-02-T04` | Ubah unique key stok: `(variant, warehouse)` dan `(batch, warehouse)` | Backend | P0 | Done |
| `WH-02-T05` | Index FIFO/FEFO cost layer per `(variant, warehouse, quantity_remaining)` | Backend | P1 | Done |

#### Detail Task WH-02

**`WH-02-T01` — Kolom warehouse_id (stok utama)**
- Tambah `warehouse_id` UUID + FK ke `product_variant_stock`, `product_stock_movements`, `product_batch_stock`
- Nullable sementara untuk backfill

**`WH-02-T02` — Kolom warehouse_id (cost layer)**
- Tambah `warehouse_id` ke `product_cost_layers` jika tabel ada (`Schema::hasTable` guard)

**`WH-02-T03` — Backfill data**
- `InventoryWarehouseMigrationService`: set `warehouse_id` dari legacy `branch_id` (yang sebenarnya gudang)
- Sinkronkan `branch_id` operasional dari `warehouses.branch_id`

**`WH-02-T04` — Unique key baru**
- Drop unique `(product_variant_id, branch_id)` → buat `(product_variant_id, warehouse_id)`
- Sama untuk batch stock: `(batch_id, warehouse_id)`

**`WH-02-T05` — Index cost layer**
- Index composite `(product_variant_id, warehouse_id, quantity_remaining)` untuk query FIFO/FEFO

### Epic WH-03: Warehouse-aware Transaction Schema (Migration)

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `WH-03-T01` | Repoint FK `transaction.sales_orders.warehouse_id` → `master_data.warehouses` | Backend | P0 | Done |
| `WH-03-T02` | Tambah `warehouse_id` di `purchase_orders` & `purchase_order_receives` + backfill default gudang cabang | Backend | P0 | Done |
| `WH-03-T03` | Refactor `production_orders`: `branch_id` → `output_warehouse_id` + `branch_id` operasional baru | Backend | P0 | Done |
| `WH-03-T04` | Repoint FK `production_orders.source_warehouse_id` → `master_data.warehouses` | Backend | P0 | Done |
| `WH-03-T05` | Tambah `source_warehouse_id` / `destination_warehouse_id` di `distribution.shipments` + `warehouse_id` di `receipts` | Backend | P2 | Done |

#### Detail Task WH-03

**`WH-03-T01` — Sales orders FK**
- Drop FK lama `sales_orders.warehouse_id` → `business_units`
- Tambah FK baru → `master_data.warehouses`
- Remap ID via `legacy_business_unit_id` mapping

**`WH-03-T02` — Purchase order warehouse**
- Tambah `warehouse_id` ke `purchase_orders` dan `purchase_order_receives`
- Backfill: gudang default cabang PO

**`WH-03-T03` — Production orders refactor**
- Rename kolom `branch_id` → `output_warehouse_id` (gudang hasil produksi)
- Tambah `branch_id` baru (cabang operasional)
- Remap data lama via `InventoryWarehouseMigrationService`

**`WH-03-T04` — Production source FK**
- Repoint `production_orders.source_warehouse_id` FK ke `master_data.warehouses`

**`WH-03-T05` — Distribution logistics**
- `shipments`: `source_warehouse_id`, `destination_warehouse_id`
- `receipts`: `warehouse_id`
- Skip migration jika schema `distribution` belum ada

### Epic WH-04: Warehouse Application Layer

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `WH-04-T01` | Model `Warehouse`, `WarehouseType`, `BranchWarehouseAssignment` + relasi di `BusinessUnit` | Backend | P0 | Todo |
| `WH-04-T02` | Refactor `WmsContext` (query `warehouses` per cabang/company, bukan `BusinessUnit::WAREHOUSE`) | Backend | P0 | Todo |
| `WH-04-T03` | Refactor `StockMutationService`, `FifoCostService`, `InventoryCostService` → `branch_id` + `warehouse_id` terpisah | Backend | P0 | Todo |
| `WH-04-T04` | Update model stok: `ProductVariantStock`, `ProductStockMovement`, `ProductCostLayer` (+ relasi `warehouse()`) | Backend | P0 | Todo |
| `WH-04-T05` | Refactor `WarehouseController` CRUD ke `master_data.warehouses` (parent = cabang, tipe dari `warehouse_types`) | Backend+Frontend | P0 | Todo |
| `WH-04-T06` | Update form/index gudang (`_form`, `index`, `insert`, `edit`) | Frontend | P1 | Todo |

#### Detail Task WH-04

**`WH-04-T01` — Model & relasi**
- Buat `app/Models/Warehouse.php` → tabel `master_data.warehouses`, connection `master_data`
- Buat `app/Models/WarehouseType.php` → tabel `master_data.warehouse_types`
- Buat `app/Models/BranchWarehouseAssignment.php` → pivot `branch_warehouse_assignments`
- Relasi `Warehouse`: `company()`, `branch()`, `warehouseType()`, `legacyBusinessUnit()` (opsional)
- Relasi `BusinessUnit`: `ownedWarehouses()` (hasMany via `warehouses.branch_id`), `assignedWarehouses()` (belongsToMany via pivot)
- Helper: `Warehouse::defaultForBranch(string $branchId): ?Warehouse`

**`WH-04-T02` — WmsContext**
- File: `app/Support/WmsContext.php`
- Ganti query `BusinessUnit::where('type_code', 'WAREHOUSE')` → model `Warehouse`
- `warehouses($branchId)` → gudang milik cabang + gudang assigned (pivot)
- `wipWarehouse($companyId)` / `finishedGoodsWarehouse($companyId)` → filter `warehouse_type_code` + `company_id`
- `defaultWarehouse($branchId)` → gudang `is_default = true` per cabang
- Return type: `Warehouse` model, bukan `BusinessUnit`

**`WH-04-T03` — Service stok inti**
- File: `StockMutationService`, `FifoCostService`, `InventoryCostService`, `StockAvailabilityService`
- Ubah signature method: tambah parameter `warehouseId` (wajib untuk operasi stok)
- Lock/update stok pakai unique `(product_variant_id, warehouse_id)`, bukan `(variant, branch_id)`
- Cost layer FIFO/FEFO scoped per `warehouse_id`
- Movement catat `branch_id` (cabang operasional) + `warehouse_id` (lokasi fisik) terpisah
- Fallback: jika `warehouseId` null, resolve dari `Warehouse::defaultForBranch($branchId)`

**`WH-04-T04` — Model stok**
- File: `ProductVariantStock`, `ProductStockMovement`, `ProductCostLayer`, `ProductStock` (legacy)
- Tambah `warehouse_id` ke `$fillable`
- Relasi `warehouse()` → `belongsTo(Warehouse::class)`
- Relasi `branch()` tetap ke `BusinessUnit` (type BRANCH)
- Scope query: `scopeForWarehouse($query, $warehouseId)`

**`WH-04-T05` — WarehouseController (backend)**
- File: `app/Http/Controllers/Admin/WarehouseController.php`
- CRUD ke model `Warehouse`, bukan `BusinessUnit` dengan `type_code = WAREHOUSE`
- Validasi: `company_id` wajib; `branch_id` wajib kecuali gudang pusat (DC)
- Validasi: hanya 1 `is_default = true` per `branch_id`
- `warehouse_type_code` dari dropdown `warehouse_types`, bukan `brand_name`
- Sync `branch_warehouse_assignments` untuk gudang shared/pusat
- Hapus insert/update ke `business_units` type WAREHOUSE

**`WH-04-T06` — UI master gudang**
- File: `resources/views/admin/business/warehouse/_form.blade.php`, `index`, `insert`, `edit`
- Dropdown parent: pilih **Cabang** (bukan Company) sebagai pemilik gudang
- Dropdown tipe: dari `warehouse_types` (Bahan Baku, WIP, Produksi, FG, Umum, dll.)
- Checkbox/toggle **Gudang Default** untuk cabang terpilih
- Index: grouping per cabang → daftar gudang di bawahnya
- Tampilkan badge tipe gudang + status default

### Epic WH-05: Warehouse Module Integration

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `WH-05-T01` | `PurchaseOrderController` + view receive: simpan & tampilkan `warehouse_id` | Backend+Frontend | P0 | Todo |
| `WH-05-T02` | `ProductStockController` + view stock: filter cabang & gudang terpisah | Backend+Frontend | P0 | Todo |
| `WH-05-T03` | `StockAdjustmentController`: pilih gudang saat adjustment | Backend+Frontend | P0 | Todo |
| `WH-05-T04` | `ProductionOrderController` + `ProductionService`: `output_warehouse_id` / `source_warehouse_id` | Backend+Frontend | P0 | Todo |
| `WH-05-T05` | `InboundController` + views inbound: dropdown gudang dari `warehouses` | Backend+Frontend | P1 | Todo |
| `WH-05-T06` | `PosCheckoutService` / `ShopCheckoutService`: kurangi stok dari gudang default cabang | Backend | P0 | Todo |
| `WH-05-T07` | `SalesOrder` model: relasi `warehouse()` ke `Warehouse` | Backend | P1 | Todo |
| `WH-05-T08` | `ReplenishmentStockService` + distribution logistics: kolom warehouse baru | Backend | P1 | Todo |
| `WH-05-T09` | `DistributionDemoSeeder`: WH-WIP/WH-FG ke `warehouses`, bukan `business_units` | Backend | P1 | Todo |
| `WH-05-T10` | `ProductionResetCommand` / `InventoryResetCommand`: query gudang dari `warehouses` | Backend | P2 | Todo |

#### Detail Task WH-05

**`WH-05-T01` — Purchase Order Receive**
- File: `PurchaseOrderController`, `resources/views/admin/product/purchase-order/receive.blade.php`
- Dropdown gudang dari `WmsContext::warehouses($branchId)` / `Warehouse` model
- Simpan `warehouse_id` ke `purchase_order_receives` saat submit receive
- Default gudang: gudang default cabang PO, atau WIP untuk bahan baku
- `StockMutationService::inbound()` terima `warehouse_id` eksplisit
- Validasi: gudang harus milik/ter-assign ke cabang PO

**`WH-05-T02` — Stock View**
- File: `ProductStockController`, `resources/views/admin/product/stock/index.blade.php`
- Filter 1: Cabang operasional (`branch_id`)
- Filter 2: Gudang (`warehouse_id`) — dependent dropdown setelah pilih cabang
- Query stok: `product_variant_stock` filter by `warehouse_id`
- Kolom tabel: tampilkan nama gudang + cabang
- Export/laporan ikut filter gudang aktif

**`WH-05-T03` — Stock Adjustment**
- File: `StockAdjustmentController`, view adjustment
- Form wajib pilih gudang sebelum input item adjustment
- Adjustment (+/-) apply ke `warehouse_id` terpilih
- Movement audit trail catat `warehouse_id` + `branch_id`

**`WH-05-T04` — Production Order**
- File: `ProductionOrderController`, `ProductionService`, `resources/views/admin/production/create.blade.php`
- Form: pilih **Gudang Bahan Baku** (`source_warehouse_id`) dan **Gudang Output** (`output_warehouse_id`)
- Form: pilih **Cabang Operasional** (`branch_id`) terpisah dari gudang
- `ProductionService`: konsumsi bahan dari `source_warehouse_id`, output ke `output_warehouse_id`
- Update model `ProductionOrder`: fillable `output_warehouse_id`, relasi `outputWarehouse()`, `sourceWarehouse()`
- Hapus penggunaan `branch_id` sebagai gudang output

**`WH-05-T05` — Inbound**
- File: `InboundController`, `resources/views/admin/inbound/*.blade.php`
- Dropdown gudang tujuan dari `Warehouse` per distributor/cabang
- Transfer antar gudang: `from_warehouse_id` → `to_warehouse_id`
- Validasi stok cukup di gudang asal

**`WH-05-T06` — POS & Shop Checkout**
- File: `PosCheckoutService`, `ShopCheckoutService`, `ShopCartService`
- Saat checkout: resolve `warehouse_id` = gudang default cabang customer/kasir
- Kurangi stok dari `warehouse_id`, bukan `branch_id` langsung
- `SalesOrder` simpan `warehouse_id` gudang pengiriman/pengambilan

**`WH-05-T07` — Sales Order relasi**
- File: `app/Models/SalesOrder.php`
- Relasi `warehouse()` → `belongsTo(Warehouse::class, 'warehouse_id')`
- Relasi `branch()` tetap ke `BusinessUnit` (BRANCH)
- Eager load `warehouse` di transaction list/detail

**`WH-05-T08` — Distribution & Replenishment**
- File: `ReplenishmentStockService`, shipment/receipt flow
- Shipment: kurangi stok dari `source_warehouse_id` (gudang FG distributor)
- Receipt: tambah stok ke `warehouse_id` (gudang default agen)
- Pakai kolom baru di `distribution.shipments` dan `distribution.receipts`

**`WH-05-T09` — DistributionDemoSeeder**
- File: `database/seeders/DistributionDemoSeeder.php`
- Create WH-WIP & WH-FG ke `master_data.warehouses` (`company_id`, `warehouse_type_code`)
- Inbound stok demo pakai `warehouse_id`, bukan ID `business_units`
- Link gudang ke cabang via `branch_id` atau assignment jika perlu

**`WH-05-T10` — Artisan Commands**
- File: `ProductionResetCommand`, `InventoryResetCommand`
- Ganti `BusinessUnit::where('type_code', 'WAREHOUSE')` → `Warehouse::whereIn('code', ['WH-WIP','WH-FG'])`
- Clean stok by `warehouse_id` di `product_variant_stock`, `product_cost_layers`, movements

### Epic WH-06: Warehouse Cleanup & Reporting

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `WH-06-T01` | Deprecate pivot `business_unit_branches` setelah semua modul pindah | Backend | P2 | Todo |
| `WH-06-T02` | Stop create `type_code = WAREHOUSE` di `business_units` | Backend | P2 | Todo |
| `WH-06-T03` | Laporan stok & stock card per `warehouse_id` | Backend+Frontend | P1 | Todo |
| `WH-06-T04` | Dashboard WMS KPI dari `warehouses` + stok per gudang | Backend+Frontend | P2 | Todo |
| `WH-06-T05` | Update dokumentasi `MIGRATION.md` / ERD untuk skema warehouse baru | Docs | P2 | Todo |

#### Detail Task WH-06

**`WH-06-T01` — Deprecate pivot lama**
- Pastikan tidak ada kode yang baca/tulis `master_data.business_unit_branches`
- Migration drop tabel `business_unit_branches` (setelah backup data ke pivot baru)
- Update `BusinessUnit::warehouses()` hapus relasi ke pivot lama

**`WH-06-T02` — Stop WAREHOUSE di business_units**
- Hapus/block create `type_code = WAREHOUSE` di `WarehouseController` & seeder
- Opsional: migration soft-deprecate baris lama (set `deleted_at`) setelah semua FK pindah
- Hapus `WAREHOUSE` dari seeder `business_unit_types` (atau mark inactive)

**`WH-06-T03` — Laporan per gudang**
- File: `ReportStockCardController`, `ReportAdvancedController`, view reporting
- Filter laporan: cabang + gudang
- Stock card movement join `warehouse_id` → `warehouses.name`
- Export Excel/PDF per gudang

**`WH-06-T04` — Dashboard WMS**
- File: `DashboardController`, `resources/views/dashboard.blade.php`
- KPI: total gudang aktif, stok per gudang, inbound/outbound hari ini per gudang
- Widget low stock per `warehouse_id`
- Data source dari `warehouses` + `product_variant_stock`, bukan `business_units`

**`WH-06-T05` — Dokumentasi**
- Update `MIGRATION.md`: urutan migration warehouse + diagram hierarki
- Update `docs/dbdiagram.dbml` / `docs/erd-migrations.md`
- Update `docs/ARCHITECTURE.md`: penjelasan `branch_id` vs `warehouse_id`

### Warehouse Migration Files (Reference)

| Migration | Path |
|---|---|
| `2026_06_18_000001` | `master_data/create_warehouse_types_table` |
| `2026_06_18_000002` | `master_data/create_warehouses_table` |
| `2026_06_18_000003` | `master_data/create_branch_warehouse_assignments_table` |
| `2026_06_18_000004` | `master_data/migrate_legacy_business_unit_warehouses` |
| `2026_06_18_000001` | `product/add_warehouse_id_to_inventory_tables` |
| `2026_06_18_000001` | `transaction/repoint_sales_orders_warehouse_fk` |
| `2026_06_18_000001` | `purchase_order/add_warehouse_id_to_purchase_order_tables` |
| `2026_06_18_000001` | `manufacturing/refactor_production_orders_for_warehouses` |
| `2026_06_18_000001` | `distribution/add_warehouse_id_to_distribution_logistics` |

### Warehouse Sprint Recommendation

| Sprint | Epic | Fokus |
|---|---|---|
| Sprint 1 | `WH-04` (T01–T04) | Model + Service inti (`WmsContext`, `StockMutationService`) |
| Sprint 2 | `WH-04` (T05–T06) + `WH-05` (T01–T04) | UI master gudang + PO/Stok/Produksi |
| Sprint 3 | `WH-05` (T05–T10) | Inbound, POS, distribusi, seeder, command |
| Sprint 4 | `WH-06` | Cleanup legacy + reporting |

## 12. Partner Network: Agent & Reseller Warehouse (New)

> Scope: memisahkan partner eksternal dari struktur internal `business_units`.
> `business_units` tetap untuk HOLDING / COMPANY / BRANCH internal.
> Agent dan Reseller dikelola sebagai partner network, dengan integrasi gudang untuk Agent.

**Aturan data:**
- `company_id` = Distributor / principal company
- `branch_id` = outlet/cabang operasional internal distributor
- `agent_id` = partner Agent eksternal
- `reseller_id` = partner Reseller eksternal
- `warehouse_id` = lokasi fisik stok

### Epic PN-01: Partner Master Schema

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `PN-01-T01` | Migration schema `partner` | Backend | P0 | Todo |
| `PN-01-T02` | Migration `partner.agents` untuk master Agent | Backend | P0 | Todo |
| `PN-01-T03` | Migration `partner.resellers` untuk master Reseller | Backend | P0 | Todo |
| `PN-01-T04` | Migration `partner.agent_reseller_assignments` | Backend | P0 | Todo |
| `PN-01-T05` | Seeder status/lifecycle partner | Backend | P1 | Todo |

#### Detail Task PN-01

**`PN-01-T01` — Schema partner**
- Buat schema `partner` untuk data jaringan eksternal distributor.
- Pisahkan dari `master_data.business_units` agar tree internal tetap bersih.
- Siapkan migration guard `CREATE SCHEMA IF NOT EXISTS partner`.

**`PN-01-T02` — agents**
- Tabel `partner.agents`: `id`, `company_id`, `code`, `name`, `status`, `approval_status`.
- Data kontak: `email`, `phone`, `address`, `city`, `province`, `postal_code`.
- Link opsional: `default_warehouse_id`, `approved_at`, `approved_by`.
- Agent bukan `business_units`; Agent adalah partner eksternal distributor.

**`PN-01-T03` — resellers**
- Tabel `partner.resellers`: `id`, `company_id`, `agent_id`, `code`, `name`, `status`.
- Data kontak/customer profile untuk aktivitas penjualan reseller.
- Reseller tidak punya warehouse default di fase awal.
- Reseller dapat dipetakan ke customer/member bila diperlukan.

**`PN-01-T04` — agent_reseller_assignments**
- Assignment reseller ke agent dengan histori perpindahan.
- Kolom: `agent_id`, `reseller_id`, `effective_from`, `effective_to`, `is_active`.
- Satu reseller hanya boleh punya satu assignment aktif pada waktu yang sama.

**`PN-01-T05` — Partner lifecycle seed**
- Seeder status Agent: `draft`, `pending_approval`, `active`, `suspended`, `terminated`.
- Seeder status Reseller: `active`, `inactive`, `suspended`.
- Seeder approval status: `pending`, `approved`, `rejected`.

### Epic PN-02: Agent Warehouse Integration

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `PN-02-T01` | Ubah `master_data.warehouses` agar bisa dimiliki Agent | Backend | P0 | Todo |
| `PN-02-T02` | Auto create/link warehouse saat Agent approved | Backend | P0 | Todo |
| `PN-02-T03` | Default warehouse Agent untuk penerimaan replenishment | Backend | P0 | Todo |
| `PN-02-T04` | Validasi warehouse milik Agent saat receive/return | Backend | P0 | Todo |

#### Detail Task PN-02

**`PN-02-T01` — Warehouse ownership**
- Rekomendasi struktur jangka panjang: `owner_type` (`COMPANY`, `BRANCH`, `AGENT`) + `owner_id`.
- Tetap simpan `company_id` untuk scope distributor.
- `branch_id` hanya untuk cabang internal, bukan Agent.
- Pastikan index lookup per `(owner_type, owner_id)` dan `(company_id, warehouse_type_code)`.

**`PN-02-T02` — Approve Agent**
- Saat Agent disetujui, sistem membuat atau menghubungkan warehouse Agent.
- Warehouse default Agent dipakai untuk receipt dan return flow.
- Code warehouse dapat digenerate dari kode Agent agar mudah dilacak.

**`PN-02-T03` — Default warehouse Agent**
- `Agent::defaultWarehouse()` mengarah ke warehouse default milik Agent.
- Fallback hanya boleh ke warehouse aktif yang owner-nya Agent yang sama.
- Tidak fallback ke gudang branch internal karena Agent bukan `business_units`.

**`PN-02-T04` — Warehouse validation**
- Receive replenishment harus masuk ke warehouse milik Agent terkait.
- Return dari Agent harus keluar dari warehouse milik Agent terkait.
- Tolak transaksi jika warehouse tidak aktif atau beda distributor.

### Epic PN-03: Replenishment Refactor for Partner Agent

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `PN-03-T01` | Refactor `replenishment_orders.agent_id` ke `partner.agents` | Backend | P0 | Todo |
| `PN-03-T02` | Shipment: distributor FG warehouse → agent warehouse | Backend | P0 | Todo |
| `PN-03-T03` | Receipt: masuk ke default warehouse Agent | Backend | P0 | Todo |
| `PN-03-T04` | Return: keluar dari warehouse Agent → FG distributor | Backend | P1 | Todo |
| `PN-03-T05` | UI replenishment pilih Agent partner, bukan branch | Backend+Frontend | P0 | Todo |

#### Detail Task PN-03

**`PN-03-T01` — Repoint agent_id**
- `distribution.replenishment_orders.agent_id` mengarah ke `partner.agents`.
- `distributor_id` tetap mengarah ke `master_data.business_units` tipe COMPANY.
- Backfill data lama dari `business_units` BRANCH jika ada demo/legacy Agent.

**`PN-03-T02` — Shipment**
- Kurangi stok dari `source_warehouse_id` gudang FG distributor.
- Isi `destination_warehouse_id` dengan default warehouse Agent.
- Audit movement mencatat `warehouse_id`, `company_id`, dan `agent_id`.

**`PN-03-T03` — Receipt**
- Tambah stok ke `warehouse_id` default Agent.
- Harga transfer menjadi cost layer Agent.
- Expiry/FEFO diteruskan dari shipment item.

**`PN-03-T04` — Return**
- Kurangi stok dari warehouse Agent.
- Tambah stok kembali ke gudang FG distributor.
- Simpan audit referensi `agent_id` dan `source_warehouse_id`.

**`PN-03-T05` — UI replenishment**
- Dropdown Agent membaca `partner.agents` aktif.
- Tampilkan warehouse default Agent di form/detail.
- Validasi UI mencegah order jika Agent belum punya warehouse aktif.

### Epic PN-04: Agent & Reseller Management UI

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `PN-04-T01` | CRUD Agent + approval workflow | Backend+Frontend | P0 | Todo |
| `PN-04-T02` | CRUD Reseller | Backend+Frontend | P0 | Todo |
| `PN-04-T03` | Assignment Reseller ke Agent | Backend+Frontend | P1 | Todo |
| `PN-04-T04` | Detail Agent tampilkan warehouse, stock summary, reseller list | Backend+Frontend | P1 | Todo |

#### Detail Task PN-04

**`PN-04-T01` — Agent management**
- Halaman list/create/edit/detail Agent.
- Approval workflow: pending → approved/rejected.
- Saat approved, panggil proses create/link warehouse Agent.

**`PN-04-T02` — Reseller management**
- Halaman list/create/edit/detail Reseller.
- Reseller berada di bawah scope distributor dan dapat ditugaskan ke Agent.
- Siapkan link opsional ke customer/member profile.

**`PN-04-T03` — Assignment**
- Form assignment Reseller ke Agent.
- Support histori assignment dan satu assignment aktif.
- Validasi Agent dan Reseller harus dalam `company_id` yang sama.

**`PN-04-T04` — Agent detail dashboard**
- Tampilkan warehouse default Agent.
- Tampilkan summary stok per warehouse Agent.
- Tampilkan daftar Reseller aktif di bawah Agent.

### Epic PN-05: Reporting & Access

| Task ID | Task | Type | Priority | Status |
|---|---|---|---|---|
| `PN-05-T01` | Filter laporan stok per Agent warehouse | Backend+Frontend | P1 | Todo |
| `PN-05-T02` | Sales/replenishment report per Agent dan Reseller | Backend+Frontend | P1 | Todo |
| `PN-05-T03` | Permission menu Partner Network | Backend | P1 | Todo |

#### Detail Task PN-05

**`PN-05-T01` — Agent warehouse reporting**
- Filter laporan stok berdasarkan Agent dan warehouse Agent.
- Stock card tetap menggunakan `warehouse_id` sebagai sumber kebenaran stok.
- Export laporan mencantumkan Agent, warehouse, dan distributor.

**`PN-05-T02` — Agent & Reseller sales report**
- Laporan replenishment per Agent.
- Laporan penjualan/aktivitas per Reseller jika data transaksi reseller sudah tersedia.
- KPI: order value, received qty, return qty, active reseller count.

**`PN-05-T03` — Partner Network permissions**
- Seeder menu Partner Network.
- Permission CRUD Agent, approval Agent, CRUD Reseller, assignment Reseller.
- Scope akses mengikuti distributor aktif user.

### Partner Network Sprint Recommendation

| Sprint | Epic | Fokus |
|---|---|---|
| Sprint 1 | `PN-01` + `PN-02` | Schema partner + ownership warehouse Agent |
| Sprint 2 | `PN-03` | Refactor replenishment Agent dari branch ke partner |
| Sprint 3 | `PN-04` | UI Agent/Reseller + approval workflow |
| Sprint 4 | `PN-05` | Reporting, permission, dan hardening akses |

## 13. Tracking Summary (Current Inventory)

| Module | Epic Count | Task Count | Status |
|---|---:|---:|---|
| Authentication & Profile | 1 | 3 | Existing |
| Human Resources | 2 | 5 | Existing |
| Business & Master Data | 2 | 6 | Existing |
| Access Mgmt & Notification | 2 | 4 | Existing |
| Customer | 1 | 3 | Existing |
| Product Master | 2 | 9 | Existing |
| Inventory & Purchasing | 2 | 7 | Existing |
| POS & Transaction | 2 | 6 | Existing |
| Reporting | 2 | 5 | Existing |
| CRM & Membership | 3 | 9 | Planned |
| **Warehouse & Multi-Location WMS** | **6** | **35** | **Migration Done / App Todo** |
| **Partner Network: Agent & Reseller Warehouse** | **5** | **21** | **Planned** |
