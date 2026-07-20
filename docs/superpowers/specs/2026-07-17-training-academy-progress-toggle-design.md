# Training Academy — Toggle Persentase Pembelajaran (Progress Visibility) Design Spec

**Tanggal:** 2026-07-17
**Branch:** akan dibuat saat implementasi, mis. `feature/academy-progress-toggle`.
**Status:** Approved (brainstorming) — siap diimplementasikan.
**Catatan:** Implementasi fitur ini dikerjakan via AI Coding Assistant lain (Cursor Composer), bukan lewat alur subagent-driven-development di sesi ini. Dokumen ini adalah PRD; lihat juga dokumen pendamping "system prompt" untuk instruksi teknis siap pakai.

## Konteks & Latar Belakang

Training Academy sudah dibangun (lihat `docs/superpowers/specs/2026-07-13-training-academy-design.md`) dan menampilkan **persentase pembelajaran** (progress bar + "X% selesai") ke Agent di beberapa tempat. Belakangan, menu sidebar Training Academy (baik sisi admin "Kelola" maupun sisi Agent "Belajar") **sengaja disembunyikan** lewat commit `2510d62d` ("hide unused menus") — seeder `TrainingAccessSeeder` saat ini isinya menghapus (soft-delete) menu tersebut alih-alih membuatnya.

Revisi ini punya dua bagian:
1. **Fitur baru:** admin bisa menyalakan/mematikan tampilan persentase pembelajaran secara global lewat satu toggle konfigurasi. Saat dimatikan, seluruh indikator progress (bar + persen + jumlah materi + estimasi menit) hilang total dari sisi Agent — bukan disembunyikan sebagian.
2. **Reaktivasi:** menu Training Academy yang sedang disembunyikan (Kelola + Belajar) diaktifkan kembali di sidebar, ditambah 1 menu baru "Pengaturan Academy".

## Yang TIDAK termasuk

- Toggle per-course (ini toggle **global**, satu untuk semua course).
- Perubahan pada laporan admin/manager (`admin/training/reports`) — halaman itu sudah tidak menampilkan persentase sama sekali, tidak tersentuh.
- Permission resource baru — toggle ini pakai permission **"Training Academy"** yang sudah ada.

## Aktor & Akses

| Aktor | Akses |
|---|---|
| **Administrator, Marketing** | Kelola penuh Training Academy (sudah ada) + akses baru: ubah toggle di halaman "Pengaturan Academy" |
| **Agent** | Baca konten Academy (sudah ada); tampilan progress otomatis mengikuti toggle admin |

Semua digate lewat permission resource **"Training Academy"** (admin) dan **"Academy"** (Agent) yang sudah ada — tidak ada permission baru.

## Skema Database

### Tabel baru: `training.academy_settings`

Pola **single-row config table**, sama seperti `configuration.app_theme_settings` yang sudah ada di project ini.

| Kolom | Tipe | Catatan |
|---|---|---|
| id | uuid PK | uuid v7, `default(DB::raw('public.uuid_generate_v7()'))` |
| show_progress_percentage | boolean default true | `true` = perilaku saat ini (progress terlihat) tetap jalan sampai admin sengaja matikan |
| updated_by | uuid nullable | |
| timestamps | | `created_at`, `updated_at` — TANPA `softDeletes()` (baris konfigurasi, tak pernah dihapus) |

Migration baru di `database/migrations/training/` (folder ini **sudah terdaftar** di `AppServiceProvider::loadMigrationsFrom`, `MigrateAllCommand::$migrationPaths`, `$customSchemas` sejak Training Academy dibangun — **verifikasi saja, jangan daftar ulang**).

## Model

`app/Models/Training/AcademySetting.php` — `connection = 'pgsql'`, `table = 'training.academy_settings'`, trait `HasUuids` (tanpa `SoftDeletes`), `$fillable = ['show_progress_percentage', 'updated_by']`, cast `show_progress_percentage => boolean`.

Method statis `AcademySetting::current(): self` — mengembalikan baris config, membuat otomatis dengan default `show_progress_percentage = true` kalau baris belum ada (`firstOrCreate([], [...])`). Ini menghindari kebutuhan seeding manual untuk baris konfigurasi ini.

## Halaman Admin: "Pengaturan Academy"

Route baru di dalam grup `training.` (`routes/training.php`, di dalam `Route::prefix('training')->name('training.')->group(...)` yang sudah ada):

```
GET  /training/settings  → training.settings.edit    (permission:Training Academy,is_update)
POST /training/settings  → training.settings.update  (permission:Training Academy,is_update)
```

Controller baru `app/Http/Controllers/Admin/Training/AcademySettingController.php`:
- `edit()`: ambil `AcademySetting::current()`, tampilkan form.
- `update(Request $request)`: validasi `show_progress_percentage` boolean, update baris config (`updated_by` = user login), redirect balik dengan pesan sukses.

View baru `resources/views/admin/training/settings/edit.blade.php`: form sederhana — satu toggle switch berlabel **"Tampilkan persentase pembelajaran ke Agent"**, teks bantuan menjelaskan efeknya ("Saat dimatikan, progress bar dan info persen pembelajaran tidak akan tampil sama sekali ke Agent"), tombol Simpan. Pakai komponen yang sudah ada di project (`<x-app-layout>`, `<x-page-header>`, `<x-alert>` untuk pesan sukses).

## Reaktivasi Menu

`database/seeders/TrainingAccessSeeder.php` dikembalikan ke versi aditif semula (lihat isi lengkap di commit `d9db94d7`, sebelum diubah jadi soft-delete oleh `2510d62d`) — memulihkan:

- Menu **"Training Academy"** (id `c1a2b3d4-e5f6-4a01-8b02-000000000010`, route `training.courses.index`, icon `ti ti-school`, order 900) → grant CRUD ke Administrator + Marketing.
- Menu **"Academy"** (id `c1a2b3d4-e5f6-4a01-8b02-000000000011`, route `academy.dashboard`, icon `ti ti-book`, order 901) → grant read-only ke Agent.

Ditambah menu baru:

- Menu **"Pengaturan Academy"** (id `c1a2b3d4-e5f6-4a01-8b02-000000000012` — nilai pasti, verbatim, mengikuti pola penomoran menu 010/011, route `training.settings.edit`, icon `ti ti-settings`, order 902, `has_page=true`, `has_read/update=true`, `has_create/delete=false` karena cuma edit-1-baris) → grant read+update ke Administrator + Marketing (pola grant sama seperti menu 010, tapi `create: false, delete: false` karena tak ada aksi tambah/hapus di halaman ini).

Seeder tetap **aditif** (`Menu::updateOrCreate`, `IamAccess::firstOrCreate`, `IamHasAccess::updateOrCreate`) — TIDAK menjalankan `MenuSeeder`/`IamHasAccessSeeder` yang truncate.

## Tampilan Agent (kondisional)

`app/Http/Controllers/Academy/AcademyController.php`:
- `dashboard()`: tambahkan `$showProgress = \App\Models\Training\AcademySetting::current()->show_progress_percentage;`, kirim ke view.
- `course(Course $course)`: sama, kirim `$showProgress` ke view.

Bungkus 4 blok render berikut dengan `@if($showProgress) ... @endif` (blok hilang total dari HTML saat `false`, bukan disembunyikan via CSS):

1. `resources/views/academy/dashboard.blade.php` — progress bar statistik keseluruhan (modules_completed/modules_total).
2. `resources/views/academy/dashboard.blade.php` — baris progress pada kartu "lanjutkan belajar" (bar + `{{ $cp['percent'] }}% selesai` + menit tersisa).
3. `resources/views/academy/dashboard.blade.php` — baris progress pada tiap kartu course di daftar (loop `$progressByCourse`).
4. `resources/views/academy/course.blade.php` — blok progress bar + baris info (`X/Y materi · Z% selesai · W menit tersisa`) di halaman detail course.

**Penting:** saat `$showProgress = false`, SELURUH baris info (termasuk jumlah materi selesai dan estimasi menit tersisa — bukan cuma angka persennya) ikut hilang, sesuai keputusan user. Data underlying (`completed_count`, `total_materials`, dst. dari `ProgressService`) tetap dihitung seperti biasa — cuma tidak dirender ke Blade.

## Edge Cases

- Baris `academy_settings` belum ada saat pertama kali diakses → `AcademySetting::current()` otomatis membuatnya dengan default `true` (tak ada migrasi data manual/seeding terpisah dibutuhkan).
- Toggle ini TIDAK memengaruhi logika pencatatan progress (`ProgressService`, `MaterialProgress`) — murni soal apa yang dirender, bukan apa yang dihitung/disimpan.
- Menu "Pengaturan Academy" hanya untuk Administrator + Marketing; Agent tidak melihat menu ini sama sekali (tidak ada grant untuk role Agent).

## Testing / Verifikasi

Project ini TIDAK punya automated test suite (kebijakan yang disengaja) — verifikasi via `php -l`, `php artisan route:list`, `php artisan view:cache`, dan smoke test `tinker`:
- Migrasi `academy_settings` jalan; `AcademySetting::current()` membuat baris default saat kosong.
- Toggle off → keempat lokasi render benar-benar hilang dari HTML (cek via `view()->render()` atau akses langsung); toggle on → tampil seperti semula.
- Ketiga menu (Training Academy, Academy, Pengaturan Academy) muncul di sidebar dengan grant yang benar per role; seeder idempotent (jalan 2x hasil sama).
- Route `training.settings.edit`/`update` ter-gate permission `Training Academy,is_update` (role tanpa akses → 403).
