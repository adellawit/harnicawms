# Cursor Handoff — Poles Kosmetik Keranjang & Checkout Agen (slice 4)

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-23-agent-cart-checkout-cosmetic-cursor-handoff.md".
> Aturan permanen ada di `.cursorrules`.
> PENTING — scope KOSMETIK saja. Fungsi pengiriman/ongkir/checkout SUDAH ADA & JALAN. JANGAN ubah controller, ShopCheckoutService, logika keranjang, atau perhitungan total. Hanya sunting tampilan (Blade + CSS). Slice TERPISAH dari katalog/riwayat — hanya sentuh file yang disebut di bawah.

## Konteks (yang SUDAH ada — jangan diulang)

- Checkout agen (`resources/views/agent/order/checkout.blade.php`) sudah punya kartu **Pengiriman** (Dikirim dari → Agen, alamat editable `shipping_address`) dan baris **Ongkos Kirim** (Rp 0) via partial `resources/views/agent/order/_checkout-summary.blade.php`.
- Persist alamat & `shipping_amount=0` sudah jalan di `ShopCheckoutService`. JANGAN sentuh.

## Tujuan (kosmetik, samakan dengan mockup)

1. Tambah baris "Estimasi ongkir Rp 0" di footer keranjang drawer.
2. Selaraskan wording label dengan mockup.
3. Rapikan tampilan ringan (opsional, CSS aditif).

## Langkah 1 — Footer keranjang drawer

File `resources/views/agent/order/_cart-footer.blade.php`. Tambahkan baris "Estimasi ongkir" SEBELUM baris Total, dan ubah teks tombol jadi "Lanjut Checkout". Hasil akhir:

```blade
@if (($summary['item_count'] ?? 0) > 0)
    <div class="d-flex justify-content-between small mb-1">
        <span>Subtotal ({{ $summary['item_count'] }} item)</span>
        <span>Rp {{ number_format($summary['subtotal'], 0, ',', '.') }}</span>
    </div>
    @if ($summary['tax_enabled'] ?? false)
        <div class="d-flex justify-content-between small text-muted mb-1">
            <span>PPN ({{ $summary['tax_rate'] }}%)</span>
            <span>Rp {{ number_format($summary['tax_amount'], 0, ',', '.') }}</span>
        </div>
    @endif
    <div class="d-flex justify-content-between small text-muted mb-2">
        <span>Estimasi ongkir</span>
        <span>Rp 0</span>
    </div>
    <div class="d-flex justify-content-between fw-bold mb-3">
        <span>Total</span>
        <span class="text-primary">Rp {{ number_format($summary['total'], 0, ',', '.') }}</span>
    </div>
    <a href="{{ route('agent-order.checkout') }}" class="btn btn-primary w-100"><i class="ti ti-arrow-right me-1"></i>Lanjut Checkout</a>
@endif
```
Catatan: ongkir hardcode "Rp 0" di drawer (belum ada konteks alamat di drawer; total tetap `$summary['total']` tanpa menambah ongkir karena ongkir memang 0). JANGAN mengubah nilai `$summary['total']`.

## Langkah 2 — Wording ringkasan checkout

File `resources/views/agent/order/_checkout-summary.blade.php`. Ganti label agar seragam dengan mockup, TANPA mengubah nilai/perhitungan:
- Label baris ongkir "Ongkos Kirim" → **"Estimasi ongkir"**.
- Label baris terakhir "Total" → **"Total pembayaran"**.

Sisanya (id `checkoutSubtotal`/`checkoutTax`/`checkoutShipping`/`checkoutTotal`, rumus `$summary['total'] + ($shippingAmount ?? 0)`) BIARKAN persis seperti sekarang.

## Langkah 3 — Poles visual ringan (opsional, CSS aditif)

Bila ingin lebih mirip mockup, tambahkan aturan CSS BARU di `public/assets/css/shop.css` (JANGAN ubah/hapus aturan lama; tambah blok baru di bawah):
- Drawer keranjang: spacing item lebih lega, thumbnail rounded, stepper qty rapi.
- Kartu checkout: header kartu tebal, radius konsisten.
Ini opsional dan murni CSS; lewati bila tak yakin agar tak merusak tampilan yang sudah rapi.

## Verifikasi

```bash
php artisan view:cache && php artisan view:clear
```
Smoke manual (login customer-agent, isi keranjang):
- Buka drawer keranjang → muncul baris "Estimasi ongkir Rp 0" + tombol "Lanjut Checkout". Total tidak berubah nilainya.
- Halaman checkout → ringkasan memakai label "Estimasi ongkir" & "Total pembayaran"; kartu Pengiriman & alamat editable tetap berfungsi seperti sebelumnya.
- Checkout (COD/Xendit) tetap jalan; `sales_orders` tersimpan seperti biasa (`shipping_amount=0`, alamat dari field).
- Regresi: nilai subtotal/pajak/total tidak berubah; `/shop` customer tidak tersentuh.

## Checklist

- [ ] `_cart-footer`: baris "Estimasi ongkir Rp 0" + tombol "Lanjut Checkout"; nilai total tak diubah.
- [ ] `_checkout-summary`: label "Estimasi ongkir" & "Total pembayaran"; nilai/id/rumus tetap.
- [ ] (opsional) CSS aditif di shop.css tanpa mengubah aturan lama.
- [ ] Tidak menyentuh controller/service/logika; view:cache bersih; checkout tetap berfungsi.
