# Cursor Handoff — Viewer Training/Materi Agen (slice 5b)

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-23-agent-training-viewer-cursor-handoff.md".
> Aturan permanen ada di `.cursorrules`. Slice TERPISAH — tambah route/method/view baru + update tautan **Pelatihan** di dashboard. Jangan sentuh modul Academy (guard web) yang sudah ada.
>
> **KOREKSI (revisi):** "Materi Pemasaran" dan "Pelatihan" adalah DUA hal berbeda. Handoff INI hanya untuk **Pelatihan** (= konten Training). "Materi Pemasaran" (= marketing asset) dikerjakan di handoff terpisah `2026-07-23-agent-marketing-materials-cursor-handoff.md` (slice 5c). Di slice ini JANGAN mengarahkan section/nav "Materi Pemasaran" ke halaman training — hanya section/card "Pelatihan".

## Tujuan

Halaman **Pelatihan** untuk agen (guard `customer` + `agent`): viewer **read-only** konten Training Academy (daftar course → detail course → buka materi). TANPA pelacakan progres. Hanya section/card **"Pelatihan"** di dashboard yang mengarah ke halaman ini.

## Konteks (sudah ada)

- Modul Training Academy ada (guard web, `permission:Academy`) — JANGAN dipakai untuk agen (beda guard). Buat viewer agen sendiri.
- Model `App\Models\Training\Course` (scope `published()`, relasi `modules()` → `CourseModule` → `materials()` → `CourseMaterial`, accessor `getThumbnailUrlAttribute()`).
- `App\Models\Training\CourseMaterial` punya accessor siap-pakai: `effective_type`, `effective_file_url`, `effective_video_url`, `effective_video_embed_id` (menangani materi lokal maupun asset-backed). Data: ada course published + materi.
- Acuan render materi: `resources/views/academy/material.blade.php` (blok `@php($et = $material->effective_type)` → video embed/link, image, pdf iframe). Acuan struktur course: `resources/views/academy/course.blade.php`. **Salin pola render-nya, tanpa bagian progres/tombol "tandai selesai".**
- Portal agen: `AgentOrderController`, route group `agent-order.*`, layout `layouts.agent-order`.

## Langkah 1 — Route

Di `routes/agent.php`, DI DALAM grup `['auth:customer','agent']`, tambah:
```php
Route::get('/pelatihan', [AgentOrderController::class, 'training'])->name('training');
Route::get('/pelatihan/{course}', [AgentOrderController::class, 'trainingShow'])->name('training.show');
```
Verifikasi: `php artisan route:list --name=agent-order.training`.

## Langkah 2 — Controller `training()` + `trainingShow()`

```php
public function training(): View
{
    $courses = \App\Models\Training\Course::published()
        ->with('category')
        ->orderBy('sort_order')->orderByDesc('published_at')
        ->get();

    return view('agent.order.training.index', ['courses' => $courses]);
}

public function trainingShow(string $courseId): View
{
    $course = \App\Models\Training\Course::published()
        ->with(['category', 'modules' => fn ($q) => $q->orderBy('sort_order'), 'modules.materials' => fn ($q) => $q->orderBy('sort_order')])
        ->findOrFail($courseId);          // course tak published → 404

    return view('agent.order.training.show', ['course' => $course]);
}
```
Catatan: cek nama scope/relasi aktual (`published()`, `modules()`, `materials()`, kolom urut `sort_order`) via grep sebelum pakai; sesuaikan bila beda. Verifikasi `php -l`.

## Langkah 3 — View daftar course `agent.order.training.index`

Buat `resources/views/agent/order/training/index.blade.php` extends `layouts.agent-order`:
- Header "Pelatihan" + subjudul "Materi pelatihan untuk agen." + tombol kembali dashboard.
- Grid kartu course: `getThumbnailUrlAttribute` (`$course->thumbnail_url`, fallback ikon/warna kategori), `title`, `category?->name`, deskripsi singkat. Kartu link ke `route('agent-order.training.show', $course->id)`.
- Empty state "Belum ada materi pelatihan." bila kosong.

## Langkah 4 — View detail course `agent.order.training.show`

Buat `resources/views/agent/order/training/show.blade.php` extends `layouts.agent-order`:
- Header: judul course + kategori + deskripsi + tombol kembali ke `agent-order.training`.
- Loop `$course->modules` → tiap modul: judul + deskripsi, lalu loop `$module->materials` → tiap materi render sesuai `effective_type` (SALIN pola dari `academy/material.blade.php`, TANPA progres):
```blade
@php($et = $material->effective_type)
@if ($et === 'video')
    @if ($material->effective_video_embed_id)
        <div class="ratio ratio-16x9 mb-2"><iframe src="https://www.youtube.com/embed/{{ $material->effective_video_embed_id }}" title="{{ $material->title }}" allowfullscreen></iframe></div>
    @elseif ($material->effective_video_url)
        <a href="{{ $material->effective_video_url }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="ti ti-external-link me-1"></i>Buka video</a>
    @endif
@elseif ($et === 'image' && $material->effective_file_url)
    <img src="{{ $material->effective_file_url }}" alt="{{ $material->title }}" class="img-fluid rounded mb-2">
@elseif ($et === 'pdf' && $material->effective_file_url)
    <div class="ratio ratio-4x3 mb-2"><iframe src="{{ $material->effective_file_url }}" title="{{ $material->title }}"></iframe></div>
    <a href="{{ $material->effective_file_url }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="ti ti-download me-1"></i>Buka PDF</a>
@else
    <div class="text-muted small">Materi belum tersedia.</div>
@endif
```
  Tampilkan `title` materi di atas tiap blok. JANGAN menulis `MaterialProgress` / tombol "tandai selesai" (viewer read-only).
- Empty state bila course tak punya modul/materi.

Verifikasi: `php artisan view:cache` sukses, lalu `view:clear`.

## Langkah 5 — Aktifkan tautan Pelatihan di dashboard

Di `resources/views/agent/order/dashboard.blade.php`, ganti placeholder "Segera hadir" pada bagian **Pelatihan** saja menjadi tautan asli ke `route('agent-order.training')`:
- Section "Pelatihan" (card "Dasar Penjualan Agent" / daftar course) → `href="{{ route('agent-order.training') }}"`.

**JANGAN sentuh**: placeholder "Semua reseller" (slice 5a) DAN placeholder "Materi Pemasaran" (kartu nav + section "Materi pemasaran" + "Lihat semua") — "Materi Pemasaran" diarahkan ke halaman marketing asset di slice 5c, bukan ke training.

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentOrderController.php
php artisan route:list --name=agent-order.training
php artisan view:cache && php artisan view:clear
```
Smoke manual (login customer-agent):
- `/agen-order/pelatihan` → daftar course (min. 1 course published). Klik → detail course menampilkan modul & materi; pdf/video/gambar dapat dibuka/tampil. TIDAK ada tombol progres.
- Course tak published diakses via URL → 404.
- Dashboard "Pelatihan" mengarah ke `/agen-order/pelatihan` (bukan "Segera hadir"). "Materi Pemasaran" TIDAK diubah di slice ini (ditangani slice 5c).
- Modul Academy web (`/academy`) & alur agent lain tak berubah.

## Checklist

- [ ] Route `agent-order.training` + `agent-order.training.show` terdaftar (guard customer + agent).
- [ ] `training()`/`trainingShow()` baca course published (404 bila tidak); nama scope/relasi diverifikasi.
- [ ] View daftar course + detail (render materi via effective_type, TANPA progres).
- [ ] Dashboard: HANYA section/card Pelatihan diarahkan ke `agent-order.training` (Materi Pemasaran & Reseller TIDAK disentuh).
- [ ] view:cache bersih; modul Academy web & slice lain tak berubah.
