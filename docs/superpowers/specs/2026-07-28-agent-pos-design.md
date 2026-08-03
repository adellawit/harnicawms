# POS Agen (Agent POS) — Design Spec

**Tanggal:** 2026-07-28
**Branch:** `feature/agent-catalog-polish` (satu branch, dibedakan per commit)
**Status:** Disetujui untuk ditulis jadi handoff Cursor

## Tujuan

Menyediakan **POS untuk agen** di portal agen: agen menjual produk **dari gudang miliknya sendiri** kepada **reseller yang ter-relasi dengan agen tersebut**, dengan tampilan mengikuti mockup PM (dua panel: kiri katalog + strip campaign, kanan keranjang + metode bayar + tombol Bayar).

Secara fungsi, POS admin yang ada sudah mengcover kebutuhan (grid produk, modal varian, diskon per-item & transaksi, promo engine buy-X-get-Y, multi metode bayar Tunai + Xendit, pengurangan stok). POS agen **meminjam** logika itu, di-scope ke konteks agen. **Restyle mockup hanya untuk POS agen** — admin POS dibiarkan apa adanya.

## Temuan kunci dari kode (dasar desain)

1. **Reseller = Customer.** `App\Models\Partner\Reseller` punya `customer_id` → `belongsTo(Customer)`. `Agent` juga punya `customer_id`. `Customer` punya `isPartnerAgent()`, `isPartnerReseller()`, `partnerRole()`. Jadi buyer POS agen disimpan sebagai `customer_id` biasa (yaitu `reseller->customer_id`) — **tanpa kolom/migrasi baru**.

2. **`PosCheckoutService` (shared) sudah sadar-agen.** `completePaidOrder()` memanggil `resolveAgentDestinationWarehouse($order)`:
   - Bila **buyer adalah Agent** → stok keluar dari gudang order lalu **masuk ke gudang agen** (skenario admin jual ke agen).
   - Bila **buyer reseller / walk-in** → return `null` → **penjualan biasa (outbound saja, tanpa stock-in)**. Komentar di kode: *"Reseller / walk-in: null (sale only, no agent stock-in)."*

   Karena buyer POS agen = **reseller**, `resolveAgentDestinationWarehouse` mengembalikan null → outbound murni. **Inilah perilaku yang kita mau.**

3. **Satu-satunya celah: sumber gudang.** `createSalesOrder()` meng-hardcode
   `$warehouseId = WmsContext::defaultWarehouse($branchId)` (gudang default cabang), lalu tiap `SalesOrderItem.source_warehouse_id` memakai itu, dan `completePaidOrder()` melakukan outbound dari `source_warehouse_id`. Untuk POS agen, sumber stok harus **gudang milik agen** (`Agent::default_warehouse_id`), bukan gudang default cabang.

4. **Helper tersedia:** `WmsContext::defaultAgentWarehouse($agentId)` dan `Agent::defaultWarehouse` sudah ada.

5. **Campaign = modul Promotion** temanmu (`product.promotions`, `Promotion::activeNow()`). Katalog agen sudah memakai sumber ini untuk menandai produk promo. Strip campaign di POS memakai sumber yang sama.

## Arsitektur

- **Controller baru** `App\Http\Controllers\Agent\AgentPosController` (guard `auth:customer` + middleware `agent`). Meniru method admin `POSController`: `index`, `getProductVariants`, `previewPromo`, `processPayment` (cash + Xendit), `paymentStatus`, `paymentReturn`. Perbedaan: konteks agen, bukan `auth('web')`.
- **Reuse `PosCheckoutService`** dengan **satu perubahan backward-compatible**: `createSalesOrder()` menerima parameter opsional `?string $warehouseId = null` (baru, di akhir signature). Bila diisi → dipakai sebagai gudang order & sumber stok; bila `null` → perilaku lama (`WmsContext::defaultWarehouse($branchId)`) tetap. **Admin POS tidak berubah** karena tidak mengirim argumen baru.
- **Konteks agen:**
  - Agen login = `auth('customer')->user()` (Customer). `$agent = $customer->agent`.
  - Branch order = branch agen (dari `ShopContextService` yang sudah dipakai `AgentOrderController`, atau `$agent`/warehouse agen). Gunakan sumber branch yang konsisten dengan `AgentOrderController` agar produk & harga terbaca sama.
  - Gudang jual = `WmsContext::defaultAgentWarehouse($agent->id)` (fallback `$agent->defaultWarehouse`). Bila agen belum punya gudang → tampilkan pesan & blokir transaksi (service sudah melempar RuntimeException untuk kasus agen-tanpa-gudang).
  - Buyer picker = `$agent->resellers()->with('customer')` — hanya reseller **aktif** yang `customer_id`-nya terisi; simpan `reseller->customer_id` sebagai `customer_id` order.
- **Pajak:** POS agen **tanpa PPN** (mockup tak menampilkan baris pajak) → `tax_enabled = false`, `tax_rate = 0`.
- **Ongkir:** tetap **0** (konsisten portal agen).
- **`order_type = 'agent-pos'`** (baru) agar penjualan POS agen bisa dibedakan dari `pos` (admin), `web-order` (order agen→distributor), `web` (customer /shop). Diteruskan ke `createSalesOrder()`.

## Tampilan (mockup)

Layout **fullscreen dua panel**, tanpa nav portal agen (POS biasanya fullscreen). Buat **layout minimal baru** `layouts.agent-pos` (atau blade self-contained) — jangan pakai `layouts.agent-order` (punya nav portal) maupun `<x-app-layout>` (admin).

- **Panel kiri:** search produk + chip kategori (`product_natures`) + grid produk (klik → modal varian, sama pola admin) + **strip campaign** di bawah (kartu dari `Promotion::activeNow()`; sembunyikan bila kosong).
- **Panel kanan:** pilih reseller (dropdown) + tabel line item (qty stepper, diskon %/nominal per-item, subtotal, hapus) + tombol aksi (Diskon transaksi, Promo, Ongkir [disabled/0], Catatan, Hapus Semua) + ringkasan (Subtotal, Diskon item, Ongkir Rp 0, **TOTAL**) + metode bayar (Tunai + Xendit) + input bayar/kembalian (untuk Tunai) + tombol **Bayar**.
- Reuse pola markup + JS admin POS semaksimal mungkin, tapi **file terpisah** (blade/JS agen) agar admin POS tak tersentuh. Pakai token warna brand (`--bs-primary`) bukan hex.

## Rencana slice (handoff terpisah, satu branch, beda commit)

 Diproses berurutan; tiap slice punya file handoff sendiri:

1. **Slice 1 — Backend & routing.** `AgentPosController` (index + product-variants + preview-promo + payment cash/Xendit + status/return), route group di `routes/agent.php`, perubahan aditif `PosCheckoutService::createSalesOrder()` (param `?string $warehouseId = null`), `order_type='agent-pos'`, buyer=reseller-as-customer, gudang=agen, tanpa pajak. View sementara boleh placeholder minimal (fokus backend benar).
2. **Slice 2 — View POS (layout mockup).** Layout fullscreen `layouts.agent-pos` + view `agent/pos/index.blade.php`: grid + kategori + panel keranjang + metode bayar + tombol Bayar. Meniru admin POS. Wiring ke endpoint Slice 1.
3. **Slice 3 — Strip campaign.** Kartu campaign dari `Promotion::activeNow()` di panel kiri bawah; kosong → tersembunyi.
4. **Slice 4 — Entry point.** Tautan/kartu "POS" (kasir) di dashboard/nav portal agen → membuka halaman POS agen.

## Di luar cakupan (sekarang)

- Restyle admin POS (kamu pilih "Agent saja").
- Ongkir non-nol / integrasi ekspedisi.
- PPN pada POS agen.
- Kolom `reseller_id` khusus di `sales_orders` (tak perlu — reseller = customer).
- Kartu debit/kredit langsung (mengikuti admin POS yang juga belum: pakai Tunai atau Xendit).

## Verifikasi (tanpa test suite otomatis)

`php -l` file yang disentuh; `php artisan route:list --name=agent-order.pos`; `php artisan view:cache && view:clear`; smoke manual login agen: pilih reseller → tambah produk → bayar Tunai → order `agent-pos` completed, stok **berkurang di gudang agen**, tak ada stock-in ke gudang lain. Regresi: admin POS (`/pos`) & /shop customer tetap normal.
