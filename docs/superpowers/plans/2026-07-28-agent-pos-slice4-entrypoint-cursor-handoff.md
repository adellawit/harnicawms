# Cursor Handoff — POS Agen Slice 4: Entry Point

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-28-agent-pos-slice4-entrypoint-cursor-handoff.md".
> Spec: `docs/superpowers/specs/2026-07-28-agent-pos-design.md`. Prasyarat: Slice 1–2 (POS agen jalan).
> SCOPE kecil: tambah tautan menuju POS agen. Tak menyentuh alur POS.

## Tujuan

Agen bisa membuka POS dari portal. Tambah entry point ke `route('agent-order.pos')`:
1. **Kartu nav di dashboard** (`resources/views/agent/order/dashboard.blade.php`) — sejajar dengan kartu nav "Materi Pemasaran"/"Pelatihan"/"Order ke Distributor" yang sudah ada. Judul "POS / Kasir", subjudul "Jual ke reseller Anda", ikon `ti ti-cash-register` (atau `ti-device-desktop`). Tiru gaya `agent-dashboard-nav-card` yang sudah dipakai.
2. **(Opsional) Ikon di header** `resources/views/layouts/agent-order.blade.php` — tombol lingkaran seperti tombol Beranda/Order yang ada (lihat `shop-nav-circle`), `title="POS"`, ikon `ti ti-cash-register`, href `route('agent-order.pos')`. Tambah bila muat; bila mengganggu layout header, cukup kartu dashboard.

Karena POS memakai layout fullscreen sendiri (`layouts.agent-pos`), tautannya berupa link biasa (buka halaman POS penuh).

## Langkah

Di `dashboard.blade.php`, tambahkan satu kartu nav:
```blade
<a href="{{ route('agent-order.pos') }}" class="card border-0 shadow-sm agent-dashboard-nav-card text-decoration-none h-100">
    <div class="card-body d-flex align-items-center gap-3">
        <span class="agent-nav-icon bg-label-primary"><i class="ti ti-cash-register"></i></span>
        <div>
            <div class="fw-semibold">POS / Kasir</div>
            <div class="text-muted small">Jual produk ke reseller Anda</div>
        </div>
    </div>
</a>
```
> Samakan kelas/markup dengan kartu nav lain yang sudah ada di dashboard (jangan mengarang kelas baru bila sudah ada pola-nya — grep `agent-dashboard-nav-card`/`agent-nav-icon`). Pakai token brand.

Verifikasi: `php artisan view:cache && php artisan view:clear`.

## Verifikasi akhir

```bash
php artisan view:cache && php artisan view:clear
```
Smoke manual: dashboard agen menampilkan kartu "POS / Kasir" → klik → halaman POS (`/agen-order/pos`) terbuka fullscreen. Section lain tak berubah.

## Checklist

- [ ] Kartu nav "POS / Kasir" di dashboard → `route('agent-order.pos')` (gaya sama kartu nav lain).
- [ ] (Opsional) ikon POS di header portal agen.
- [ ] view:cache bersih; layout & section lain tak berubah.
