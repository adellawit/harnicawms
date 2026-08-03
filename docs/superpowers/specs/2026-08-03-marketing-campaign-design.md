# Marketing Campaign — Design Spec

**Tanggal:** 2026-08-03
**Branch:** `feature/agent-pos` (satu branch, dibedakan per commit)
**Status:** Disetujui untuk ditulis jadi handoff (mulai Slice A & B)

## Tujuan

Membangun fitur **Marketing Campaign** di admin: program promosi bertanggal yang (1) tampil & bisa dipilih di **POS agen**, dan (2) dipakai untuk **reaktivasi reseller** — reseller `inactive` yang ikut campaign (via transaksi POS) otomatis jadi `active`.

Saat ini menu "Marketing Campaign" **salah isi** — kontennya sebenarnya **Marketing Assets** (`AssetController` → `marketing.assets`). Jadi menu itu di-relabel jadi "Marketing Assets", dan "Marketing Campaign" dibangun sebagai fitur baru.

Contoh struktur yang ditiru: modul **Promotion** (`app/Models/Promotion.php`, `App\Http\Controllers\Admin\PromotionController`, route `promotions.*` di `routes/distribution.php`, view `resources/views/admin/promotions/*`) — CRUD dengan `code` auto, `name`, `is_active`, `starts_at`/`ends_at`, `priority`, soft deletes.

## Keputusan (hasil diskусi)

- **Mekanik diskon v1: ikut Promotion.** Campanye TIDAK membuat engine diskon baru — **menautkan satu Promotion** (`promotion_id`, `belongsTo`) sebagai mekaniknya. POS menjalankan mekanik lewat `PromotionEngineService` yang sudah ada.
- **1 Promotion per campaign** (relasi `belongsTo`, nullable).
- **Reaktivasi reseller: per-campaign** — kolom flag `reactivates_reseller` (boolean). Hanya campaign ber-flag yang mereaktivasi.
- **Banner: upload sendiri** (field gambar milik campaign).
- **POS: campaign bisa dipilih/diterapkan** kasir (bukan sekadar info).

## Model data

### `marketing.campaigns`
`id (uuid v7)`, `company_id`, `code` (auto, mis. `CMP-...`), `name`, `description` (nullable),
`banner_path` (nullable, upload), `promotion_id` (nullable, FK → `product.promotions`), `reactivates_reseller` (bool, default false),
`is_active` (bool), `starts_at` (datetime, nullable), `ends_at` (datetime, nullable), `priority` (int, default 0), `status` (string, mis. `draft`/`active`/`ended` — atau cukup pakai `is_active` + window; tentukan saat implement),
audit: `created_by`/`updated_by`/`deleted_by`, `timestamps`, `softDeletes`.
Scope `activeNow()` (mirip Promotion): `is_active` true DAN (`starts_at` null atau ≤ now) DAN (`ends_at` null atau ≥ now).

### `marketing.campaign_participants`
`id (uuid v7)`, `campaign_id` (FK), `reseller_id` (FK → `partner.resellers`), `sales_order_id` (nullable, FK → transaksi POS pemicu), `joined_at` (datetime), audit + timestamps.
Unik lembut: satu reseller boleh ikut campaign yang sama lebih dari sekali (tiap transaksi) ATAU dedupe per (campaign, reseller) — tentukan saat implement (rekomendasi: catat tiap partisipasi, tak perlu unik).

## Titik integrasi (slice berikutnya)

- **POS agen** (`AgentPosController` + `agent/pos/index.blade.php`): pilih campaign aktif, terapkan mekanik Promotion tertaut ke transaksi, tandai `campaign_id`, saat bayar buat `campaign_participant` + reaktivasi reseller bila `reactivates_reseller`.
- **Strip campaign POS**: sekarang menarik `Promotion::activeNow()` → dialihkan ke `Campaign::activeNow()`.

## Rencana slice

- **Slice A** — Relabel menu "Marketing Campaign" → "Marketing Assets" (perbaikan salah label). Kecil, independen.
- **Slice B** — CRUD admin Marketing Campaign: migrasi + model + controller + views (tiru Promotion) + route + permission + menu baru "Marketing Campaign". Independen, bisa digarap duluan.
- **Slice C** — Integrasi POS: pilih & terapkan campaign, `campaign_participant`, reaktivasi reseller. Butuh B + POS agen.
- **Slice D** — Alihkan strip campaign POS dari `Promotion::activeNow()` → `Campaign::activeNow()`.

## Di luar cakupan (v1)

- Engine diskon campaign sendiri (pakai Promotion dulu).
- Banyak Promotion per campaign.
- Reaktivasi via jalur selain transaksi POS (mis. enroll manual admin).
- Penargetan reseller spesifik / kuota / anggaran campaign.

## Verifikasi (tanpa test suite)

`php -l`, `php artisan migrate` (migrasi baru jalan), `php artisan route:list --name=marketing.campaigns`, `view:cache && view:clear`. Smoke admin: buat campaign (pilih Promotion, set periode, flag reaktivasi, upload banner) → tampil di index → edit/hapus. Menu "Marketing Assets" menampilkan aset (bukan lagi berlabel Campaign); menu "Marketing Campaign" menuju CRUD baru.
