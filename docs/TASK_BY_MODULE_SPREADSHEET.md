# Task by Module — Spreadsheet Format

> Copy-paste ke spreadsheet. Setiap baris task bisa dipisah ke kolom: **Module | Task | Estimasi (days) | Status**
> Format estimasi: 1 day = 1 hari kerja (8 jam)

---

## Module : Authentication & Profile

Task :
- Login / logout / register / forgot-password flow (3 days)
- Profile update & change password (2 days)
- Branch switching per user context (2 days)

---

## Module : Human Resources

Task :
- Employee list, create, edit, delete, restore (3 days)
- Employee detail, import data & download template (3 days)
- Impersonation login-as (2 days)
- Division CRUD (2 days)
- Position CRUD (2 days)

---

## Module : Business & Master Data

Task :
- Holding CRUD (2 days)
- Company CRUD (2 days)
- Branch CRUD (3 days)
- Auto-generate kode branch & warehouse (BusinessUnitCodeService) (2 days)
- Warehouse settings inline di form Branch (create/edit) (3 days)
- Parameter CRUD (2 days)
- Parameter detail CRUD (2 days)
- Dashboard configuration per role (3 days)

---

## Module : Access Management & Notification

Task :
- Role CRUD (2 days)
- Menu CRUD (2 days)
- Notification config page (2 days)
- Notification API — list, unread count, mark read (2 days)

---

## Module : Customer

Task :
- Customer group CRUD (2 days)
- Customer CRUD (3 days)
- Remove customer attachment (1 day)

---

## Module : Product Master

Task :
- Nature / unit / category CRUD (3 days)
- Attribute definition + value CRUD (3 days)
- Tag & collection CRUD (2 days)
- Price list CRUD + active endpoint (3 days)
- Product item CRUD (3 days)
- 3-step product insert flow (5 days)
- Variant CRUD & variant data API (4 days)
- Unit conversion add / edit / delete (3 days)
- Import / export / download template (4 days)
- Product label serial allocation (ProductLabelSerialService) (3 days)
- Print barcode & QR label — preview & PDF (4 days)

---

## Module : Inventory

Task :
- Stock view per cabang & gudang (3 days)
- Stock opname save (3 days)
- Stock adjustment save + pilih gudang (3 days)
- Product price save (2 days)

---

## Module : Purchasing

Task :
- Supplier CRUD (2 days)
- Purchase order CRUD (4 days)
- Purchase order receiving flow + warehouse_id (4 days)

---

## Module : BOM (Bill of Materials)

Task :
- Migration schema manufacturing + tabel bill_of_materials & bom_items (2 days)
- BOM index — daftar produk jadi & status resep per varian (3 days)
- BOM create — input komponen bahan baku + satuan per item (4 days)
- BOM show — detail resep + estimasi biaya HPP per komponen FIFO (3 days)
- BOM delete (1 day)
- Seeder menu & permission Bill of Materials (1 day)
- BOM edit — ubah komponen tanpa hapus & buat ulang (3 days) — Planned
- BOM versioning — histori versi resep (3 days) — Planned
- BOM copy — duplikasi resep ke varian lain (2 days) — Planned

---

## Module : Production Order

Task :
- Migration production_orders, materials & outputs (2 days)
- Production order list (2 days)
- Production order create — pilih BOM, qty, overhead, preview kebutuhan bahan (4 days)
- Production order complete — konsumsi FIFO bahan + inbound FG + hitung HPP (5 days)
- Production order show — detail bahan terkonsumsi & output (2 days)
- Generate nomor produksi PRD-YYYYMM-XXXX (1 day)
- Seeder menu & permission Production Order (1 day)
- Form create — pilih gudang bahan baku & gudang output FG eksplisit (3 days) — Todo
- ProductionService scoped per warehouse_id (3 days) — Todo
- Laporan produksi — qty, biaya bahan, overhead, HPP (3 days) — Planned
- Laporan efisiensi bahan baku vs BOM standar (2 days) — Planned

---

## Module : POS

Task :
- POS page & cart flow (5 days)
- Variant pricing API by price list (2 days)
- Payment processing + stock deduction dari gudang default (4 days)
- Transaction list page (2 days)
- Transaction detail page (2 days)
- Method payment CRUD (2 days)

---

## Module : Reporting

Task :
- Summary sales report (3 days)
- Transaction report (3 days)
- Purchase order report (2 days)
- Stock movement / stock card report (3 days)
- Stock history report (2 days)
- Laporan stok per warehouse_id (3 days)

---

## Module : CRM & Membership (Planned)

Task :
- Migration schema crm + tabel membership point configuration (2 days)
- Seeder menu Configuration untuk CRM Membership (1 day)
- CRUD backend membership point configuration (3 days)
- CRUD frontend membership point configuration (3 days)
- Link akun membership ke customer (3 days)
- Info membership & poin di detail customer (2 days)
- Hitung poin otomatis saat transaksi POS (4 days)
- Simpan riwayat poin per transaksi (2 days)
- Tampilan riwayat poin di halaman customer (2 days)

---

## Module : Warehouse & Multi-Location WMS

Task :
- Migration warehouse_types lookup (1 day) — Done
- Migration master_data.warehouses (1 day) — Done
- Migration branch_warehouse_assignments (1 day) — Done
- Data migration legacy business_units WAREHOUSE (2 days) — Done
- Seeder WarehouseSeeder (1 day) — Done
- Tambah warehouse_id ke tabel inventory (2 days) — Done
- Migrasi data warehouse_id + unique key baru (2 days) — Done
- Repoint FK sales_orders, purchase_orders, production_orders (3 days) — Done
- Model Warehouse, WarehouseType, BranchWarehouseAssignment (2 days)
- Refactor WmsContext — query warehouses per cabang (3 days)
- Refactor StockMutationService, FifoCostService, InventoryCostService (5 days)
- Update model stok + relasi warehouse() (2 days)
- WarehouseController CRUD ke master_data.warehouses (4 days)
- Update form/index gudang (insert, edit, _form) (3 days)
- Purchase order receive — simpan & tampilkan warehouse_id (3 days)
- Product stock view — filter cabang & gudang terpisah (3 days)
- Stock adjustment — pilih gudang (2 days)
- Production order — output_warehouse_id / source_warehouse_id (4 days)
- Inbound — dropdown gudang dari warehouses (3 days)
- POS & Shop checkout — kurangi stok dari gudang default cabang (3 days)
- SalesOrder relasi warehouse() (1 day)
- Replenishment & distribution logistics — kolom warehouse baru (3 days)
- Update DistributionDemoSeeder ke warehouses (2 days)
- ProductionResetCommand / InventoryResetCommand refactor (1 day)
- Deprecate pivot business_unit_branches (2 days)
- Stop create type_code WAREHOUSE di business_units (1 day)
- Dashboard WMS KPI dari warehouses (3 days)
- Update dokumentasi MIGRATION.md & ERD (2 days)

---

## Module : Partner Network (Agent & Reseller)

Task :
- Halaman pendaftaran partner Agent / Reseller (4 days) — Done
- Migration partner_applications & documents (2 days) — Done
- Auto-create/link customer dari pendaftaran (2 days) — Done
- Follow-up workflow manual admin & Agent (3 days) — Done
- Convert calon Agent + transaksi awal PO/Invoice (4 days) — Done
- Assign & convert calon Reseller oleh Agent (3 days) — Done
- Migration schema partner — agents, resellers, assignments (2 days) — Done
- Warehouse ownership Agent + auto create saat approve (3 days) — Done
- Refactor replenishment agent_id ke partner.agents (4 days) — Done
- Shipment distributor FG → agent warehouse (3 days) — Done
- Receipt & return flow warehouse Agent (3 days) — Done
- UI replenishment pilih Agent partner (3 days) — Done
- CRUD Agent + approval workflow (4 days) — Done
- CRUD Reseller + assignment ke Agent (3 days) — Done
- Detail Agent — warehouse, stock summary, reseller list (3 days) — Done
- Filter laporan stok per Agent warehouse (2 days) — Done
- Sales / replenishment report per Agent & Reseller (3 days) — Done
- Permission menu Partner Network (2 days) — Done
- Agent portal login — user mapping, role, auto-create akun (4 days) — Done
- Scope halaman & replenishment untuk user Agent (3 days) — Done

---

## Module : UI / Layout (Enhancement)

Task :
- Sidebar workspace-switcher style (branch selector card) (2 days) — Done
- Login page refresh (1 day)

---

# Ringkasan Estimasi per Module

| Module | Jumlah Task | Total Estimasi (days) | Catatan |
|---|---:|---:|---|
| Authentication & Profile | 3 | 7 | Existing |
| Human Resources | 5 | 12 | Existing |
| Business & Master Data | 8 | 19 | + warehouse settings branch |
| Access Management & Notification | 4 | 8 | Existing |
| Customer | 3 | 6 | Existing |
| Product Master | 11 | 36 | + label serial & barcode |
| Inventory | 4 | 11 | Perlu integrasi warehouse |
| Purchasing | 3 | 10 | Perlu integrasi warehouse |
| BOM (Bill of Materials) | 9 | 22 | Existing + enhancement planned |
| Production Order | 10 | 25 | Existing + warehouse integration todo |
| POS | 6 | 17 | Perlu integrasi warehouse |
| Reporting | 6 | 16 | + laporan per gudang |
| CRM & Membership | 9 | 22 | Planned |
| Warehouse & Multi-Location WMS | 28 | 58 | Migration done, app layer todo |
| Partner Network | 20 | 58 | Implemented |
| UI / Layout | 2 | 3 | Enhancement |
| **GRAND TOTAL** | **131** | **330** | |

---

# Format TSV (alternatif — paste langsung ke Google Sheets / Excel)

```
Module	Task	Estimasi (days)	Status
Authentication & Profile	Login / logout / register / forgot-password flow	3	Existing
Authentication & Profile	Profile update & change password	2	Existing
Authentication & Profile	Branch switching per user context	2	Existing
Human Resources	Employee list, create, edit, delete, restore	3	Existing
Human Resources	Employee detail, import data & download template	3	Existing
Human Resources	Impersonation login-as	2	Existing
Human Resources	Division CRUD	2	Existing
Human Resources	Position CRUD	2	Existing
Business & Master Data	Holding CRUD	2	Existing
Business & Master Data	Company CRUD	2	Existing
Business & Master Data	Branch CRUD	3	Existing
Business & Master Data	Auto-generate kode branch & warehouse	2	In Progress
Business & Master Data	Warehouse settings inline di form Branch	3	In Progress
Business & Master Data	Parameter CRUD	2	Existing
Business & Master Data	Parameter detail CRUD	2	Existing
Business & Master Data	Dashboard configuration per role	3	Existing
Access Management & Notification	Role CRUD	2	Existing
Access Management & Notification	Menu CRUD	2	Existing
Access Management & Notification	Notification config page	2	Existing
Access Management & Notification	Notification API — list, unread count, mark read	2	Existing
Customer	Customer group CRUD	2	Existing
Customer	Customer CRUD	3	Existing
Customer	Remove customer attachment	1	Existing
Product Master	Nature / unit / category CRUD	3	Existing
Product Master	Attribute definition + value CRUD	3	Existing
Product Master	Tag & collection CRUD	2	Existing
Product Master	Price list CRUD + active endpoint	3	Existing
Product Master	Product item CRUD	3	Existing
Product Master	3-step product insert flow	5	Existing
Product Master	Variant CRUD & variant data API	4	Existing
Product Master	Unit conversion add / edit / delete	3	Existing
Product Master	Import / export / download template	4	Existing
Product Master	Product label serial allocation	3	In Progress
Product Master	Print barcode & QR label — preview & PDF	4	In Progress
Inventory	Stock view per cabang & gudang	3	Todo
Inventory	Stock opname save	3	Existing
Inventory	Stock adjustment save + pilih gudang	3	Todo
Inventory	Product price save	2	Existing
Purchasing	Supplier CRUD	2	Existing
Purchasing	Purchase order CRUD	4	Existing
Purchasing	Purchase order receiving flow + warehouse_id	4	Todo
BOM (Bill of Materials)	Migration schema manufacturing + tabel bill_of_materials & bom_items	2	Existing
BOM (Bill of Materials)	BOM index — daftar produk jadi & status resep per varian	3	Existing
BOM (Bill of Materials)	BOM create — input komponen bahan baku + satuan per item	4	Existing
BOM (Bill of Materials)	BOM show — detail resep + estimasi biaya HPP per komponen FIFO	3	Existing
BOM (Bill of Materials)	BOM delete	1	Existing
BOM (Bill of Materials)	Seeder menu & permission Bill of Materials	1	Existing
BOM (Bill of Materials)	BOM edit — ubah komponen tanpa hapus & buat ulang	3	Planned
BOM (Bill of Materials)	BOM versioning — histori versi resep	3	Planned
BOM (Bill of Materials)	BOM copy — duplikasi resep ke varian lain	2	Planned
Production Order	Migration production_orders, materials & outputs	2	Existing
Production Order	Production order list	2	Existing
Production Order	Production order create — pilih BOM, qty, overhead, preview kebutuhan bahan	4	Existing
Production Order	Production order complete — konsumsi FIFO bahan + inbound FG + hitung HPP	5	Existing
Production Order	Production order show — detail bahan terkonsumsi & output	2	Existing
Production Order	Generate nomor produksi PRD-YYYYMM-XXXX	1	Existing
Production Order	Seeder menu & permission Production Order	1	Existing
Production Order	Form create — pilih gudang bahan baku & gudang output FG eksplisit	3	Todo
Production Order	ProductionService scoped per warehouse_id	3	Todo
Production Order	Laporan produksi — qty, biaya bahan, overhead, HPP	3	Planned
Production Order	Laporan efisiensi bahan baku vs BOM standar	2	Planned
POS	POS page & cart flow	5	Existing
POS	Variant pricing API by price list	2	Existing
POS	Payment processing + stock deduction dari gudang default	4	Todo
POS	Transaction list page	2	Existing
POS	Transaction detail page	2	Existing
POS	Method payment CRUD	2	Existing
Reporting	Summary sales report	3	Existing
Reporting	Transaction report	3	Existing
Reporting	Purchase order report	2	Existing
Reporting	Stock movement / stock card report	3	Existing
Reporting	Stock history report	2	Existing
Reporting	Laporan stok per warehouse_id	3	Todo
CRM & Membership	Migration schema crm + tabel membership point configuration	2	Planned
CRM & Membership	Seeder menu Configuration untuk CRM Membership	1	Planned
CRM & Membership	CRUD backend membership point configuration	3	Planned
CRM & Membership	CRUD frontend membership point configuration	3	Planned
CRM & Membership	Link akun membership ke customer	3	Planned
CRM & Membership	Info membership & poin di detail customer	2	Planned
CRM & Membership	Hitung poin otomatis saat transaksi POS	4	Planned
CRM & Membership	Simpan riwayat poin per transaksi	2	Planned
CRM & Membership	Tampilan riwayat poin di halaman customer	2	Planned
Warehouse & Multi-Location WMS	Migration warehouse_types lookup	1	Done
Warehouse & Multi-Location WMS	Migration master_data.warehouses	1	Done
Warehouse & Multi-Location WMS	Migration branch_warehouse_assignments	1	Done
Warehouse & Multi-Location WMS	Data migration legacy business_units WAREHOUSE	2	Done
Warehouse & Multi-Location WMS	Seeder WarehouseSeeder	1	Done
Warehouse & Multi-Location WMS	Tambah warehouse_id ke tabel inventory	2	Done
Warehouse & Multi-Location WMS	Migrasi data warehouse_id + unique key baru	2	Done
Warehouse & Multi-Location WMS	Repoint FK sales_orders, purchase_orders, production_orders	3	Done
Warehouse & Multi-Location WMS	Model Warehouse, WarehouseType, BranchWarehouseAssignment	2	Todo
Warehouse & Multi-Location WMS	Refactor WmsContext — query warehouses per cabang	3	Todo
Warehouse & Multi-Location WMS	Refactor StockMutationService, FifoCostService, InventoryCostService	5	Todo
Warehouse & Multi-Location WMS	Update model stok + relasi warehouse()	2	Todo
Warehouse & Multi-Location WMS	WarehouseController CRUD ke master_data.warehouses	4	Todo
Warehouse & Multi-Location WMS	Update form/index gudang (insert, edit, _form)	3	Todo
Warehouse & Multi-Location WMS	Purchase order receive — simpan & tampilkan warehouse_id	3	Todo
Warehouse & Multi-Location WMS	Product stock view — filter cabang & gudang terpisah	3	Todo
Warehouse & Multi-Location WMS	Stock adjustment — pilih gudang	2	Todo
Warehouse & Multi-Location WMS	Production order — output_warehouse_id / source_warehouse_id	4	Todo
Warehouse & Multi-Location WMS	Inbound — dropdown gudang dari warehouses	3	Todo
Warehouse & Multi-Location WMS	POS & Shop checkout — kurangi stok dari gudang default cabang	3	Todo
Warehouse & Multi-Location WMS	SalesOrder relasi warehouse()	1	Todo
Warehouse & Multi-Location WMS	Replenishment & distribution logistics — kolom warehouse baru	3	Todo
Warehouse & Multi-Location WMS	Update DistributionDemoSeeder ke warehouses	2	Todo
Warehouse & Multi-Location WMS	ProductionResetCommand / InventoryResetCommand refactor	1	Todo
Warehouse & Multi-Location WMS	Deprecate pivot business_unit_branches	2	Todo
Warehouse & Multi-Location WMS	Stop create type_code WAREHOUSE di business_units	1	Todo
Warehouse & Multi-Location WMS	Dashboard WMS KPI dari warehouses	3	Todo
Warehouse & Multi-Location WMS	Update dokumentasi MIGRATION.md & ERD	2	Todo
Partner Network	Halaman pendaftaran partner Agent / Reseller	4	Done
Partner Network	Migration partner_applications & documents	2	Done
Partner Network	Auto-create/link customer dari pendaftaran	2	Done
Partner Network	Follow-up workflow manual admin & Agent	3	Done
Partner Network	Convert calon Agent + transaksi awal PO/Invoice	4	Done
Partner Network	Assign & convert calon Reseller oleh Agent	3	Done
Partner Network	Migration schema partner — agents, resellers, assignments	2	Done
Partner Network	Warehouse ownership Agent + auto create saat approve	3	Done
Partner Network	Refactor replenishment agent_id ke partner.agents	4	Done
Partner Network	Shipment distributor FG → agent warehouse	3	Done
Partner Network	Receipt & return flow warehouse Agent	3	Done
Partner Network	UI replenishment pilih Agent partner	3	Done
Partner Network	CRUD Agent + approval workflow	4	Done
Partner Network	CRUD Reseller + assignment ke Agent	3	Done
Partner Network	Detail Agent — warehouse, stock summary, reseller list	3	Done
Partner Network	Filter laporan stok per Agent warehouse	2	Done
Partner Network	Sales / replenishment report per Agent & Reseller	3	Done
Partner Network	Permission menu Partner Network	2	Done
Partner Network	Agent portal login — user mapping, role, auto-create akun	4	Done
Partner Network	Scope halaman & replenishment untuk user Agent	3	Done
UI / Layout	Sidebar workspace-switcher style	2	Done
UI / Layout	Login page refresh	1	In Progress
```
