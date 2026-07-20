# Cursor Handoff — Landing Page Order Agent → Distributor

> Konten siap **copy-paste ke `.cursorrules`** (atau langsung ke prompt Cursor Composer) untuk mengimplementasikan `docs/superpowers/specs/2026-07-18-agent-order-landing-design.md`. Salin blok antara `--- MULAI ---` dan `--- SELESAI ---`.

## Catatan sebelum ditempel

- Jika `.cursorrules` sudah berisi aturan lain, tempel sebagai TAMBAHAN di bawah, jangan timpa.
- Bagian "KONTEKS PROJECT" bisa dijadikan aturan permanen; bagian "TASK" hapus setelah fitur selesai.

---

--- MULAI ---

# KONTEKS PROJECT (permanen)

- Laravel 12, PostgreSQL, tabel schema-qualified (`product.*`, `transaction.*`, `partner.*`). PK UUID v7.
- TIDAK ADA automated test suite (kebijakan disengaja). JANGAN tulis PHPUnit/Pest. Verifikasi: `php -l`, `php artisan route:list`, `php artisan view:cache`, `php artisan tinker`.
- 2 guard auth: `web` (model `User`, admin) dan `customer` (model `Customer`, toko `/shop`). Alias middleware didaftarkan di `bootstrap/app.php` via `$middleware->alias([...])`.
- Ikuti pola file yang sudah ada; jangan bikin struktur baru kalau ada yang bisa ditiru.

# TASK — Landing Page Order Agent → Distributor

Baca spec lengkap: `docs/superpowers/specs/2026-07-18-agent-order-landing-design.md`. Tujuan: landing page terpisah `/agen-order` untuk customer-yang-agent memesan produk JADI dari distributor; order tersimpan sebagai `transaction.sales_orders` dengan `order_type = 'web-order'`. Reuse mesin keranjang+checkout `/shop`. Kerjakan 5 langkah berurutan, verifikasi tiap langkah.

## Langkah 0 — Verifikasi asumsi data (WAJIB sebelum coding)

Jalankan tinker untuk konfirmasi (jangan lanjut kalau salah):
```bash
php artisan tinker --execute="
echo 'isPartnerAgent ada: '.(method_exists(App\Models\Customer::class,'isPartnerAgent')?'Y':'N').PHP_EOL;
echo 'ProductNature FINISHED_GOOD: '.(App\Models\ProductNature::where('code','FINISHED_GOOD')->exists()?'Y':'N').PHP_EOL;
echo 'Product::nature FK: nature_id (relasi nature)'.PHP_EOL;
"
```
Konfirmasi juga signature `App\Services\PosCheckoutService::createSalesOrder(...)` — parameter ke-9 harus `string $orderType = 'pos'` (atau serupa). Filter produk jadi memakai relasi `Product::nature()` (kolom `nature_id`) → `ProductNature.code = 'FINISHED_GOOD'`. JANGAN pakai `product_nature_id` (itu relasi ParameterDetail yang berbeda).

## Langkah 1 — Middleware `EnsureCustomerIsAgent`

Buat `app/Http/Middleware/EnsureCustomerIsAgent.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return redirect()->route('customer.login');
        }

        if (! $customer->isPartnerAgent()) {
            abort(403, 'Halaman ini khusus untuk agen.');
        }

        return $next($request);
    }
}
```

Daftarkan alias di `bootstrap/app.php`, DI DALAM `$middleware->alias([...])` yang sudah ada (tambahkan satu baris, jangan hapus alias lain):
```php
'agent' => \App\Http\Middleware\EnsureCustomerIsAgent::class,
```

Verifikasi: `php -l app/Http/Middleware/EnsureCustomerIsAgent.php`.

## Langkah 2 — Tambah parameter `orderType` di ShopCheckoutService

Di `app/Services/Shop/ShopCheckoutService.php`:

- Ubah signature `processXendit(Request $checkoutRequest, string $paymentMethodId, ?string $xenditChannel = null)` menjadi:
  `processXendit(Request $checkoutRequest, string $paymentMethodId, ?string $xenditChannel = null, string $orderType = 'web')`
- Ubah signature `processCod(Request $checkoutRequest, string $paymentMethodId)` menjadi:
  `processCod(Request $checkoutRequest, string $paymentMethodId, string $orderType = 'web')`
- Di KEDUA method, pada pemanggilan `$this->checkout->createSalesOrder(...)`, argumen ke-9 yang saat ini literal `'web'` GANTI menjadi variabel `$orderType`. Contoh (processXendit, cari blok ini):
  ```php
  $order = $this->checkout->createSalesOrder(
      $checkoutRequest,
      $totals,
      $salesNumber,
      $branchId,
      $companyId,
      null,
      'pending',
      'unpaid',
      'web',          // <-- GANTI menjadi: $orderType,
  );
  ```
  Lakukan hal sama di `processCod`.

Default `'web'` menjaga perilaku `/shop` yang sudah ada TIDAK berubah. Verifikasi: `php -l app/Services/Shop/ShopCheckoutService.php`, lalu pastikan `/shop` masih jalan normal (order_type tetap 'web' saat dipanggil tanpa argumen baru).

## Langkah 3 — Route file `routes/agent.php`

Buat `routes/agent.php`:

```php
<?php

use App\Http\Controllers\Agent\AgentOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent order landing — order agent → distributor (guard customer + agent gate)
|--------------------------------------------------------------------------
*/

Route::prefix('agen-order')->name('agent-order.')->middleware(['auth:customer', 'agent'])->group(function () {
    Route::get('/', [AgentOrderController::class, 'index'])->name('index');
    Route::get('/products/variants', [AgentOrderController::class, 'productVariants'])->name('products.variants');
    Route::post('/cart/add', [AgentOrderController::class, 'cartAdd'])->name('cart.add');
    Route::post('/cart/update', [AgentOrderController::class, 'cartUpdate'])->name('cart.update');
    Route::post('/cart/remove', [AgentOrderController::class, 'cartRemove'])->name('cart.remove');
    Route::get('/checkout', [AgentOrderController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [AgentOrderController::class, 'checkoutProcess'])->name('checkout.process');
    Route::get('/payment/return', [AgentOrderController::class, 'paymentReturn'])->name('payment.return');
    Route::get('/payment/{orderId}/status', [AgentOrderController::class, 'paymentStatus'])->name('payment.status');
    Route::get('/orders', [AgentOrderController::class, 'orders'])->name('orders');
    Route::get('/orders/{order}', [AgentOrderController::class, 'orderShow'])->name('orders.show');
});
```

Di `routes/web.php`, cari baris yang me-`require` route file lain (mis. `require __DIR__.'/customer.php';` atau `require __DIR__.'/marketing.php';`) dan tambahkan setelahnya:
```php
require __DIR__.'/agent.php';
```
(Kalau `routes/customer.php` di-load lewat mekanisme lain di `bootstrap/app.php` `withRouting(...)`, ikuti pola yang sama untuk `agent.php`. Cek `bootstrap/app.php` bagian `->withRouting(...)` lebih dulu.)

Verifikasi: `php artisan route:list --name=agent-order` — 11 route muncul dengan middleware `auth:customer` + `agent`.

## Langkah 4 — `AgentOrderController` (mirror ShopController)

Buat `app/Http/Controllers/Agent/AgentOrderController.php` dengan MENYALIN struktur `app/Http/Controllers/Customer/ShopController.php` (semua method: `index`, `productVariants`, `cartAdd`, `cartUpdate`, `cartRemove`, `checkout`, `checkoutProcess`, `paymentReturn`, `paymentStatus`, `orders`, `orderShow`, plus helper `context()`, `cart()`, `checkoutService()`, `minVariantPrice()`), lalu terapkan PERBEDAAN berikut saja:

1. **Namespace & class:** `namespace App\Http\Controllers\Agent;` class `AgentOrderController`.

2. **`index()` — filter produk jadi.** Pada query produk (`$productsQuery = Product::with('nature')->withCount('variants')->saleItems()->whereNull('deleted_at')->where('branch_id', $branchId)...`), TAMBAHKAN filter produk jadi:
   ```php
   $productsQuery->whereHas('nature', fn ($q) => $q->where('code', 'FINISHED_GOOD'));
   ```
   (Boleh hilangkan facet filter `nature_id`/`productTypes` dari ShopController jika tak diperlukan untuk agent — landing agent hanya produk jadi. Jika dipertahankan, pastikan tetap dalam lingkup FINISHED_GOOD.) View diarahkan ke `agent.order.index` (lihat Langkah 5), kirim variabel yang sama seperti ShopController::index (`customer`, `branch`, `products`, `cart`, `summary`, dst).

3. **`checkoutProcess()` — order_type 'web-order'.** Method ini di ShopController memanggil `$this->checkoutService()->processXendit(...)` atau `->processCod(...)`. Tambahkan argumen `orderType` = `'web-order'`:
   - Panggilan Xendit: `->processXendit($request, $paymentMethodId, $xenditChannel, 'web-order')`
   - Panggilan COD: `->processCod($request, $paymentMethodId, 'web-order')`
   (Cek urutan argumen persis di ShopController::checkoutProcess dan sesuaikan; `orderType` adalah argumen TERAKHIR yang baru ditambahkan di Langkah 2.)

4. **`orders()` & `orderShow()` — filter web-order.** Di ShopController keduanya memfilter `->where('order_type', 'web')`. GANTI semua `'web'` menjadi `'web-order'` di kedua method ini (dan di `cancelOrder` bila method itu ikut disalin — pastikan guard `order_type !== 'web-order'`). Order agent hanya yang `order_type = 'web-order'` milik customer-agent yang login.

5. **Semua `return view('customer.shop.<x>', ...)` diganti** menjadi `return view('agent.order.<x>', ...)` dengan data yang sama.

Verifikasi: `php -l app/Http/Controllers/Agent/AgentOrderController.php`.

## Langkah 5 — View `resources/views/agent/order/*`

Salin view dari `resources/views/customer/shop/*` ke `resources/views/agent/order/*` (mis. `index.blade.php`, `checkout.blade.php`, `orders.blade.php`, `order-show.blade.php`, plus partial yang dipakai). Sesuaikan:
- Semua `route('customer.<x>')` → `route('agent-order.<x>')` (dan nama route checkout/cart/payment yang relevan).
- Heading/branding jadi "Order ke Distributor" (atau sesuai preferensi) untuk membedakan dari toko customer.
- Endpoint fetch JS (cart add/update/remove, variants) mengarah ke route `agent-order.*`.

Verifikasi: `php artisan view:cache` (harus sukses tanpa error compile), lalu `php artisan view:clear`.

## Verifikasi akhir (tinker + manual)

```bash
php artisan route:list --name=agent-order   # 11 route, middleware auth:customer + agent
php artisan view:cache                        # compile bersih
```
Smoke manual (login sebagai customer-agent vs customer biasa):
- Customer NON-agent buka `/agen-order` → 403 (ditolak middleware `agent`).
- Customer-agent buka `/agen-order` → tampil katalog, HANYA produk FINISHED_GOOD.
- Tambah ke keranjang → checkout (Xendit/COD) → cek DB: `sales_orders` terbaru punya `order_type = 'web-order'` dan `customer_id` = id customer-agent.
- `/agen-order/orders` hanya menampilkan order `web-order` milik agent tsb.
- `/shop` biasa TIDAK berubah (order_type masih 'web').

## Checklist

- [ ] Langkah 0 verifikasi data lolos (isPartnerAgent, FINISHED_GOOD, createSalesOrder orderType param).
- [ ] Middleware `EnsureCustomerIsAgent` + alias `agent` di bootstrap/app.php.
- [ ] `ShopCheckoutService::processXendit/processCod` punya param `$orderType='web'`, diteruskan ke createSalesOrder; `/shop` tak berubah.
- [ ] `routes/agent.php` + di-`require`; 11 route `agent-order.*` terdaftar dgn middleware benar.
- [ ] `AgentOrderController` mirror ShopController + 5 perbedaan (filter FINISHED_GOOD, order_type web-order, view agent.order.*, filter riwayat web-order).
- [ ] View `agent/order/*` (route names & branding disesuaikan), view:cache bersih.
- [ ] Non-agent ditolak; agent bisa order; order tersimpan `web-order`; `/shop` tetap normal.

--- SELESAI ---
