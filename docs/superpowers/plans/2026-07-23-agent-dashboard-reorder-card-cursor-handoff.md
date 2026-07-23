# Cursor Handoff — Kartu "Order lagi" Beranda Agen (logika + aksen)

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-23-agent-dashboard-reorder-card-cursor-handoff.md".
> Aturan permanen ada di `.cursorrules`. SCOPE kecil: `AgentOrderController::dashboard()` + kartu "Order lagi" di `resources/views/agent/order/dashboard.blade.php` (+ 1 aturan CSS aditif). Tak menyentuh bagian lain.

## Tujuan

1. **Logika:** kartu "Order lagi" hanya muncul bila ada order **completed** (bukan order terakhir apa pun status). Reorder dari order completed terbaru.
2. **Tampilan:** kartu diberi **aksen garis kiri warna primary brand** (kartu tetap putih). Pakai token brand (`--bs-primary`), BUKAN hex mentah dari mockup — agar patuh design system & ikut warna brand.

## Langkah 1 — Controller: order completed terbaru

Di `app/Http/Controllers/Agent/AgentOrderController.php` method `dashboard()`, ubah query `$lastOrder` agar hanya order **completed**:

```php
$lastOrder = SalesOrder::where('order_type', self::ORDER_TYPE)
    ->where('customer_id', $cid)
    ->where('status', 'completed')     // <-- TAMBAHKAN: hanya order selesai
    ->latest('created_at')
    ->first();
```
(Kirim `$lastOrder` ke view seperti sebelumnya — tak berubah.) Verifikasi: `php -l app/Http/Controllers/Agent/AgentOrderController.php`.

Catatan: bila nilai status "selesai" di data bukan `'completed'`, sesuaikan (cek `DB::table('transaction.sales_orders')->distinct()->pluck('status')`). Gunakan nilai yang sama dengan yang dipakai badge "COMPLETED" di riwayat.

## Langkah 2 — View: aksen border kiri primary

Di `resources/views/agent/order/dashboard.blade.php`, pada kartu "Order lagi" (blok `@if ($lastOrder)` → `<div class="card border-0 shadow-sm shop-order-card">`), tambahkan class aksen `agent-reorder-card`:
```blade
<div class="card border-0 shadow-sm shop-order-card agent-reorder-card">
```
(Sisanya biarkan: judul "Order lagi dari ...", tombol `btn-success`, dll.)

## Langkah 3 — CSS aditif (pakai token brand)

Di `public/assets/css/shop.css`, TAMBAHKAN blok baru (jangan ubah aturan lama):
```css
.agent-reorder-card {
    border-left: 4px solid var(--bs-primary) !important;
}
```
`var(--bs-primary)` mengikuti warna primary brand project (hijau `#5C9E84` sekarang), jadi otomatis konsisten bila brand berubah. Bila di project variabel primary bernama lain (mis. `--primary` / token tema), pakai variabel itu — JANGAN hardcode hex.

Verifikasi: `php artisan view:cache` sukses, lalu `php artisan view:clear`.

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentOrderController.php
php artisan view:cache && php artisan view:clear
```
Smoke manual (login customer-agent):
- Agen yang PUNYA order completed → kartu "Order lagi" muncul (dari order completed terbaru), dengan aksen garis kiri hijau brand.
- Agen TANPA order completed (mis. hanya pending) → kartu "Order lagi" TIDAK muncul.
- Klik "Order lagi" → keranjang terisi dari order tsb → ke checkout (perilaku reorder existing tetap).
- Warna aksen mengikuti `--bs-primary` (hijau brand), bukan hex tetap.
- Section/kartu lain di dashboard tak berubah.

## Checklist

- [ ] `$lastOrder` difilter `status = completed` (nilai status disamakan dgn badge COMPLETED).
- [ ] Kartu "Order lagi" dapat class `agent-reorder-card`.
- [ ] CSS `.agent-reorder-card` pakai `var(--bs-primary)` (token brand), bukan hex.
- [ ] Kartu hilang bila tak ada order completed; reorder tetap jalan.
- [ ] view:cache bersih; bagian lain tak berubah.
