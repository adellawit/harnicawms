# Recap Kerja — WMS 3.0

> Sesi kerja: onboarding project baru, perbaikan bug lintas modul (Partner Network, Purchase Order, Production, Inventory), dan diskusi arsitektur data.

---

## 1. Onboarding & Environment

- Mempelajari struktur project: Laravel 12 + PostgreSQL multi-schema (modular monolith), lihat [MIGRATION.md](../MIGRATION.md) dan [docs/ARCHITECTURE.md](../docs/ARCHITECTURE.md).
- Menambahkan `.gitignore` standar Laravel (project ini sebelumnya **tidak punya** `.gitignore` sama sekali — `.env` sempat ter-commit).
- Memperbaiki `APP_ENV=production` → `local` di `.env` supaya `migrate:fresh` tidak diblokir prompt konfirmasi production.
- Troubleshoot koneksi PostgreSQL lokal (role `postgres` belum ada di Homebrew Postgres) dan konflik peer-dependency `npm install` (`laravel-vite-plugin` vs Vite 7).

## 2. Bug: Migration folder `partner` & `crm` terlewat

**Gejala:** `SQLSTATE[42P01]: relation "partner.agents" does not exist` saat buka menu Partner Network.

**Akar masalah:** [`MigrateAllCommand.php`](../app/Console/Commands/MigrateAllCommand.php) tidak mendaftarkan folder migration `partner` maupun `crm` di `$migrationPaths` / `$customSchemas`, padahal migration-nya sudah ada dan sudah didaftarkan di `AppServiceProvider`.

**Perbaikan:**
- Tambah `partner` & `crm` ke `MigrateAllCommand` (urutan setelah `auth`/`customer`/`transaction` sesuai dependency FK).
- Jalankan migration untuk kedua folder — tabel `partner.agents`, `partner.resellers`, `crm.membership_point_configurations`, dst berhasil dibuat.

## 3. Form Partner Application — validasi & UX

Halaman: `/partner-network/applications/create`

| Field | Sebelum | Sesudah |
|---|---|---|
| Email | `type="email"` tanpa pesan error | + pesan error inline |
| No. Telepon | Bebas ketik apa saja | Filter otomatis, non-angka langsung terbuang |
| Kota | Text input bebas | Dropdown Select2 (AJAX, ter-filter dari Provinsi) |
| Provinsi | Text input bebas | Dropdown Select2 (AJAX search) |

Reuse partial `admin.partials.province-city-dropdown` yang sudah ada di form lain — plus perbaikan bug pendukung: asset Select2 (`select2.css`/`select2.js`) ternyata belum di-include di halaman ini, jadi dropdown-nya sempat tidak berfungsi sama sekali sebelum ditambahkan.

## 4. Dropdown Produk — AJAX Search yang Reusable

**Masalah:** dropdown pilih produk di "Tambah Item" (Convert Agent, Partner Application) me-load **semua** varian produk sekaligus ke halaman — tidak scalable kalau produk makin banyak.

**Solusi (Opsi B dari diskusi):**
- Endpoint baru `GET /helper/product-variants` di [`HelperController`](../app/Http/Controllers/Admin/HelperController.php) — search + pagination, pola sama dengan `getProvinces`/`getCities`.
- Partial reusable baru: [`product-variant-select2.blade.php`](../resources/views/admin/partials/product-variant-select2.blade.php) — expose `window.initProductVariantSelect2($el, options)`, bisa dipasang ke `<select>` statis maupun yang di-generate dinamis via JS.
- Diterapkan di `partner/applications/show.blade.php`, sekaligus hapus query `WmsContext::variantOptions()` yang tadinya selalu load semua varian tiap halaman detail dibuka.

## 5. Bug: Radio Button "Document Type" di Purchase Order

Halaman: `/product/purchase-order/insert`

**Gejala:** pilih radio "RO — Release Order", radio "PO"/"CPO" lainnya **hilang** dari layar dan tidak bisa dipilih lagi.

**Akar masalah:** JS di [`insert.blade.php`](../resources/views/admin/product/purchase-order/insert.blade.php) menyembunyikan opsi lain setiap kali `sub` (RO) dipilih — logic ini sebenarnya sisa kode yang salah tempat, karena skenario "locked ke RO" (dari CPO) sudah ditangani terpisah oleh server (radio-nya bahkan tidak dirender).

**Perbaikan:** hapus logic hide/show yang keliru; ketiga opsi sekarang selalu terlihat & bisa diklik bebas.

## 6. Bug: Crash di Halaman Buat Production Order

Halaman: `/production/create`

**Gejala:** `Argument #1 ($product) must be of type App\Models\Product, null given`.

**Akar masalah:** ada BOM (resep) aktif yang produk outputnya **sudah dihapus (soft-delete)** — relasi `$bom->product` jadi `null`, tapi kode tidak menjaga kemungkinan itu.

**Perbaikan (3 lapis):**
1. Guard `->whereHas('product')` di query BOM controller (jangan crash lagi walau ada BOM yatim di masa depan).
2. Saat produk dihapus, sekarang otomatis menonaktifkan BOM yang mengacu ke produk itu (`ProductController::deleteData`) — akar masalah yang sebelumnya tidak ada cascade-nya sama sekali.
3. Data cleanup: BOM yatim yang sudah ada di database dinonaktifkan.

## 7. Inbound (Stok Masuk) — Kolom Satuan Tidak Jelas

Halaman: `/inbound`

**Masalah:** tabel "Layer Biaya FIFO" cuma tampilkan angka qty tanpa satuan — padahal kolom `unit_id` sudah ada di database, cuma belum dipakai.

**Perbaikan:** tambah relasi `unit()` di `ProductCostLayer`, eager-load di controller, tambah kolom **Satuan** di tabel + rename header (`Qty` → `Qty Masuk`, `HPP / Unit` → `HPP / Satuan`) supaya tidak ambigu.

## 8. Diskusi: Kenapa "1 Karton" Produksi Jadi "300 Box" di Stok?

Bukan bug di modul stok — root cause-nya di konfigurasi **BOM (resep)**:

- Setiap BOM punya `output_unit_id` yang **fixed**. Apa pun satuan yang dipilih user saat input qty produksi, sistem selalu konversi ke satuan resep itu (`ProductionQuantityNormalizer::toBomOutputUnit()`) sebelum dicatat ke stok/FIFO.
- Ditemukan **2 BOM aktif dengan nama identik** untuk produk yang sama ("Foredi Product... - Resep Standar") — satu berbasis Karton, satu berbasis Box — dan dropdown "Resep (BOM)" di form **tidak menampilkan satuan output**, jadi keduanya tampil sama persis dan gampang salah pilih.
- **Belum diperbaiki** — masih menunggu keputusan: perbaiki label dropdown (tampilkan satuan) dan/atau bereskan BOM duplikat.

## 9. Diskusi Arsitektur (belum diimplementasi)

- **Kode negara untuk nomor telepon**: belum ada tabel di sistem (model `Nation` ada tapi tidak pernah termigrasi/dipakai). Dua opsi dibahas — tabel ringan baru vs "menghidupkan" tabel `nations` yang sudah ada modelnya. **Keputusan ditunda.**
- **Struktur data bahan baku multi-level (Karton → Box → Sachet) & alur CPO → RO**: dikonfirmasi sistem **sudah mendukung penuh** lewat `product_unit_conversions` (rantai konversi berjenjang per produk) dan hierarki `purchase_orders.parent_id`/`po_kind`. Catatan penting: RO **wajib** pakai satuan yang identik dengan CPO induknya (tidak ada konversi otomatis lintas satuan di layer ini).

---

## File yang Berubah

```
Modified:
  app/Console/Commands/MigrateAllCommand.php
  app/Http/Controllers/Admin/HelperController.php
  app/Http/Controllers/Admin/InboundController.php
  app/Http/Controllers/Admin/Partner/PartnerApplicationController.php
  app/Http/Controllers/Admin/ProductController.php
  app/Http/Controllers/Admin/ProductionOrderController.php
  app/Models/ProductCostLayer.php
  resources/views/admin/inbound/index.blade.php
  resources/views/admin/partner/applications/_form.blade.php
  resources/views/admin/partner/applications/create.blade.php
  resources/views/admin/partner/applications/show.blade.php
  resources/views/admin/product/purchase-order/insert.blade.php
  routes/web.php

New:
  .gitignore
  resources/views/admin/partials/product-variant-select2.blade.php
```

## Belum Dikerjakan (Follow-up)

- [ ] Perbaiki label dropdown "Resep (BOM)" supaya menampilkan satuan output (hindari resep duplikat nama ambigu).
- [ ] Putuskan mau untrack `.env`/`.env-production` dari git (`git rm --cached`).
- [ ] Putuskan desain tabel kode negara untuk nomor telepon (jika jadi dikerjakan).
- [ ] Migrasikan form lain (Purchase Order, dsb) ke pola dropdown produk AJAX yang baru, jika mau konsisten penuh di seluruh aplikasi.
