# Cursor Handoff — Perbaikan Keranjang Drawer Agen (slice 4b)

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-23-agent-cart-drawer-fix-cursor-handoff.md".
> Aturan permanen ada di `.cursorrules`.
> SCOPE: perbaikan perilaku UI keranjang saja. TANPA centang/pilih per-item. TANPA mengubah logika `ShopCartService`/checkout. NOL perubahan ke `/shop` customer.

## Masalah yang diperbaiki

Saat ini ikon keranjang di header agen (`resources/views/layouts/agent-order.blade.php`, `#navCartBtn`) adalah `<a href="{{ route('agent-order.checkout') }}">` — klik langsung LOMPAT ke halaman Checkout. Drawer keranjang (`offcanvas offcanvas-end #cartOffcanvas`) sebenarnya SUDAH ADA tapi ditulis di `resources/views/agent/order/index.blade.php` (katalog saja) dan hanya dipicu tombol bar mobile (`d-lg-none`). Akibatnya di desktop tak ada drawer — tak sesuai mockup.

**Perilaku yang benar (mockup):** klik ikon keranjang → **buka drawer geser dari kanan** (isi keranjang + qty stepper + hapus). Menuju checkout dipicu tombol **"Lanjut Checkout"** DI DALAM drawer (bawa SEMUA item). Berlaku di semua halaman agen.

## Pendekatan

Pindahkan drawer ke LAYOUT agar tersedia di semua halaman agen; suplai data keranjang ke layout lewat view-composer yang sudah ada; ubah ikon keranjang jadi pemicu drawer. Tak menyentuh `ShopCartService`.

## Langkah 1 — Suplai data keranjang ke layout (view-composer)

Di `app/Providers/AppServiceProvider.php` sudah ada `View::composer(['layouts.customer', 'layouts.agent-order', 'customer.auth.login'], ...)` yang men-share `shopCompanyName` dll. TAMBAHKAN composer BARU yang KHUSUS `layouts.agent-order` (jangan gabung dengan `/shop` `layouts.customer` agar toko customer tak terpengaruh) untuk men-share `cart` & `cartSummary`:

```php
View::composer('layouts.agent-order', function ($view) {
    if (! auth('customer')->check()) {
        $view->with(['navCart' => ['items' => []], 'navCartSummary' => ['item_count' => 0, 'subtotal' => 0, 'tax_amount' => 0, 'tax_rate' => 0, 'tax_enabled' => false, 'total' => 0]]);
        return;
    }
    try {
        $cartService = app(\App\Services\Shop\ShopCartService::class);
        $view->with(['navCart' => $cartService->get(), 'navCartSummary' => $cartService->summarize()]);
    } catch (\Throwable $e) {
        $view->with(['navCart' => ['items' => []], 'navCartSummary' => ['item_count' => 0, 'subtotal' => 0, 'tax_amount' => 0, 'tax_rate' => 0, 'tax_enabled' => false, 'total' => 0]]);
    }
});
```
(Null-safe: bila belum login customer / context belum siap, kirim keranjang kosong agar layout tak error.) Verifikasi `php -l app/Providers/AppServiceProvider.php`.

## Langkah 2 — Pindahkan drawer + trigger ke layout

Di `resources/views/layouts/agent-order.blade.php`:

**(a)** Ubah `#navCartBtn` dari `<a href=...checkout>` menjadi tombol pemicu offcanvas (pertahankan ikon + badge count yang sudah ada):
```blade
<button type="button" class="shop-nav-circle shop-nav-cart" id="navCartBtn" title="Keranjang" aria-label="Keranjang"
        data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
    <i class="ti ti-shopping-cart"></i>
    @php $navCartCount = count(session(\App\Services\Shop\ShopCartService::SESSION_KEY)['items'] ?? []); @endphp
    @if ($navCartCount > 0)
        <span class="badge bg-danger rounded-pill cart-badge" id="navCartBadge">{{ $navCartCount }}</span>
    @endif
</button>
```

**(b)** Tambahkan markup offcanvas drawer di layout (mis. tepat sebelum `@stack('scripts')` / sebelum `</body>`), memakai `$navCart`/`$navCartSummary` dari composer:
```blade
<div class="offcanvas offcanvas-end shop-cart-offcanvas" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="cartOffcanvasLabel">Keranjang</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <div class="flex-grow-1 overflow-auto p-3" id="cartItemsList">
            @include('agent.order._cart-items', ['cart' => $navCart, 'summary' => $navCartSummary])
        </div>
        <div class="border-top p-3 bg-light" id="cartFooter">
            @include('agent.order._cart-footer', ['summary' => $navCartSummary])
        </div>
    </div>
</div>
```
Pastikan partial `_cart-items` menerima `$cart`/`$summary` dan `_cart-footer` menerima `$summary` (sudah demikian). Tombol di `_cart-footer` sudah "Lanjut Checkout" → `route('agent-order.checkout')` (dari slice 4). Bila belum, ubah jadi "Lanjut Checkout" ke checkout.

**(c)** Muat `shop.js` di layout agar interaksi drawer (ubah qty / hapus) jalan di SEMUA halaman:
```blade
<script>
window.shopRoutes = {
    cartUpdate: @json(route('agent-order.cart.update')),
    cartRemove: @json(route('agent-order.cart.remove')),
    shop: @json(route('agent-order.index')),
};
</script>
<script src="{{ asset('assets/js/shop.js') }}"></script>
```
(Letakkan setelah jQuery/bootstrap yang sudah dimuat layout; sesuaikan dengan cara `window.shopRoutes`/`shopCheckoutUrl` yang sudah dipakai di index — JANGAN duplikasi bila layout sudah menyediakannya.)

## Langkah 3 — Bersihkan duplikasi di katalog

Di `resources/views/agent/order/index.blade.php`:
- HAPUS blok `<div class="offcanvas ... #cartOffcanvas">...</div>` (sekarang ada di layout).
- Bila `shop.js` + `window.shopRoutes` kini dimuat di layout, HAPUS `@push('scripts')` yang menduplikasi pemuatan `shop.js`/route cart di index (hindari double-load & double-binding). Bila index masih butuh JS khusus katalog (modal varian/add-to-cart), sisakan hanya bagian itu.
- Tombol bar keranjang mobile (`.shop-cart-bar d-lg-none`) BOLEH tetap sebagai pemicu tambahan (`data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas"`) — targetnya sekarang drawer di layout. Pastikan tak ada dua elemen ber-id `#cartOffcanvas`.

Verifikasi: `php artisan view:cache` (sukses), lalu `php artisan view:clear`.

## Verifikasi akhir

```bash
php -l app/Providers/AppServiceProvider.php
php artisan view:cache && php artisan view:clear
```
Smoke manual (login customer-agent, ada isi keranjang):
- Di katalog: klik ikon keranjang di header → **drawer geser dari kanan** muncul dengan item + qty stepper + hapus. TIDAK lompat ke checkout.
- Ubah qty / hapus item di drawer → total di drawer ter-update (shop.js).
- Tombol "Lanjut Checkout" di drawer → ke halaman checkout (bawa semua item).
- Ikon keranjang juga membuka drawer di halaman Beranda & Riwayat (drawer kini global).
- Badge jumlah item di ikon tetap benar.
- **Regresi `/shop` customer**: buka `/shop`, pastikan keranjang & checkout customer TETAP seperti semula (composer baru hanya untuk `layouts.agent-order`, tak menyentuh `layouts.customer`).
- Hanya ada SATU `#cartOffcanvas` di DOM.

## Checklist

- [ ] Composer BARU khusus `layouts.agent-order` men-share `navCart`/`navCartSummary` (null-safe); TIDAK menyentuh `layouts.customer`.
- [ ] Ikon keranjang jadi tombol pemicu offcanvas (bukan link ke checkout).
- [ ] Drawer offcanvas dipindah ke layout; render item via `_cart-items`/`_cart-footer`.
- [ ] `shop.js` + route cart tersedia di layout; duplikasi di index dihapus (tak double-load).
- [ ] Offcanvas & mobile-bar tak menduplikasi id; "Lanjut Checkout" → checkout.
- [ ] view:cache bersih; `/shop` customer TIDAK berubah; tak ada perubahan `ShopCartService`/logika checkout.
