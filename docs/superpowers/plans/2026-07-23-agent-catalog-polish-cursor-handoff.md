# Cursor Handoff — Poles Katalog Agen

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-23-agent-catalog-polish-cursor-handoff.md".
> Aturan permanen ada di `.cursorrules` — JANGAN tempel isi file ini ke `.cursorrules`.
> PRD lengkap: `docs/superpowers/specs/2026-07-23-agent-catalog-polish-design.md`.

## Tujuan

Poles halaman katalog agen (`agent.order.index`) sesuai mockup: hero carousel statis, chip filter kategori dinamis, section "Item Promo" (dari promosi aktif rekan), heading "Semua produk", dan footer di layout agen. Sebagian besar = kerangka UI yang terisi otomatis saat data ada (kategori/promo sekarang 0). Kerjakan berurutan, verifikasi tiap langkah.

## Langkah 0 — Verifikasi konteks (WAJIB)

```bash
php artisan tinker --execute="
echo 'Product::category: '.(method_exists(App\Models\Product::class,'category')?'Y':'N').PHP_EOL;
echo 'Promotion::activeNow: '.(method_exists(App\Models\Promotion::class,'scopeActiveNow')?'Y':'N').PHP_EOL;
echo 'Promotion::buyProduct: '.(method_exists(App\Models\Promotion::class,'buyProduct')?'Y':'N').PHP_EOL;
echo 'ShopContextService::company: '.(method_exists(App\Services\Shop\ShopContextService::class,'company')?'Y':'N').PHP_EOL;
"
```
Baca dulu: `app/Http/Controllers/Agent/AgentOrderController.php` (method `index()`), `resources/views/agent/order/index.blade.php`, `resources/views/layouts/agent-order.blade.php`. Konfirmasi bagaimana `$shopCompanyName` di-share ke layout (view-composer / middleware) — pakai mekanisme yang sama untuk menambah data perusahaan ke footer, JANGAN query DB langsung di Blade bila sudah ada composer.

## Langkah 1 — Controller `index()`: kategori, filter, promo

Di `AgentOrderController::index()`, TANPA mengubah logika produk yang sudah ada, tambahkan:

**(a) Filter opsional** sebelum `$products = $productsQuery->get()...`:
```php
$categoryId = $request->get('category_id');
$promoOnly = $request->boolean('promo');

if ($categoryId) {
    $productsQuery->where('category_id', $categoryId);   // invalid → hasil kosong/diabaikan, tidak error
}
```

**(b) Kategori untuk chip** (kategori yang punya produk jadi di cabang ini). Cara aman (via distinct category_id dari produk jadi):
```php
$categoryIds = Product::query()->saleItems()->whereNull('deleted_at')
    ->where('branch_id', $branchId)
    ->whereHas('nature', fn ($q) => $q->where('code', 'FINISHED_GOOD'))
    ->whereNotNull('category_id')
    ->distinct()->pluck('category_id');
$categories = \App\Models\ProductCategory::whereIn('id', $categoryIds)->orderBy('name')->get(['id', 'name']);
```

**(c) Produk promo** (id produk yang jadi `buy_product` promosi aktif):
```php
$promoProductIds = \App\Models\Promotion::activeNow()
    ->whereNotNull('buy_product_id')
    ->pluck('buy_product_id')->unique()->all();
```
Setelah `$products` (koleksi hasil map) terbentuk, turunkan daftar promo & tandai:
```php
$products = $products->map(function ($p) use ($promoProductIds) {
    $p['is_promo'] = in_array($p['id'], $promoProductIds, true);
    return $p;
});
$promoProducts = $products->where('is_promo', true)->values();
if ($promoOnly) {
    $products = $promoProducts;
}
```

**(d) Kirim ke view** — tambahkan ke array `view('agent.order.index', [...])`:
```php
'categories' => $categories,
'activeCategoryId' => $categoryId,
'promoProducts' => $promoProducts,
'promoOnly' => $promoOnly,
```
Verifikasi: `php -l app/Http/Controllers/Agent/AgentOrderController.php`.

## Langkah 2 — View `index.blade.php`: hero, chip, Item Promo, heading

Di `resources/views/agent/order/index.blade.php`, di dalam `@section('content')`, DI ATAS grid produk (`#productGrid`):

**(a) Hero carousel statis** (1–3 slide, Bootstrap 5). Contoh 1 slide (boleh tambah 2 lagi):
```blade
<div id="agentHero" class="carousel slide shop-hero mb-3" data-bs-ride="carousel">
    <div class="carousel-inner rounded-3 overflow-hidden">
        <div class="carousel-item active">
            <div class="shop-hero-slide" style="background-image:url('{{ asset('assets/img/wms/hero-1.jpg') }}')">
                <div class="shop-hero-overlay">
                    <span class="badge bg-light text-dark mb-2">CAMPAIGN</span>
                    <h2 class="h4 text-white fw-bold mb-1">Bundling Hemat Juli</h2>
                    <p class="text-white-50 mb-0 small">Order paket family & dapatkan harga spesial distributor.</p>
                </div>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#agentHero" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
    <button class="carousel-control-next" type="button" data-bs-target="#agentHero" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
</div>
```
Tambahkan CSS `.shop-hero-slide`/`.shop-hero-overlay` di `public/assets/css/shop.css` (tinggi ~200-320px, background-size cover, overlay gradient + padding). Bila gambar `hero-1.jpg` tak ada, boleh pakai warna solid/gradient sebagai fallback.

**(b) Chip filter kategori** (setelah header search, sebelum section produk):
```blade
<div class="shop-chips d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('agent-order.index', array_filter(['q' => $search])) }}"
       class="btn btn-sm {{ ! $activeCategoryId && ! ($promoOnly ?? false) ? 'btn-primary' : 'btn-outline-secondary' }}">Semua</a>
    @foreach ($categories as $cat)
        <a href="{{ route('agent-order.index', array_filter(['q' => $search, 'category_id' => $cat->id])) }}"
           class="btn btn-sm {{ $activeCategoryId === $cat->id ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $cat->name }}</a>
    @endforeach
</div>
```

**(c) Section "Item Promo"** (hanya bila ada; letakkan sebelum "Semua produk"):
```blade
@if (($promoProducts ?? collect())->isNotEmpty() && ! ($promoOnly ?? false))
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="h6 mb-0"><i class="ti ti-discount-2 me-1"></i>Item Promo</h2>
        <a href="{{ route('agent-order.index', array_filter(['q' => $search, 'promo' => 1])) }}" class="small">Lihat semua →</a>
    </div>
    <div class="row g-2 g-sm-3 mb-4">
        @foreach ($promoProducts as $product)
            @include('agent.order._product-card', ['product' => $product, 'showPromo' => true])
        @endforeach
    </div>
@endif
```

**(d) Heading "Semua produk"** tepat di atas grid existing `#productGrid`:
```blade
<h2 class="h6 mb-2"><i class="ti ti-package me-1"></i>{{ ($promoOnly ?? false) ? 'Produk Promo' : 'Semua produk' }}</h2>
```

**(e) Ekstrak kartu produk ke partial** `resources/views/agent/order/_product-card.blade.php` dari markup kartu yang SUDAH ADA di dalam `@forelse ($products ...)` (pindahkan blok `<div class="col-6 ..."><div class="card shop-product-card ...">...</div></div>` ke partial, terima `$product` dan opsional `$showPromo`). Di partial, bila `$showPromo ?? false` ATAU `$product['is_promo'] ?? false`, tampilkan badge:
```blade
<span class="badge bg-danger shop-promo-badge">PROMO</span>
```
Lalu grid utama `#productGrid` `@foreach`/`@forelse` memanggil `@include('agent.order._product-card', ['product' => $product])`. Pastikan atribut `data-product-id`/`data-product-name` dan perilaku klik (buka modal varian) TETAP SAMA agar `shop.js` existing tetap jalan.

Verifikasi: `php artisan view:cache` (sukses), lalu `php artisan view:clear`.

## Langkah 3 — Footer di layout

Di `resources/views/layouts/agent-order.blade.php`, sebelum penutup `</body>` (setelah konten `@yield('content')`), tambahkan footer 3 kolom. Data perusahaan: pakai mekanisme yang sama seperti `$shopCompanyName` (cek view-composer yang menyediakannya; bila hanya nama yang tersedia, tambahkan alamat/telp/email lewat composer yang sama — JANGAN query DB di Blade bila ada composer). Struktur:
```blade
<footer class="shop-footer border-top mt-5">
    <div class="container py-4">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="fw-bold mb-2">{{ $shopCompanyName ?? config('app.name') }}</div>
                <p class="text-muted small mb-0">Distributor resmi produk jadi untuk jaringan agen. Order online, kirim ke alamat agen Anda.</p>
            </div>
            <div class="col-md-4">
                <div class="fw-semibold small text-muted text-uppercase mb-2">Kontak & Alamat</div>
                <ul class="list-unstyled small mb-0">
                    <li><i class="ti ti-map-pin me-1"></i>{{ $shopCompanyAddress ?? '-' }}</li>
                    <li><i class="ti ti-phone me-1"></i>{{ $shopCompanyPhone ?? '-' }}</li>
                    <li><i class="ti ti-mail me-1"></i>{{ $shopCompanyEmail ?? '-' }}</li>
                </ul>
            </div>
            <div class="col-md-4">
                <div class="fw-semibold small text-muted text-uppercase mb-2">Lokasi</div>
                <a class="small" target="_blank" rel="noopener"
                   href="https://www.google.com/maps/search/?api=1&query={{ urlencode($shopCompanyAddress ?? '') }}">
                    <i class="ti ti-external-link me-1"></i>Buka di Google Maps
                </a>
            </div>
        </div>
        <div class="text-center text-muted small mt-3">© {{ date('Y') }} {{ $shopCompanyName ?? config('app.name') }}</div>
    </div>
</footer>
```
Jika variabel `$shopCompanyAddress/Phone/Email` belum ada, tambahkan di view-composer/tempat `$shopCompanyName` disediakan (dari `ShopContextService::company()`), bukan hardcode. Bila composer tak ditemukan, boleh set di layout via `@php($company = auth('customer')->user()?->...)` dengan null-safe — tapi UTAMAKAN composer yang sudah ada.

Verifikasi: `php artisan view:cache` sukses, lalu `view:clear`.

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentOrderController.php
php artisan route:list --name=agent-order
php artisan view:cache && php artisan view:clear
```
Smoke manual (login customer-agent):
- `/agen-order` → hero tampil, chip minimal "Semua", section "Semua produk" (Item Promo tersembunyi karena 0 promo aktif), footer tampil dengan data perusahaan.
- Uji chip kategori: bila belum ada kategori, hanya "Semua"; buat 1 kategori + assign ke produk jadi → chip muncul & filter jalan (`?category_id=`).
- Uji Item Promo: buat 1 `product.promotions` aktif (`is_active=true`, `starts_at`/`ends_at` mengapit sekarang, `buy_product_id` = produk jadi) → section "Item Promo" muncul dengan badge PROMO; nonaktifkan → section hilang.
- Footer muncul juga di `/agen-order/checkout` & `/agen-order/beranda`.
- Regresi: add-to-cart / modal varian / checkout / `/shop` customer tetap normal.

## Checklist

- [ ] Langkah 0 verifikasi relasi/scope/helper; nama disesuaikan bila beda.
- [ ] `index()`: filter `category_id` + `promo` opsional, kirim `categories`/`activeCategoryId`/`promoProducts`/`promoOnly`.
- [ ] Kartu produk diekstrak ke `_product-card.blade.php`; grid & Item Promo memakainya; perilaku klik/`shop.js` tetap.
- [ ] Hero carousel statis (1–3 slide) + CSS.
- [ ] Chip kategori dinamis (selalu ada "Semua"); mempertahankan `q`.
- [ ] Section Item Promo muncul saat ada promo aktif, hilang saat 0; badge PROMO.
- [ ] Footer 3 kolom di layout (data perusahaan via composer/null-safe), tampil di semua halaman agen.
- [ ] view:cache bersih; keranjang/checkout/`/shop` tak berubah.
