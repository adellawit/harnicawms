# Cursor Handoff — "Lihat semua" Kondisional di Beranda Agen

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-23-agent-dashboard-see-all-visibility-cursor-handoff.md".
> Aturan permanen ada di `.cursorrules`. SCOPE kecil: hanya `AgentOrderController::dashboard()` + `resources/views/agent/order/dashboard.blade.php`. Tak menyentuh halaman lain.

## Tujuan

Sembunyikan tautan **"Lihat semua" / "Semua pesanan" / "Semua reseller"** pada tiap section di Beranda agen **bila data tidak melebihi jumlah yang ditampilkan** (tak ada lagi yang bisa dilihat). Aturan: tampilkan tautan HANYA bila `total > limit_tampil`.

Limit tampil per section (sudah ada di controller): Pesanan aktif = 4, Reseller Saya = 4, Materi Pemasaran = 4, Pelatihan = 3.

## Langkah 1 — Controller `dashboard()`: hitung total per section

Di `app/Http/Controllers/Agent/AgentOrderController.php` method `dashboard()`, tambahkan total count per section (yang belum ada) dan kirim ke view. `$activeOrdersCount` sudah ada (total pesanan aktif). Tambahkan:

```php
$totalResellers = $agent ? $agent->resellers()->count() : 0;

$totalMarketingAssets = \App\Models\Marketing\Asset::query()
    ->active()->where('usable_in_marketing', true)->count();

$totalCourses = \App\Models\Training\Course::query()->published()->count();
```
Lalu tambahkan ke array `view('agent.order.dashboard', [...])`:
```php
'totalActiveOrders'    => $activeOrdersCount,   // sudah dihitung; pastikan dikirim
'totalResellers'       => $totalResellers,
'totalMarketingAssets' => $totalMarketingAssets,
'totalCourses'         => $totalCourses,
```
(Sesuaikan nama scope/relasi bila di kode berbeda — samakan dengan query list yang sudah dipakai di `dashboard()` agar total-nya konsisten dengan yang ditampilkan.)

Verifikasi: `php -l app/Http/Controllers/Agent/AgentOrderController.php`.

## Langkah 2 — View: bungkus tiap tautan "Lihat semua" dengan `@if`

Di `resources/views/agent/order/dashboard.blade.php`, untuk MASING-MASING section, bungkus tautan "lihat semua"-nya:

- **Pesanan aktif** — tautan "Semua pesanan →":
```blade
@if ($totalActiveOrders > 4)
    <a href="{{ route('agent-order.orders') }}" class="small ...">Semua pesanan →</a>
@endif
```
- **Reseller Saya** — tautan "Semua reseller →":
```blade
@if ($totalResellers > 4)
    <a href="{{ route('agent-order.resellers') }}" class="small ...">Semua reseller →</a>
@endif
```
- **Materi Pemasaran** — tautan "Lihat semua →":
```blade
@if ($totalMarketingAssets > 4)
    <a href="{{ route('agent-order.materials') }}" class="small ...">Lihat semua →</a>
@endif
```
- **Pelatihan** — tautan "Lihat semua →":
```blade
@if ($totalCourses > 3)
    <a href="{{ route('agent-order.training') }}" class="small ...">Lihat semua →</a>
@endif
```
Pertahankan kelas/markup tautan yang sudah ada; hanya bungkus dengan `@if`. JANGAN ubah daftar item/kartu di dalam section, empty state, atau kartu nav.

Catatan: aturannya `> limit` (bukan `>=`). Contoh: Pelatihan dengan 1 data (≤ 3) → tautan HILANG; dengan 4 data (> 3) → tautan MUNCUL.

Verifikasi: `php artisan view:cache` sukses, lalu `php artisan view:clear`.

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentOrderController.php
php artisan view:cache && php artisan view:clear
```
Smoke manual (login customer-agent):
- Pelatihan dengan 1 course → **tidak ada** "Lihat semua". (Uji sebaliknya: bila course > 3, tautan muncul.)
- Reseller ≤ 4 → tanpa "Semua reseller"; > 4 → muncul.
- Materi Pemasaran ≤ 4 (sekarang 0) → tanpa "Lihat semua".
- Pesanan aktif ≤ 4 → tanpa "Semua pesanan"; > 4 → muncul.
- Section, kartu, empty state lain tak berubah.

## Checklist

- [ ] `dashboard()` menghitung & mengirim total per section (orders/reseller/materi/course).
- [ ] Tiap tautan "Lihat semua/Semua ..." dibungkus `@if (total > limit)` (4/4/4/3).
- [ ] Aturan `>` (bukan `>=`); markup/daftar item lain tak berubah.
- [ ] view:cache bersih.
