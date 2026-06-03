# Arsitektur Sistem (Current State)

> Dokumen ini merepresentasikan arsitektur yang sedang berjalan berdasarkan struktur codebase saat ini.

## Framework Version Summary

  Project: WMS 3.0 (Warehouse Management System)

  Backend
  ┌───────────┬─────────┐
  │ Component │ Version │
  ├───────────┼─────────┤
  │ Laravel   │ ^12.0   │
  ├───────────┼─────────┤
  │ PHP       │ ^8.2    │
  └───────────┴─────────┘
  Frontend
  ┌──────────────┬─────────┐
  │  Component   │ Version │
  ├──────────────┼─────────┤
  │ Vite         │ ^7.3.1  │
  ├──────────────┼─────────┤
  │ Tailwind CSS │ ^4.1.18 │
  ├──────────────┼─────────┤
  │ Alpine.js    │ ^3.14.1 │
  ├──────────────┼─────────┤
  │ Axios        │ ^1.7.9  │
  └──────────────┴─────────┘
  Key Packages

  - Laravel Sanctum ^4.0 - API authentication
  - Laravel Breeze ^2.0 - Auth scaffolding
  - JWT Auth ^2.7 - Token authentication
  - Laravel Datatables ^12.0 - Table utilities
  - L5 Swagger ^9.0 - API documentation
  - Maatwebsite Excel ^3.1 - Excel import/export

  This is a modular monolith Laravel 12 application with API-based architecture using Sanctum + JWT for authentication.

## 1. Ringkasan Arsitektur

- **Tipe Arsitektur**: Modular Monolith
- **Backend Framework**: Laravel (PHP)
- **Frontend Rendering**: Blade server-rendered + JS enhancement (Select2, ApexCharts, dsb)
- **Database**: PostgreSQL multi-schema (contoh: `auth`, `master_data`, `product`, `transaction`, `human_resources`, `crm`)
- **Pattern Implementasi**: Controller-centric dengan pemisahan per modul domain

## 2. Konteks Sistem

### 2.1 Aktor Utama

- `[Admin]`
- `[Operator]`
- `[Manager]`
- `[External System]`

### 2.2 Bounded Context / Domain Utama (Aktif)

- Authentication & Profile
- Human Resources
- Business Structure
- Access Management & Settings
- Customer
- CRM & Membership
- Product Master
- Inventory
- Purchasing
- POS & Transaction
- Reporting

## 3. Komponen Utama

### 3.1 Backend Components (Aktif)

- `routes/web.php` sebagai entrypoint HTTP utama
- `app/Http/Controllers/Admin/*` untuk modul business features
- Eloquent Models per schema domain (`app/Models/*`)
- Seeder & migration berbasis schema (`database/seeders`, `database/migrations/*`)
- Permission middleware per menu/fitur

### 3.2 Frontend Components (Aktif)

- Layout global (`resources/views/layouts/*`)
- Halaman modul admin (`resources/views/admin/**`)
- Komponen reusable (`x-app-layout`, `x-page-header`, KPI cards)

### 3.3 Data Components (Aktif)

- Primary DB PostgreSQL multi-schema
- Soft delete, UUID key, relational FK antar schema
- Tidak ada message broker aktif di current scope

### 3.4 CRM & Membership Data Model (Planned)

- Schema `crm` untuk memisahkan data membership dari master/customer dan transaksi:
  - Tabel konfigurasi poin membership (misal: `crm.membership_point_configs`)
    - Konfigurasi rate poin per nominal transaksi (contoh: **1 poin = 100** rupiah) dengan dukungan kelipatan nominal.
    - Aturan pembulatan/perhitungan poin (floor/round up) per transaksi.
  - Tabel akun membership per customer (misal: `crm.membership_accounts`)
    - Relasi ke customer (`customer.customers`) sebagai pemilik akun membership.
    - Saldo poin terkini + metadata tier/level (jika diperlukan di fase lanjut).
  - Tabel riwayat poin (misal: `crm.membership_point_histories`)
    - Penyimpanan earning poin per transaksi POS/penjualan dan event koreksi manual.
    - Relasi ke transaksi (`transaction.*`) dan ke akun membership.

> Catatan: Schema `crm` bersifat **planned extension** di atas domain Customer & POS yang sudah ada, tanpa mengubah pola modular monolith.

## 4. Integrasi & Antarmuka

### 4.1 Internal Interfaces

- POS -> Product pricing/stock
- Purchasing -> Supplier & Product
- Reporting -> Transaction/Product/Purchase datasets
- Access Management -> Role/Menu/Permission mapping

### 4.2 External Interfaces (Current)

- Helper API internal (provinces/cities/business units)
- File upload endpoint
- Tidak ada gateway external wajib pada alur inti saat ini

## 5. Alur Data (High-Level)

### 5.1 Alur Read

1. `[Client Request]`
2. `[Validation]`
3. `[Query Data]`
4. `[Response Mapping]`

### 5.2 Alur Write

1. `[Client Request]`
2. `[Validation & Business Rules]`
3. `[Transaction / Persist]`
4. `[Event / Audit Log]`

## 6. Non-Functional Requirements

### 6.1 Security

- `[Auth Strategy]`
- `[Authorization Strategy]`
- `[Data Protection]`

### 6.2 Performance

- `[Response Time Target]`
- `[Concurrency Target]`
- `[Caching Strategy]`

### 6.3 Reliability

- `[Backup & Restore]`
- `[Retry Strategy]`
- `[Monitoring & Alerting]`

## 7. Deployment View

### 7.1 Environments

- `[Local]`
- `[Staging]`
- `[Production]`

### 7.2 CI/CD

- `[Build]`
- `[Test]`
- `[Release]`

## 8. Risiko Arsitektur (Current)

| Risiko | Dampak | Mitigasi | Owner |
|---|---|---|---|
| Perubahan lintas schema migration | High | Atur dependency migration + dokumentasi urutan | Backend |
| Coupling antar controller-modul | Medium | Standarisasi service boundary per modul | Backend |
| Konsistensi permission menu vs route | Medium | Validasi permission matrix berkala | Backend + QA |

## 9. Keputusan Arsitektur (ADR Ringkas)

| ID | Keputusan | Status | Tanggal | Catatan |
|---|---|---|---|---|
| `ADR-001` | Gunakan modular monolith dengan pemisahan domain via folder + schema DB | Accepted | Existing | Menjaga delivery cepat sambil tetap terstruktur |
| `ADR-002` | Gunakan PostgreSQL multi-schema untuk segregasi domain data | Accepted | Existing | Domain boundary lebih jelas pada level data |
