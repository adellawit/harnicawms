# Marketing Center — Materi Promosi (Asset Library) Design Spec

**Tanggal:** 2026-07-14
**Branch:** (akan dibuat, mis. `feature/marketing-center`)
**Status:** Approved (brainstorming) — siap masuk tahap implementation plan.

## Konteks & Batasan Sub-Proyek

"Marketing Center" adalah kumpulan alat pemasaran yang **diakses oleh admin/marketing**
(guard `web`), **bukan** oleh reseller/agent. Screenshot referensi PM adalah layar
admin/marketing. Fitur ini akan tumbuh dalam beberapa sub-proyek independen:

1. **Materi Promosi (asset library)** — sub-proyek INI.
2. Link Order + Analitik — berikutnya.
3. Promo/Campaign management — terakhir.

Spec ini HANYA mencakup sub-proyek 1. Yang lain punya spec sendiri.

### Yang dibangun di sub-proyek 1
- **Pustaka aset media** yang dikelola admin/marketing: kategori + aset
  (gambar / video-link / PDF / teks-WA), dengan scope pemakaian, flag thumbnail,
  dan status draft/aktif.
- **Integrasi Training Academy**: course-builder bisa memilih aset (scope Training)
  sebagai isi materi (gambar/pdf/video) atau sebagai thumbnail course — via referensi FK.

### Yang TIDAK termasuk (di luar scope sub-proyek 1)
- Tampilan/konsumsi sisi reseller atau agent (mereka tidak mengakses Marketing Center).
- Tracking download/copy/share aset.
- Personalisasi teks WA (placeholder) — teks WA = template statis.
- Pilar Promo Aktif & Link Order pada dashboard (sub-proyek 2 & 3).
- Penargetan aset per reseller/grup (semua aset aktif berlaku umum).

## Aktor & Hak Akses

| Aktor | Akses |
|---|---|
| **Administrator, Marketing** (role, guard `web`) | Kelola penuh kategori & aset; pakai aset di course-builder Training |
| **Reseller, Agent** | TIDAK ada akses ke Marketing Center |

Resource permission baru: **`Marketing Center`** (`is_create/read/update/delete`),
di-seed untuk role **Administrator** (`08d263b7-2c3b-43f0-a49b-b80d9d4b7685`) dan
**Marketing** (`c1a2b3d4-e5f6-4a01-8b02-000000000001`, dibuat di Training Academy).
Super Admin bypass otomatis. Wiring lewat **seeder aditif** (pola sama seperti
Training Academy `TrainingAccessSeeder` — JANGAN jalankan MenuSeeder/IamHasAccessSeeder
yang `TRUNCATE`).

## Arsitektur & Pola yang Diikuti

- **DB:** PostgreSQL schema baru `marketing` (pola sama seperti `training.*`, `partner.*`).
  Migration di `database/migrations/marketing/`, schema dibuat via
  `DB::statement('CREATE SCHEMA IF NOT EXISTS marketing')`. **Daftarkan folder
  `marketing` di tiga tempat** (belajar dari bug Training): `AppServiceProvider::loadMigrationsFrom`,
  `MigrateAllCommand::$migrationPaths`, `MigrateAllCommand::$customSchemas`.
- **PK:** UUID v7 (`->default(DB::raw('public.uuid_generate_v7()'))`), `company_id`
  uuid nullable, audit columns + `timestamps()` + `softDeletes()`.
- **Models:** `app/Models/Marketing/`.
- **Controllers:** `app/Http/Controllers/Admin/Marketing/`.
- **Routes:** file baru `routes/marketing.php`, di-`require` dari `routes/web.php`.
- **Views:** `resources/views/admin/marketing/`, komponen `<x-app-layout>`, `<x-page-header>`, `<x-alert>`.
- **File upload:** disk `public` (`->store('marketing/assets', 'public')`).
- **FK target:** company → `master_data.business_units`.

## Skema Database

### `marketing.asset_categories`

| Kolom | Tipe | Catatan |
|---|---|---|
| id | uuid PK | uuid v7 |
| company_id | uuid nullable | |
| name | string(150) | |
| color | string(7) nullable | hex |
| icon | string(60) nullable | class `ti-*` |
| sort_order | integer default 0 | |
| is_active | boolean default true | |
| audit + timestamps + softDeletes | | |

### `marketing.assets`

| Kolom | Tipe | Catatan |
|---|---|---|
| id | uuid PK | |
| company_id | uuid nullable | |
| category_id | uuid FK → asset_categories | onDelete restrict |
| title | string(200) | |
| description | text nullable | |
| type | string(20) | `image` \| `video` \| `pdf` \| `text` |
| file_path | string nullable | wajib untuk `image`/`pdf` (disk public) |
| link_url | string(500) nullable | wajib untuk `video` (URL IG/TikTok/YouTube/dll) |
| body_text | text nullable | wajib untuk `text` (teks WA) |
| usable_in_marketing | boolean default true | scope: aset pemasaran (reseller-facing kelak) |
| usable_in_training | boolean default false | scope: boleh dipilih di course-builder |
| can_be_thumbnail | boolean default false | hanya berarti untuk `image` |
| status | string(20) default 'draft' | `draft` \| `active` |
| sort_order | integer default 0 | |
| audit + timestamps + softDeletes | | |

Index: `company_id`, `category_id`, `type`, `status`.
Aturan: `text` hanya boleh `usable_in_marketing` (tak dipakai Training). `can_be_thumbnail`
hanya boleh true bila `type = image`.

### Perubahan tabel Training Academy (integrasi referensi)

- `training.course_materials` + `marketing_asset_id` uuid nullable FK → `marketing.assets`
  (onDelete restrict). Bila terisi, konten materi berasal dari aset.
- `training.courses` + `thumbnail_asset_id` uuid nullable FK → `marketing.assets`
  (onDelete restrict). Thumbnail efektif = aset image bila direferensikan, else `thumbnail_path`.

Kedua kolom ditambah lewat migration baru di `database/migrations/marketing/` (bukan
mengedit migration training yang sudah jalan).

## Model Aset (single-table, type-conditional)

Satu tabel `marketing.assets` dengan field kondisional per `type` — pola sama seperti
`training.course_materials` (pdf/image/youtube). Accessor:
- `Asset::getFileUrlAttribute()` → `Storage::url(file_path)` untuk image/pdf.
- `Asset::getIsYoutubeAttribute()` / helper embed untuk video link (deteksi YouTube via
  `App\Support\YouTube::embedId()` yang sudah ada; link non-YouTube = link biasa).

## UX Pengelola (Administrator + Marketing)

- **Menu "Marketing Center"** di sidebar admin → landing = daftar aset (pilar Materi Promosi).
  Menu/pilar lain (Promo, Link Order) menyusul di sub-proyek berikutnya.
- **Kategori aset** (`/marketing/categories`): CRUD sederhana via modal (nama, color picker,
  ikon, urutan, aktif) — pola identik dgn kategori Training.
- **Aset** (`/marketing/assets`):
  - **Index**: daftar aset + tipe, kategori, scope badge (Marketing/Training), status; aksi
    edit/hapus/preview.
  - **Form tipe-aware**: pilih `type`; image/pdf → upload file; video → input `link_url`;
    text → textarea `body_text`. Centang scope `usable_in_marketing` / `usable_in_training`
    (minimal satu). Bila image, tampilkan `can_be_thumbnail`. Status draft/aktif. Kategori.
  - **Preview/aksi**: image → tampil gambar; pdf → tombol buka; video → embed (YouTube)
    atau link; text → tampil teks + tombol **Salin**.
  - **Proteksi hapus**: aset yang direferensikan `course_materials`/`courses` tidak bisa
    dihapus (pesan jelas berapa course memakainya).

## Integrasi Course-Builder Training Academy

- **Materi course** (di `content.blade.php` builder): untuk tipe gambar/pdf/video, tambah
  opsi **"Pilih dari Pustaka"** di samping "Upload". Membuka modal daftar aset dengan
  `usable_in_training = true`, `status = active`, tipe cocok (image→image, pdf→pdf,
  video→video). Menyimpan `marketing_asset_id` pada materi. Teks WA (`text`) TIDAK muncul.
- **Thumbnail course** (form course create/edit): opsi pilih aset image ber-`can_be_thumbnail`
  = true & aktif; menyimpan `thumbnail_asset_id`. Kalau dipilih, mengalahkan upload `thumbnail_path`.
- **Rendering** (course detail/material viewer & dashboard):
  - Thumbnail efektif: `thumbnail_asset_id ? asset.file_url : thumbnail_url`.
  - Materi ber-`marketing_asset_id`: konten dari aset. image → `<img>`; pdf → iframe;
    video → bila YouTube embed iframe, bila link lain → kartu tombol "Buka video" (target _blank).
  - Materi tanpa `marketing_asset_id`: perilaku lama (file_path/youtube_url) tetap.
- **Proteksi**: existing viewer `CourseMaterial` menambah cara resolve konten
  (asset-vs-local) via accessor terpusat agar view tidak bercabang rumit.

## Access, Role, Routing

- Guard `web`, semua route digate `permission:Marketing Center,is_*`.
- Route prefix `/marketing`, name `marketing.*`:
  - `categories.index|store|update|destroy`
  - `assets.index|create|store|edit|update|destroy`
  - `assets.picker` (JSON/partial untuk modal picker di course-builder) —
    `permission:Marketing Center,is_read` (course-builder juga butuh, dan Administrator/Marketing
    yang mengelola course sudah punya akses Training + Marketing).
- File `routes/marketing.php` di-`require` dari `routes/web.php`.
- Seeder aditif `MarketingAccessSeeder`: menu "Marketing Center" + grant CRUD ke
  Administrator & Marketing (idempotent, tanpa truncate). Registrasi di `DatabaseSeeder`
  setelah `TrainingAccessSeeder`.

## Error Handling & Edge Cases

- **Validasi kondisional per tipe** (FormRequest): `image` → file mimes jpg/jpeg/png/webp;
  `pdf` → mimes pdf; `video` → `link_url` required + URL valid; `text` → `body_text` required.
  Field yang tak sesuai tipe di-null-kan saat simpan.
- **Scope**: minimal satu dari `usable_in_marketing`/`usable_in_training` harus true.
  `text` tidak boleh `usable_in_training`. `can_be_thumbnail` hanya bila `image`.
- **Draft**: aset draft tak muncul di picker Training maupun (kelak) view reseller.
- **Soft delete**: aset & kategori soft-deleted di-exclude di semua query & picker.
- **Proteksi hapus** aset yang dipakai course (materials/thumbnail) dan kategori yang punya aset.
- **Video non-YouTube di course**: viewer render sebagai kartu link, bukan embed.

## Testing / Verifikasi

Project TIDAK punya automated test suite — verifikasi via `php -l` lint + `php artisan route:list`
+ runtime `tinker`:
- Migrasi `marketing` jalan (2 tabel) + kolom FK di training bertambah.
- Buat kategori & aset tiap tipe; validasi kondisional.
- Referensikan aset dari materi course & thumbnail; render benar; proteksi hapus aktif.
- Picker hanya menampilkan aset `usable_in_training` + aktif + tipe cocok.
- Route `marketing.*` terdaftar; permission gating benar (role tanpa akses → 403).

## Catatan Migrasi (penting, belajar dari Training Academy)

Daftarkan `database/migrations/marketing` di **tiga** tempat: `AppServiceProvider::loadMigrationsFrom`,
`MigrateAllCommand::$migrationPaths`, `MigrateAllCommand::$customSchemas` — agar env
baru (`migrate` / `db:migrate-all` / `--fresh`) membuat & menjatuhkan schema dengan benar.
Jalankan hanya seeder aman (aditif) pada DB bersama; jangan `MenuSeeder`/`IamHasAccessSeeder`.
