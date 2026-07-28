# Poles Riwayat Pesanan Agen — Design Spec (PRD)

**Tanggal:** 2026-07-23
**Branch:** `feature/agent-catalog-polish` (branch portal agen berjalan; dibedakan per-commit)
**Status:** Approved (brainstorming) — siap diimplementasikan via Cursor.
**Bagian dari:** slicing UI portal agen. Slice #3 (Riwayat Pesanan).

## Ringkasan

Menyempurnakan halaman riwayat pesanan agen (`agent.order.orders.index`) sesuai mockup: header + tombol "Order baru", **chip filter** (Semua/Pending/Selesai/Belum bayar/Dibatalkan), dan tiap baris ditambah **thumbnail produk**, **jumlah item**, dan **badge metode bayar**. Kerangka sudah ada (baris order + badge status/bayar + paginasi); ini penyempurnaan tampilan + 1 filter.

## Konteks (kode yang sudah ada)

- `AgentOrderController::orders()` : `SalesOrder::where('order_type','web-order')->where('customer_id',$cid)->with('payments.methodPayment')->orderByDesc('created_at')->paginate(15)`.
- View `resources/views/agent/order/orders/index.blade.php` : baris order (SO number, total, tanggal, badge `status` + `payment_status`), empty state, paginasi `withQueryString()`.
- Relasi tersedia: `SalesOrder::items()` HasMany, `SalesOrderItem::product()` (untuk `product->image`), `SalesOrder::methodPayment()` belongsTo (`method_payment_id`), `MethodPayment` punya `name`/`code`.

## Perubahan

### Controller `orders()`
- Eager-load tambah: `items.product` dan `methodPayment`. (`withCount('items')` opsional untuk jumlah item.)
- Terima query param `?filter=<key>` (chip aktif), dipetakan:

| key | efek query |
|---|---|
| (kosong) / `all` | tanpa filter |
| `pending` | `where('status','pending')` |
| `completed` | `where('status','completed')` |
| `unpaid` | `where('payment_status','unpaid')` |
| `cancelled` | `where('status','cancelled')` |

  Key tak dikenal → diabaikan (tampilkan semua). Kirim `$activeFilter` ke view. Paginasi tetap `withQueryString()` agar filter ikut antar-halaman.

### View `orders/index.blade.php`
- **Header**: eyebrow "PORTAL AGEN · WEB ORDER", judul "Riwayat Pesanan", subjudul singkat, tombol **"Order baru"** → `route('agent-order.index')`.
- **Chip filter** (single-select, 5 chip): Semua/Pending/Selesai/Belum bayar/Dibatalkan → link `?filter=<key>`, chip aktif ditandai dari `$activeFilter`.
- **Tiap baris order** (pertahankan link ke `agent-order.orders.show` + badge status/bayar yang ada), tambah:
  - **Thumbnail**: `$order->items->first()?->product?->image` (fallback ikon `ti ti-package`).
  - **Jumlah item**: `$order->items->count()` atau `items_count` → "N item".
  - **Badge metode bayar**: `$order->methodPayment?->name ?? $order->payments->first()?->methodPayment?->name` (mis. COD / VA BCA / QRIS). Sembunyikan bila null.
- Paginasi existing dipertahankan.

## Edge cases

- Filter tanpa hasil → empty state kontekstual ("Belum ada pesanan pada filter ini."), tetap tampilkan chip.
- Order tanpa item → thumbnail fallback ikon, "0 item".
- Order tanpa metode bayar → badge metode bayar disembunyikan (bukan "null").
- `?filter=` invalid → semua pesanan.

## Regresi

- Tidak menyentuh checkout, detail order (`orderShow`), keranjang, katalog, `/shop`. Hanya `orders()` + view riwayat.

## Testing / Verifikasi

Tanpa automated test — `php -l`, `php artisan route:list --name=agent-order.orders`, `php artisan view:cache`, tinker/manual:
- Riwayat render: header + tombol Order baru + 5 chip + baris dengan thumbnail/jumlah item/badge metode bayar.
- Tiap chip memfilter benar (`?filter=pending|completed|unpaid|cancelled`); invalid → semua.
- Paginasi mempertahankan filter.
- Detail order & alur lain tak berubah.
