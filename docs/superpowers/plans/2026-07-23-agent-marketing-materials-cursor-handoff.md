# Cursor Handoff — Viewer Materi Pemasaran Agen (slice 5c)

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-23-agent-marketing-materials-cursor-handoff.md".
> Aturan permanen ada di `.cursorrules`. Slice TERPISAH — tambah route/method/view baru + update tautan **Materi Pemasaran** di dashboard. Jangan sentuh admin Marketing Center (guard web) yang sudah ada, dan JANGAN sentuh bagian Pelatihan/Reseller (slice lain).

## Konteks penting (revisi)

"Materi Pemasaran" dan "Pelatihan" adalah DUA hal berbeda:
- **Materi Pemasaran** (handoff INI) = aset marketing (`marketing.assets`) yang boleh dipakai agen — brosur/poster (gambar), PDF, video/link, template teks WA. Aksi: Unduh / Buka / Salin sesuai tipe.
- **Pelatihan** = konten Training (handoff `2026-07-23-agent-training-viewer-cursor-handoff.md`, slice 5b) — JANGAN dicampur.

## Konteks (sudah ada)

- Portal agen: `App\Http\Controllers\Agent\AgentOrderController`, route group `agent-order.*` (guard `auth:customer` + `agent`), layout `layouts.agent-order`.
- `App\Models\Marketing\Asset`: scope `active()` (status=active); field `type` (`image`|`pdf`|`video`|`text`), `file_path`, `link_url`, `body_text`, `usable_in_marketing` (boolean), `title`, `description`, `category_id`; accessor `getFileUrlAttribute()` (→ URL file disk public), `getVideoEmbedIdAttribute()` (YouTube embed id / null). Relasi `category()` → `App\Models\Marketing\Category`.
- Dashboard (`resources/views/agent/order/dashboard.blade.php`) sudah ada; kartu nav "Materi Pemasaran" + section "Materi pemasaran" ("Lihat semua"/Unduh/Salin/Buka) saat ini disabled "Segera hadir".
- Data `marketing.assets` sekarang **0 baris** → halaman tampil empty state (normal); otomatis terisi saat admin menambah aset.

## Aturan tampil & aksi (keputusan user)

- Tampilkan aset `status = active` DAN `usable_in_marketing = true`.
- Aksi otomatis per `type`:
  - `image` → tombol **Unduh** (`file_url`) + preview gambar.
  - `pdf` → tombol **Unduh/Buka** (`file_url`).
  - `video` → tombol **Buka** (`link_url`, target _blank); bila `video_embed_id` ada boleh embed YouTube.
  - `text` → tombol **Salin** (menyalin `body_text` ke clipboard) + tampilkan teksnya.

## Langkah 1 — Route

Di `routes/agent.php`, DI DALAM grup `['auth:customer','agent']`, tambah:
```php
Route::get('/materi', [AgentOrderController::class, 'materials'])->name('materials');
```
Verifikasi: `php artisan route:list --name=agent-order.materials`.

## Langkah 2 — Controller `materials()`

```php
public function materials(Request $request): View
{
    $query = \App\Models\Marketing\Asset::query()
        ->active()
        ->where('usable_in_marketing', true)
        ->with('category')
        ->latest('created_at');

    if ($categoryId = $request->get('category_id')) {
        $query->where('category_id', $categoryId);
    }
    if ($type = $request->get('type')) {
        if (in_array($type, ['image', 'pdf', 'video', 'text'], true)) {
            $query->where('type', $type);
        }
    }

    $assets = $query->paginate(24)->withQueryString();

    // kategori yang punya aset marketing (untuk chip filter), aman bila kosong
    $categories = \App\Models\Marketing\Category::whereHas('assets', fn ($q) => $q->active()->where('usable_in_marketing', true))
        ->orderBy('name')->get(['id', 'name']);

    return view('agent.order.materials', [
        'assets' => $assets,
        'categories' => $categories,
        'activeCategoryId' => $categoryId,
        'activeType' => $type,
    ]);
}
```
Catatan: bila relasi `Category::assets()` belum ada, ganti bagian `$categories` dengan mengambil `category_id` distinct dari aset marketing lalu load Category (`Category::whereIn('id', $ids)`). Verifikasi `php -l`.

## Langkah 3 — View `agent.order.materials`

Buat `resources/views/agent/order/materials.blade.php` extends `layouts.agent-order`:
- Header "Materi Pemasaran" + subjudul "Brosur, poster, template WA, video untuk jualan Anda." + tombol kembali dashboard.
- Chip filter kategori opsional (Semua + `$categories`, via `?category_id=`) dan/atau tipe (`?type=`); selalu ada "Semua".
- Grid kartu aset. Tiap kartu: **badge tipe** (IMG/PDF/VIDEO/WA), `title`, `category?->name`, dan aksi sesuai tipe:
```blade
@php($type = $asset->type)
<div class="col-6 col-md-4 col-xl-3">
  <div class="card h-100">
    <div class="asset-thumb position-relative">
      <span class="badge bg-dark position-absolute top-0 start-0 m-2">{{ ['image'=>'IMG','pdf'=>'PDF','video'=>'VIDEO','text'=>'WA'][$type] ?? strtoupper($type) }}</span>
      @if ($type === 'image' && $asset->file_url)
        <img src="{{ $asset->file_url }}" alt="{{ $asset->title }}" class="w-100" style="height:140px;object-fit:cover" loading="lazy">
      @else
        <div class="d-flex align-items-center justify-content-center bg-light" style="height:140px">
          <i class="ti {{ ['pdf'=>'ti-file-type-pdf','video'=>'ti-video','text'=>'ti-brand-whatsapp'][$type] ?? 'ti-photo' }} fs-1 text-muted"></i>
        </div>
      @endif
    </div>
    <div class="card-body">
      <div class="fw-semibold small text-truncate">{{ $asset->title }}</div>
      @if ($asset->category)<div class="text-muted small">{{ $asset->category->name }}</div>@endif
      <div class="mt-2">
        @if (in_array($type, ['image','pdf']) && $asset->file_url)
          <a href="{{ $asset->file_url }}" download target="_blank" class="btn btn-sm btn-outline-primary"><i class="ti ti-download me-1"></i>Unduh</a>
        @elseif ($type === 'video' && $asset->link_url)
          <a href="{{ $asset->link_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="ti ti-external-link me-1"></i>Buka</a>
        @elseif ($type === 'text')
          <button type="button" class="btn btn-sm btn-outline-primary btn-copy-asset" data-text="{{ $asset->body_text }}"><i class="ti ti-copy me-1"></i>Salin</button>
        @endif
      </div>
    </div>
  </div>
</div>
```
- Empty state "Belum ada materi pemasaran." bila kosong (kondisi sekarang: 0 aset).
- Paginasi `{{ $assets->links('pagination::bootstrap-5') }}` bila `hasPages()`.
- JS "Salin" (di `@push('scripts')`):
```blade
<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn-copy-asset');
    if (!btn) return;
    navigator.clipboard.writeText(btn.dataset.text || '').then(() => {
        const old = btn.innerHTML; btn.innerHTML = '<i class="ti ti-check me-1"></i>Tersalin';
        setTimeout(() => { btn.innerHTML = old; }, 1500);
    });
});
</script>
```

Verifikasi: `php artisan view:cache` sukses, lalu `view:clear`.

## Langkah 4 — TAMBAHKAN section "Materi Pemasaran" ke dashboard

**PENTING:** dashboard saat ini BELUM punya section/kartu "Materi Pemasaran" (dulu sempat dihilangkan karena dikira redundan dengan Pelatihan — ternyata BEDA). Jadi di slice ini section itu perlu **DITAMBAHKAN kembali**, bukan sekadar mengganti tautan.

Di `resources/views/agent/order/dashboard.blade.php`:

1. **Kartu nav "Materi Pemasaran"** — tambahkan kartu navigasi (sejajar/berdampingan dengan kartu "Pelatihan" & "Order ke Distributor" yang sudah ada), mengarah ke `route('agent-order.materials')`, judul "Materi Pemasaran", subjudul "Brosur, poster, template WA, video". Tiru gaya `agent-dashboard-nav-card` yang sudah dipakai kartu Pelatihan.

2. **Section "Materi pemasaran"** — tambahkan section (mis. setelah "Reseller Saya", sebelum/berdampingan "Pelatihan", sesuai mockup) dengan header "Materi pemasaran" + tautan "Lihat semua →" ke `route('agent-order.materials')`. Isi: 4 aset teratas dari `Marketing\Asset::active()->where('usable_in_marketing', true)->latest()->limit(4)->get()` — **controller `dashboard()` perlu menambah variabel ini** (mis. `$marketingAssets`) dan mengirimnya ke view. Tiap kartu: badge tipe + `title` + aksi sesuai tipe (Unduh/Buka/Salin, sama seperti Langkah 3). Empty state "Belum ada materi." bila kosong (kondisi sekarang: 0 aset).

Bila menambah data ke `dashboard()`, pastikan null-safe dan JANGAN ubah section lain.

**JANGAN sentuh**: bagian "Pelatihan" (slice 5b) & "Reseller Saya"/"Semua reseller" (slice 5a) — keduanya sudah benar.

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentOrderController.php
php artisan route:list --name=agent-order.materials
php artisan view:cache && php artisan view:clear
```
Smoke manual (login customer-agent):
- `/agen-order/materi` → (data 0 → empty state). Untuk uji fungsi: admin buat 1 aset tiap tipe (active + usable_in_marketing) → kartu muncul; Unduh (image/pdf) mengunduh file; Buka (video) membuka link; Salin (text) menyalin `body_text` ke clipboard.
- Filter kategori/tipe (`?category_id=`/`?type=`) bekerja; invalid diabaikan.
- Dashboard: section + kartu nav "Materi Pemasaran" MUNCUL (baru ditambahkan) dan mengarah ke `/agen-order/materi`; Pelatihan/Reseller tak berubah.
- Admin Marketing Center (guard web) & slice lain tak berubah.

## Checklist

- [ ] Route `agent-order.materials` terdaftar (guard customer + agent).
- [ ] `materials()`: filter active + usable_in_marketing; category/type opsional; paginasi withQueryString.
- [ ] View: kartu per tipe dengan aksi Unduh/Buka/Salin; JS salin; empty state; badge tipe.
- [ ] Dashboard: section + kartu nav "Materi Pemasaran" DITAMBAHKAN (mengarah ke `agent-order.materials`), `dashboard()` mengirim `$marketingAssets`; Pelatihan & Reseller tak disentuh.
- [ ] view:cache bersih; admin Marketing Center & slice lain tak berubah.
