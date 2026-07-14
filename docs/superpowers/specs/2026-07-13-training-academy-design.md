# Training Academy — Design Spec

**Tanggal:** 2026-07-13
**Branch:** `feature/training-academy`
**Status:** Approved (brainstorming) — siap masuk tahap implementation plan.

## Ringkasan

Modul internal untuk pelatihan **agent**: admin & marketing membuat materi belajar
(course → modul → materi), agent mempelajarinya dan progres belajarnya tercatat.
**Tidak ada sertifikasi** — murni pembelajaran. Reseller tidak memiliki akses.

Semua berjalan di dalam guard `web` (aplikasi admin WMS yang sudah ada), dibedakan
lewat sistem permission/role yang sudah berlaku (resource + flag
`is_read/is_create/is_update/is_delete`). Tidak ada portal terpisah.

## Aktor & Hak Akses

| Aktor | Peran | Akses |
|---|---|---|
| **Admin, Marketing** | Pengelola | CRUD penuh course/modul/materi/kategori + lihat laporan progres |
| **Agent** (User ber-role Agent) | Learner | Melihat & mempelajari course published, progres tercatat |
| **Reseller** | — | Tidak ada akses ke modul ini |

Learner = `User` yang merupakan agent (model `App\Models\Partner\Agent` punya
`user_id` → agent login sebagai user biasa). Progres di-key ke `user_id`.

## Arsitektur & Pola yang Diikuti

- **DB:** PostgreSQL schema baru `training` (pola sama seperti `manufacturing.*`,
  `partner.*`). Migration di folder `database/migrations/training/`. Schema dibuat
  via `DB::statement('CREATE SCHEMA IF NOT EXISTS training')` di migration pertama.
- **PK:** UUID v7 (`->default(DB::raw('public.uuid_generate_v7()'))`), semua tabel
  punya `company_id` (nullable uuid), audit columns (`created_by/updated_by/deleted_by`),
  `timestamps`, dan soft delete kecuali tabel progres.
- **Models:** `app/Models/Training/`.
- **Controllers:** pengelola di `app/Http/Controllers/Admin/Training/`, learner di
  `app/Http/Controllers/Academy/`.
- **Routes:** file baru `routes/training.php`, di-`require` dari `routes/web.php`
  (pola sama seperti `distribution.php`). Grup dibungkus
  `Route::middleware(['auth','verified'])`.
- **Views:** Blade server-rendered, mengikuti komponen di `/design-system`.
- **File upload:** disk `public` (`->store('training/...', 'public')`), pola sama
  seperti `ProfileController` (`images/profile`).
- **Menu & permission:** di-seed via `MenuSeeder`, `IamAccessSeeder`, `RoleSeeder`.

## Skema Database

Progress model = **Approach A** (dua tabel progres: material-level + course-level access).

### `training.course_categories`
Master kategori (chip "Penjualan", "Digital", dll di dashboard).

| Kolom | Tipe | Catatan |
|---|---|---|
| id | uuid PK | uuid v7 |
| company_id | uuid nullable | |
| name | string | |
| color | string(7) nullable | hex, mis. `#5C9E84` (warna kartu/chip) |
| icon | string nullable | class ikon, mis. `ti-briefcase` |
| sort_order | integer default 0 | |
| is_active | boolean default true | |
| audit + timestamps + softDeletes | | |

### `training.courses`

| Kolom | Tipe | Catatan |
|---|---|---|
| id | uuid PK | |
| company_id | uuid nullable | |
| category_id | uuid FK → course_categories | |
| title | string | |
| description | text nullable | |
| thumbnail_path | string nullable | path di disk `public` |
| status | string | `draft` \| `published`, default `draft` |
| published_at | timestamp nullable | diisi saat pertama published |
| sort_order | integer default 0 | |
| audit + timestamps + softDeletes | | |

Agent hanya melihat course `status = 'published'`.

### `training.course_modules`

| Kolom | Tipe | Catatan |
|---|---|---|
| id | uuid PK | |
| course_id | uuid FK → courses (cascade) | |
| title | string | |
| description | text nullable | |
| sort_order | integer default 0 | urutan modul dalam course |
| audit + timestamps + softDeletes | | |

### `training.course_materials`

| Kolom | Tipe | Catatan |
|---|---|---|
| id | uuid PK | |
| module_id | uuid FK → course_modules (cascade) | |
| title | string | |
| type | string | `pdf` \| `image` \| `youtube` |
| file_path | string nullable | wajib jika type `pdf`/`image` (disk `public`) |
| youtube_url | string nullable | wajib jika type `youtube` (URL disimpan, embed id diturunkan) |
| estimated_minutes | integer nullable | **opsional**; dasar metrik "Jam Belajar"/"menit tersisa" |
| sort_order | integer default 0 | |
| audit + timestamps + softDeletes | | |

### `training.material_progress`
Sumber kebenaran status materi per agent. Tanpa soft delete.

| Kolom | Tipe | Catatan |
|---|---|---|
| id | uuid PK | |
| user_id | uuid FK → users | |
| material_id | uuid FK → course_materials | |
| viewed_at | timestamp | di-set saat materi pertama dibuka |
| completed_at | timestamp nullable | di-set saat tombol "Tandai selesai" |
| timestamps | | |

Unique `(user_id, material_id)`.

### `training.course_access`
Log buka course + pointer resume (untuk "Sedang Dipelajari" & tombol "Lanjutkan").
Tanpa soft delete.

| Kolom | Tipe | Catatan |
|---|---|---|
| id | uuid PK | |
| user_id | uuid FK → users | |
| course_id | uuid FK → courses | |
| first_opened_at | timestamp | pemenuhan brief "log saat buka course" |
| last_accessed_at | timestamp | untuk mengurutkan "Sedang Dipelajari" |
| last_material_id | uuid nullable FK → course_materials | resume pointer |
| timestamps | | |

Unique `(user_id, course_id)`.

## Routing & Permission

Dua resource permission terpisah:

- **`Training Academy`** (pengelola) → di-seed untuk role **Admin & Marketing**
  (`is_create/is_read/is_update/is_delete`).
- **`Academy`** (learner) → di-seed untuk role **Agent** (`is_read`).

### Route pengelola — prefix `/training`, name `training.*`
- `categories` — index/store/update/destroy (`permission:Training Academy,is_*`)
- `courses` — index/create/store/edit/update/destroy/show
- `courses/{course}/content` — halaman content builder
- modul & materi — store/update/destroy/reorder (nested di bawah course)
- `reports` — halaman laporan progres agent (`permission:Training Academy,is_read`)

### Route learner — prefix `/academy`, name `academy.*` (`permission:Academy,is_read`)
- `GET /academy` — dashboard agent
- `GET /academy/courses/{course}` — detail course (daftar modul & materi)
- `GET /academy/materials/{material}` — viewer materi (log `viewed_at` + upsert `course_access`)
- `POST /academy/materials/{material}/complete` — set `completed_at`

## UX Pengelola (Admin / Marketing)

1. **Kategori** — CRUD sederhana: nama, color picker, ikon, urutan, aktif/nonaktif.
2. **Courses index** — daftar course + status (draft/published), kategori, jumlah
   modul & materi, aksi (edit / kelola isi / hapus).
3. **Course form** — title, kategori, deskripsi, upload thumbnail (disk `public`,
   `training/thumbnails/`), status draft/published.
4. **Content builder** (`courses/{course}/content`) — kelola Modul & Materi dalam
   satu course: tambah/edit/hapus/urutkan modul; dalam tiap modul tambah/edit/hapus/
   urutkan materi. Form materi menyesuaikan tipe: PDF/gambar → upload file; YouTube
   → input URL. Field `estimasi_menit` opsional.

## UX Agent + Logika Progres

- **Dashboard `/academy`** (mengikuti screenshot referensi, **tanpa Sertifikat**):
  - Header stat: **Modul Selesai X/Y** (agregat seluruh course published), **Jam
    Belajar** (Σ `estimated_minutes` materi completed; hanya materi yang punya
    estimasi).
  - Blok **"Sedang Dipelajari"**: course dengan `course_access.last_accessed_at`
    terbaru dan belum 100% selesai, dengan progress bar + tombol **"Lanjutkan"**
    (menuju `last_material_id`).
  - Grid **"Semua Kursus"**: hanya course `published`, tiap kartu tampilkan
    thumbnail, kategori, progress bar, dan tombol berlabel kondisional
    **Mulai / Lanjutkan / Ulangi** (0% / sebagian / 100%).
- **Course detail `/academy/courses/{course}`** — daftar modul → materi dengan
  indikator centang "selesai"; klik materi → viewer.
- **Material viewer `/academy/materials/{material}`**:
  - Render sesuai tipe: PDF → iframe; gambar → `<img>`; YouTube → iframe embed
    (dari URL yang diparse ke embed id).
  - Saat halaman dibuka → upsert `material_progress` (`viewed_at`) + upsert
    `course_access` (`first_opened_at` jika baru, `last_accessed_at`,
    `last_material_id`).
  - Tombol **"Tandai selesai"** → set `material_progress.completed_at`.
  - Navigasi materi sebelumnya/berikutnya.

### `App\Services\Training\ProgressService`
Satu tempat untuk semua hitungan progres:
- `courseProgress(User $user, Course $course)`: `total_materials`, `completed_count`,
  `percent` (`completed/total`), `modules_completed` (modul yang **semua** materinya
  completed), `minutes_done` (Σ estimasi materi completed), `minutes_remaining`
  (Σ estimasi materi belum completed) — keduanya **null-aware** (materi tanpa
  estimasi di-exclude).
- `dashboardStats(User $user)`: agregat lintas semua course published untuk header.

Hanya materi/modul/course yang **tidak** soft-deleted dan course **published**
yang dihitung untuk agent.

## Laporan Ringkas (Admin / Marketing)

Halaman `/training/reports`: tabel agent dengan kolom jumlah **course selesai**,
**materi selesai**, dan **aktivitas terakhir** (`MAX(last_accessed_at)`). Agregasi
dari `material_progress` + `course_access` join ke `users` (agent). Opsional
drill-down per agent (daftar course + status). Drill-down boleh masuk sebagai
peningkatan setelah tabel ringkas jadi.

## Error Handling & Edge Cases

- **Soft delete** course/modul/materi: baris progres lama tetap tersimpan tapi
  **di-exclude** dari perhitungan (join hanya ke record aktif).
- **Course draft**: tak muncul & tak ikut agregasi untuk agent.
- **Materi tanpa `estimated_minutes`**: di-exclude dari total menit; UI tampil "—".
- **Upload**: validasi tipe & ukuran (`pdf` → mime pdf; `image` → jpg/png).
- **YouTube URL**: terima format panjang (`watch?v=`) & pendek (`youtu.be/`),
  derive embed id, validasi gagal jika tak dikenali.
- **Field required kondisional**: `file_path` wajib untuk pdf/image, `youtube_url`
  wajib untuk youtube — divalidasi di FormRequest berdasarkan `type`.

## Testing

- **Unit `ProgressService`**: transisi viewed→completed; hitung persen; `modules_completed`;
  menit opsional (null-aware); exclude soft-deleted & draft.
- **Feature — kontrol akses**: agent tak bisa akses route pengelola; admin/marketing
  bisa; agent hanya melihat course published.
- **Feature — learner flow**: buka materi men-set `viewed_at` + `course_access`;
  "Tandai selesai" men-set `completed_at`; dashboard menampilkan angka benar.
- **Feature — upload & YouTube**: validasi file; parsing/validasi URL YouTube.

## Yang Sengaja TIDAK Termasuk (YAGNI)

- Sertifikasi / sertifikat.
- Tracking durasi real-time (timer/heartbeat) — diganti `estimated_minutes` manual opsional.
- Pengambilan durasi video via YouTube API — bisa ditambah belakangan.
- Penargetan/assignment course ke agent/grup tertentu — semua agent melihat semua published.
- Akses reseller.
