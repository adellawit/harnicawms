# Poles Katalog Agen — Design Spec (PRD)

**Tanggal:** 2026-07-23
**Branch:** `feature/agent-catalog-polish` (lanjutan portal agen; menumpuk di atas dashboard + shipping + landing)
**Status:** Approved (brainstorming) — siap diimplementasikan via Cursor.
**Bagian dari:** slicing UI portal agen sesuai mockup PM. Slice #2 (Katalog).

## Ringkasan

Menyempurnakan halaman katalog agen (`/agen-order`, `agent.order.index`) sesuai mockup: tambah **hero carousel** (statis), **chip filter kategori** (dinamis), section **"Item Promo"** (produk dari promosi aktif), heading **"Semua produk"** (grid existing), dan **footer** kaya di layout. Sebagian besar section adalah kerangka UI yang otomatis terisi saat data ada (kategori/promo/marketing sekarang masih 0).

## Konteks & kondisi data

- Katalog agen sudah ada: `App\Http\Controllers\Agent\AgentOrderController::index()` + `resources/views/agent/order/index.blade.php`. Produk difilter `saleItems()` + `branch_id` + `nature.code = FINISHED_GOOD`, dipetakan (id/name/image/min_price/variants_count), skip yang tak berharga.
- **Kategori** `product.product_categories`: **0 baris** sekarang. Produk punya `category_id` (relasi `Product::category()` → `App\Models\ProductCategory`).
- **Promosi** `product.promotions` (modul rekan, buy-X-get-Y): **0 aktif** sekarang. Model `App\Models\Promotion` punya scope `activeNow()` dan relasi `buyProduct()` (`buy_product_id`). Bukan "harga coret" — tak ada data harga promo.
- **Banner/Campaign**: tidak ada tabel. Hero dibuat **statis**.
- **Perusahaan** (untuk footer): `ShopContextService::company()` → `master_data.business_units` (name/address/city/phone/email tersedia).
- Layout agen `resources/views/layouts/agent-order.blade.php` saat ini **belum punya footer**.

## Section yang ditambahkan (semua di `agent.order.index`, di atas grid produk)

### 1. Hero carousel (statis)
Bootstrap carousel dengan **1–3 slide hardcode** (gambar + judul + subjudul) di Blade. Tanpa DB. Contoh slide: "Bundling Hemat Juli — Order paket family & dapatkan harga spesial distributor." Gambar boleh dari `asset(...)` lokal atau placeholder. Ganti nanti bila ada modul banner.

### 2. Chip filter kategori (dinamis)
- Controller `index()`: ambil kategori yang PUNYA produk jadi di cabang ini:
  `ProductCategory` yang `whereHas('products', fn($q) => $q->saleItems()->where('branch_id',$branchId)->whereHas('nature', fn($n)=>$n->where('code','FINISHED_GOOD')))`. (Sesuaikan nama relasi `Product::category()`/inverse; bila inverse `products()` belum ada di `ProductCategory`, query via `Product` distinct `category_id` lalu load kategori.)
- Selalu ada chip **"Semua"** (aktif bila tak ada filter). Kondisi sekarang (0 kategori) → hanya "Semua".
- Klik chip → `GET /agen-order?category_id=<id>` (atau tanpa param untuk Semua). `index()` menambah filter opsional: bila `category_id` valid, `->where('category_id', $categoryId)` pada query produk. `category_id` tak valid → diabaikan (tampilkan semua), jangan error.
- Chip mempertahankan `q` (search) yang aktif.

### 3. Section "Item Promo"
- Controller `index()`: `$promoProductIds = Promotion::activeNow()->whereNotNull('buy_product_id')->pluck('buy_product_id')->unique();` lalu dari koleksi `$products` yang sudah dipetakan, tandai/ambil yang id-nya ada di `$promoProductIds` → `$promoProducts`.
- View: bila `$promoProducts` tidak kosong, render section "Item Promo" (kartu produk sama seperti grid biasa + **badge "PROMO"**). Klik = perilaku produk normal (buka modal varian / add to cart) — **tanpa harga coret**.
- **Bila kosong (0 promo aktif sekarang) → section TIDAK dirender** (bukan blok kosong).
- "Lihat semua →" opsional: bila dibuat, arahkan ke `GET /agen-order?promo=1` yang memfilter grid hanya produk promo (filter opsional di `index()`).

### 4. Section "Semua produk"
Grid produk yang sudah ada, cukup diberi heading "Semua produk". Bila `category_id`/`q`/`promo` aktif, grid mengikuti filter tsb.

## Footer (di layout `agent-order.blade.php`)

Tambahkan footer di layout (tampil di semua halaman agen: katalog, checkout, dashboard). 3 kolom seperti mockup:
- **Branding**: nama distributor (`ShopContextService::company()?->name` atau `companyName()`) + tagline statis.
- **Kontak & Alamat**: address/city/phone/email dari `company()` (fallback teks statis "-" bila null). Tambah WA & jam operasional sebagai teks statis.
- **Lokasi**: tautan "Buka di Google Maps" berbasis alamat perusahaan (`https://www.google.com/maps/search/?api=1&query=<urlencode(alamat)>`); embed iframe opsional — bila menyulitkan, cukup gambar/ikon peta + tautan.
- Baris bawah: `© {{ date('Y') }} {{ nama distributor }}`.

Data perusahaan dikirim ke layout via `View::share`/composer yang sudah ada, atau langsung `auth('customer')`/context di layout — ikuti cara variabel `$shopCompanyName` yang sudah dipakai di layout (`{{ $shopCompanyName ?? config('app.name') }}`). Bila perlu variabel tambahan (alamat/telp), tambahkan lewat mekanisme yang sama (jangan query DB langsung di Blade bila ada view-composer).

## Edge cases

- 0 kategori → hanya chip "Semua". 0 promo aktif → section Item Promo hilang. Hero statis selalu tampil.
- `category_id`/`promo` param invalid → diabaikan, tampilkan semua.
- Footer null-safe pada data perusahaan (fallback statis).
- Search `q` + filter kategori/promo bisa aktif bersamaan (AND).

## Regresi

- Tidak menyentuh logika keranjang/varian/checkout — hanya menambah section tampilan + filter opsional (`category_id`, `promo`) di `index()`.
- Footer masuk ke layout `agent-order.blade.php` (khusus agen) — **tidak** menyentuh layout `/shop` customer. Toko customer tak terpengaruh.

## Testing / Verifikasi

Tanpa automated test — via `php -l`, `php artisan route:list --name=agent-order`, `php artisan view:cache`, tinker smoke:
- Katalog render: hero + chip (min. "Semua") + "Semua produk".
- `?category_id=` memfilter grid; invalid diabaikan.
- Section Item Promo muncul saat ada promo aktif (buat 1 `product.promotions` activeNow dengan `buy_product_id` produk jadi → cek section muncul + badge PROMO); hilang saat 0.
- Footer tampil di katalog/checkout/dashboard dengan data perusahaan.
- Keranjang/checkout/`/shop` tak berubah.
