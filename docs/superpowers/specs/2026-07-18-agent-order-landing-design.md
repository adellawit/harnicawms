# Landing Page Order Agent → Distributor — Design Spec (PRD)

**Tanggal:** 2026-07-18
**Branch:** `feature/agent-order-landing`
**Status:** Approved (brainstorming) — siap diimplementasikan.
**Catatan:** Implementasi dikerjakan via AI Coding Assistant (Cursor), bukan lewat subagent-driven-development. Dokumen ini PRD; lihat dokumen pendamping "cursor-handoff" untuk instruksi teknis siap-tempel.

## Ringkasan

Landing page **terpisah** untuk **agent** memesan produk **jadi** dari distributor. Ordernya masuk sebagai data transaksi (`transaction.sales_orders`) dengan `order_type = 'web-order'`, terpisah dari order customer biasa (`'web'`) dan POS (`'pos'`). Halaman ini hanya boleh diakses oleh customer yang berstatus **agent** — inilah pembeda middleware-nya dari `/shop` biasa.

## Konteks Sistem (temuan eksplorasi)

- Ada 2 guard auth: **`web`** (staff/admin, model `User`) dan **`customer`** (belanja `/shop`, model `Customer`).
- **Agent = customer khusus:** model `Customer` punya relasi `agent()` (HasOne ke `partner.agents`) dan method **`Customer::isPartnerAgent(): bool`** yang sudah ada. Agent login memakai guard `customer` yang sama (tak ada sistem login baru).
- Flow `/shop` yang ada (guard `customer`) memakai: `ShopContextService` (resolusi cabang + price list + pajak), `ShopCartService` (keranjang berbasis session), `ShopCheckoutService` (Xendit + COD) yang memanggil `PosCheckoutService::createSalesOrder(...)`. Ordernya `order_type = 'web'`.
- `PosCheckoutService::createSalesOrder(Request, totals, salesNumber, branchId, companyId, userId, status, paymentStatus, orderType)` — parameter ke-9 `orderType` sudah ada; `/shop` mengoper `'web'`. Jadi order agent cukup mengoper `'web-order'`.
- "Produk jadi" ada di data sebagai `ProductNature` kode **`FINISHED_GOOD`** (lawan `RAW_MATERIAL`), tersambung ke `products.nature_id` via relasi `Product::nature()`. **Catatan:** ada dua kolom mirip — `nature_id` (relasi `nature` → `ProductNature`, punya kode FINISHED_GOOD) dan `product_nature_id` (relasi lain → `ParameterDetail`). Filter produk jadi memakai relasi **`nature`** (`nature_id`) dengan `ProductNature.code = 'FINISHED_GOOD'`. Implementer WAJIB verifikasi kolom/relasi ini di data nyata sebelum lanjut.

## Keputusan Desain (hasil interview)

| Aspek | Keputusan |
|---|---|
| Identitas agent | Customer yang `isPartnerAgent()` = true. Guard `customer` + middleware gate baru. |
| Hubungan dgn `/shop` | Halaman/route/view **terpisah**, tapi **reuse** service keranjang+checkout `/shop`. |
| Katalog | Hanya produk **jadi** (`ProductNature.code = 'FINISHED_GOOD'`). |
| Harga | Sama seperti `/shop` — price list `REGULER` (via `ShopContextService::priceListId()`). |
| Pembayaran | Sama seperti `/shop` — Xendit (VA/transfer) + COD. |
| Sumber cabang/warehouse | **Opsi 1** — ikut konteks customer agent (`customer->getBranchId()`), persis `/shop`. |
| `order_type` | `'web-order'`. |

## Autentikasi & Middleware

- Agent login memakai guard **`customer`** yang sudah ada (kredensial sama seperti `/shop`; halaman login `/shop/login` existing tetap dipakai — TIDAK membuat login baru).
- **Middleware baru** `App\Http\Middleware\EnsureCustomerIsAgent` (alias **`agent`**), didaftarkan di `bootstrap/app.php` (`$middleware->alias([...])`). Logika: ambil `Auth::guard('customer')->user()`; jika null → redirect ke login customer; jika ada tapi `! $customer->isPartnerAgent()` → tolak (redirect ke `route('customer.shop')` dengan pesan, atau `abort(403)`).
- Landing page digate `['auth:customer', 'agent']`. Ini "middleware yang dibedakan" dari `/shop` (yang cuma `auth:customer`).

## Route & Controller

- File route baru `routes/agent.php`, di-`require` dari `routes/web.php` (pola sama seperti `routes/customer.php`/`routes/marketing.php`).
- Prefix `/agen-order`, name `agent-order.`, group middleware `['auth:customer', 'agent']`. Endpoint (mirror `ShopController`):
  - `GET /` → `index` (katalog produk jadi + keranjang)
  - `GET /products/variants` → `productVariants` (JSON varian per produk)
  - `POST /cart/add` | `POST /cart/update` | `POST /cart/remove` → operasi keranjang
  - `GET /checkout` → `checkout` (form) ; `POST /checkout` → `checkoutProcess`
  - `GET /payment/return` → `paymentReturn` ; `GET /payment/{orderId}/status` → `paymentStatus`
  - `GET /orders` → `orders` ; `GET /orders/{order}` → `orderShow`
- Controller baru `App\Http\Controllers\Agent\AgentOrderController` — struktur mirror `App\Http\Controllers\Customer\ShopController`, memakai `ShopContextService`, `ShopCartService`, `ShopCheckoutService` yang sudah ada (di-resolve via container). Beda utama vs ShopController:
  1. `index()` memfilter katalog ke produk jadi (`whereHas('nature', fn($q) => $q->where('code', 'FINISHED_GOOD'))`), selain filter `saleItems()` + `branch_id` yang sudah ada.
  2. `checkoutProcess()` memanggil checkout dengan `order_type = 'web-order'` (lihat bagian Checkout).
  3. View mengarah ke `resources/views/agent/order/*` (bukan `customer/shop/*`).

## Checkout & Data Order

- `App\Services\Shop\ShopCheckoutService::processXendit()` dan `processCod()` ditambah parameter `string $orderType = 'web'` (default menjaga perilaku `/shop` tak berubah), lalu diteruskan ke argumen ke-9 `createSalesOrder(...)`. Controller agent memanggil dengan `'web-order'`.
- Hasil: `transaction.sales_orders.order_type = 'web-order'`, `customer_id` = id customer-agent. Nomor order, `SalesOrderPayment`, sinkronisasi status Xendit — semua ikut mekanisme `/shop`.
- Riwayat order agent (`orders()`/`orderShow()`) memfilter `where('order_type', 'web-order')->where('customer_id', <agent customer id>)` (bukan `'web'` seperti `/shop`).

## Tampilan

- View baru di `resources/views/agent/order/` (mis. `index.blade.php`, `checkout.blade.php`, `orders.blade.php`, `order-show.blade.php`), boleh mirror struktur `resources/views/customer/shop/*` agar cepat & konsisten, dengan heading/branding "Order ke Distributor" untuk membedakan dari toko customer.

## Edge Cases & Titik Perhatian

- **Non-agent ditolak:** customer biasa (bukan agent) yang membuka `/agen-order` harus ditolak middleware `agent` — verifikasi eksplisit.
- **Keranjang berbagi session:** `ShopCartService::SESSION_KEY = 'customer_shop_cart'` dipakai bersama. Karena agent = customer, keranjang landing agent & `/shop` (bila agent membuka `/shop`) **berbagi keranjang yang sama**. Untuk B2B agent umumnya tak masalah (agent hanya memakai landing-nya). Bila bisnis butuh benar-benar terpisah, tambah session key khusus agent (mis. subclass/param `ShopCartService`) — dicatat sebagai kemungkinan upgrade, TIDAK dikerjakan di scope awal kecuali diminta.
- **Katalog kosong / harga tak tersedia:** ikut perilaku `/shop` (`ShopContextService::assertReady()` + filter `has_price`).
- **Sumber warehouse (Opsi 1):** mengikuti cabang customer. Bila kelak bisnis butuh order ditujukan ke `partner.agents.default_warehouse_id` (Opsi 2), itu upgrade terpisah (override resolusi warehouse di checkout) — dicatat, di luar scope awal.

## Testing / Verifikasi

Project TIDAK punya automated test suite (kebijakan disengaja) — verifikasi via `php -l`, `php artisan route:list`, `php artisan view:cache`, dan smoke `tinker`:
- Middleware `agent` menolak customer non-agent, meloloskan customer-agent.
- Katalog `/agen-order` hanya menampilkan produk `FINISHED_GOOD`.
- Alur keranjang → checkout (Xendit & COD) menghasilkan `sales_orders` dengan `order_type = 'web-order'` + `customer_id` benar.
- Riwayat order agent hanya menampilkan order `web-order` milik agent tsb.
- Route `agent-order.*` terdaftar dengan middleware `auth:customer` + `agent`.
