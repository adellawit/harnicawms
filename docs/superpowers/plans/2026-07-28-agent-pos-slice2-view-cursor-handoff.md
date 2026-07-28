# Cursor Handoff — POS Agen Slice 2: View & Layout (mockup)

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-28-agent-pos-slice2-view-cursor-handoff.md".
> Spec: `docs/superpowers/specs/2026-07-28-agent-pos-design.md`. Prasyarat: Slice 1 (backend & routes) sudah ada.
> SCOPE = tampilan POS agen sesuai mockup PM. **JANGAN ubah admin POS** (`admin/transaction/pos.blade.php`) & backend Slice 1 (kecuali menambah variabel view yang kurang).

## Referensi mockup

Dua panel:
- **Kiri:** search "Scan barcode / Cari produk", dropdown kategori, tombol Filter, chip kategori (Semua/Minuman/Makanan/…), grid produk (kartu: gambar, nama, kode, harga), dan **strip campaign** di kiri-bawah (Slice 3 — sisakan tempatnya).
- **Kanan:** dropdown pelanggan ("Pelanggan Umum (Walk In)" → di sini = reseller agen) + tombol "Tambah Customer" (opsional, boleh disembunyikan dulu), tabel line item (No, Produk+kode, Harga, Qty stepper −/+, Diskon %, Subtotal, hapus), baris tombol (Diskon F4, Promo F5, Ongkir F6, Catatan F7, Hapus Semua F8), ringkasan (Subtotal (n item), Diskon Item, Ongkir Rp 0, **TOTAL**), Metode Pembayaran (Tunai/QRIS/Kartu Debit/Kartu Kredit/Lainnya), Bayar & Kembalian, tombol besar **BAYAR (F1)**.

## Pendekatan

**Reuse markup + JS admin POS** (`resources/views/admin/transaction/pos.blade.php`, 2687 baris) sebagai basis, tapi **file terpisah** untuk agen. Admin POS sudah punya: grid produk, modal varian, cart state, diskon per-item & transaksi, promo preview (`previewPromo`), pemilihan metode bayar, alur cash + Xendit. Tugas Slice 2 = membungkusnya dalam **layout fullscreen dua-panel bergaya mockup** dan mengarahkan endpoint ke route `agent-order.pos*`.

## Langkah 1 — Layout fullscreen `layouts.agent-pos`

Buat `resources/views/layouts/agent-pos.blade.php`: layout minimal fullscreen (viewport tinggi penuh, dua kolom), **tanpa** nav portal agen dan **tanpa** sidebar admin. Sertakan aset yang dibutuhkan admin POS (CSS/JS vendor yang dipakai `pos.blade.php` — lihat blok `@push('vendor-css')`/`@push('page-css')`/`@push('scripts')` di admin POS untuk daftarnya). Sediakan `@yield('content')` + `@stack('scripts')`. Header ringkas: nama toko/agen + tombol keluar ke `route('agent-order.dashboard')`.

- Tema warna: token brand `--bs-primary` (hijau `#5C9E84`), **bukan** hex mockup (mockup biru hanya ilustrasi). Ikuti design system project (`/design-system`).

## Langkah 2 — View `agent/pos/index.blade.php`

Ganti placeholder Slice 1. Extends `layouts.agent-pos`. Susun dua panel:

**Panel kiri (`col`):**
- Search bar + dropdown kategori + tombol Filter (fungsional minimal; search memfilter grid seperti admin).
- Chip kategori dari `$productTypes` (product_natures) — "Semua" + tiap tipe; klik memfilter grid.
- Grid produk dari `$products` — reuse komponen `resources/views/components/pos/product-card.blade.php` bila cocok, atau markup kartu setara. Klik kartu → modal varian (reuse pola modal + `fetch('{{ route("agent-order.pos.product-variants") }}?product_id=…&price_list_id=…')`).
- **Placeholder strip campaign** di bawah grid (kosong dulu; Slice 3 mengisi). Beri container `#posCampaignStrip` agar Slice 3 tinggal mengisi.

**Panel kanan (`col`, lebar tetap mis. `col-xl-5`):**
- **Dropdown reseller** dari data yang dikirim controller (Slice 1: daftar reseller agen; value = `customer_id`). Label "Pelanggan (Reseller)". Default kosong/Walk-in bila diizinkan (boleh wajib pilih reseller — sesuaikan; minimal boleh walk-in dengan customer_id null).
- Tabel line item + qty stepper + diskon per-item + subtotal + hapus — reuse logika cart admin (`resources/views/components/pos/cart-item.blade.php` bila cocok).
- Baris tombol Diskon/Promo/Ongkir/Catatan/Hapus Semua. **Ongkir = tampil "Rp 0" & non-aktif** (portal agen ongkir 0). Promo memakai `route('agent-order.pos.preview-promo')`.
- Ringkasan: Subtotal, Diskon Item, **Ongkir Rp 0**, **TOTAL**. **Tanpa baris PPN** (tax 0).
- Metode Pembayaran dari `$methodPayments`/`$nonXenditMethods`/`$xenditChannelGroups` (sama seperti admin). Tunai → input "Bayar" + hitung "Kembalian". Xendit → redirect.
- Tombol **BAYAR** → submit ke `route('agent-order.pos.payment')` (POST) dengan payload sama seperti admin (`items[]`, `payment_method_id`, `customer_id`, `amount_paid`, `discount_*`, `price_list_id`, `notes`, `xendit_channel`). Tangani respons sukses (tampilkan kembalian/nomor transaksi, reset cart) & Xendit (redirect `invoice_url`), mirip admin.

## Langkah 3 — JS

Reuse skrip admin POS. Cara termudah & paling aman: **salin** blok `@push('scripts')` POS admin ke view agen, lalu ganti **hanya URL endpoint** ke route `agent-order.pos.*`. Definisikan objek route di Blade:
```blade
<script>
window.agentPosRoutes = {
    productVariants: @json(route('agent-order.pos.product-variants')),
    previewPromo:    @json(route('agent-order.pos.preview-promo')),
    payment:         @json(route('agent-order.pos.payment')),
    paymentStatus:   @json(url('agen-order/pos/payment')), // + '/{id}/status'
};
</script>
```
Jangan memuat ulang aset yang sudah ada di layout (hindari double-load). Pastikan `csrf-token` meta tersedia di layout untuk POST.

Verifikasi: `php artisan view:cache && php artisan view:clear`.

## Verifikasi akhir

```bash
php artisan view:cache && php artisan view:clear
```
Smoke manual (login agen → `/agen-order/pos`), sebaiknya cek di preview browser:
- Dua panel tampil seperti mockup; grid produk & chip kategori berfungsi (filter).
- Klik produk → modal varian → tambah ke keranjang; qty stepper & diskon per-item meng-update subtotal & TOTAL.
- Pilih reseller; TOTAL tanpa baris PPN; Ongkir Rp 0.
- Bayar Tunai → sukses (kembalian benar), keranjang reset. (Backend Slice 1 sudah menyimpan order agent-pos & mengurangi stok gudang agen.)
- Tak ada error konsol JS; admin POS `/pos` tetap normal.

## Checklist

- [ ] Layout `layouts.agent-pos` fullscreen dua panel (aset POS termuat, tanpa nav portal/admin).
- [ ] View `agent/pos/index.blade.php`: kiri (search+kategori+grid+placeholder campaign), kanan (reseller+cart+metode bayar+BAYAR).
- [ ] Dropdown pelanggan = reseller agen (value customer_id); tanpa PPN; Ongkir Rp 0.
- [ ] JS reuse admin POS, endpoint diarahkan ke `agent-order.pos.*`; tanpa double-load aset.
- [ ] Bayar Tunai & alur Xendit jalan; admin POS tak berubah; token warna brand (bukan hex mockup).
