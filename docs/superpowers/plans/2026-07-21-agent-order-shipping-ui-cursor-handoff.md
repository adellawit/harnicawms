# Cursor Handoff — Info Pengiriman di Checkout Agent (slicing UI, ongkir 0)

> Konten siap ditunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-21-agent-order-shipping-ui-cursor-handoff.md".
> Aturan project permanen sudah ada di `.cursorrules` — JANGAN tempel isi file ini ke `.cursorrules`.

## Konteks & tujuan

Landing page order agent→distributor (`/agen-order`, commit `e188d057`) sudah jadi: guard `customer` + middleware `agent`, katalog produk jadi, checkout membuat `transaction.sales_orders` dengan `order_type = 'web-order'`.

Yang kurang: **informasi pengiriman** dari distributor ke agent. Arahan PM: **slicing UI saja dulu dengan data yang ada** — tampilkan dikirim dari kota mana ke kota mana, alamat pengiriman bisa diedit agent, dan tampilkan baris ongkir yang untuk sekarang **selalu Rp 0** (data tarif ongkir belum ada).

**Scope tegas:** ini murni tampilan + penyimpanan alamat. TIDAK membuat tabel baru, TIDAK menghitung ongkir, TIDAK menyentuh modul Replenishment/pengiriman fisik.

## Data yang dipakai (sudah ada semua — tanpa migrasi)

| Kebutuhan | Sumber | Catatan |
|---|---|---|
| Kota asal (distributor) | `master_data.business_units.city` / `.province` | Terisi lengkap (mis. Bandung, Jawa Barat). Diakses via `ShopContextService::branch()`. |
| Kota tujuan (agent) | `partner.agents.city` / `.province` | `city` terisi untuk 5 dari 6 agen; **`province` KOSONG semua** → tampilkan kota saja bila province kosong. Diakses via `$customer->agent` (relasi `Customer::agent()` HasOne). |
| Alamat kirim (editable) | `customer.customers.address_shipping`, fallback `.address`, fallback `partner.agents.address` | `address_shipping` hasil migrasi 2026-07-15. |
| Simpan alamat order | `transaction.sales_orders.customer_address` | Kolom sudah ada. Saat ini diisi `$customer->address` di `ShopCheckoutService`. |
| Ongkir | `transaction.sales_orders.shipping_amount` | Kolom sudah ada, **default 0**. Cukup dibiarkan/di-set 0. |

## Yang HARUS diperhatikan (jebakan)

1. **`resources/views/customer/shop/_checkout-summary.blade.php` DIPAKAI BERSAMA** oleh checkout agent DAN checkout customer `/shop`. **JANGAN mengubah file itu** — buat partial baru khusus agent. Kalau diubah, toko customer ikut menampilkan baris ongkir (tidak diinginkan).
2. `resources/views/agent/order/checkout.blade.php` saat ini masih meng-`@include('customer.shop._checkout-summary', ...)` di DUA tempat (desktop + mobile). Keduanya harus diarahkan ke partial agent yang baru.
3. Ringkasan checkout **di-render server**, tidak ada AJAX re-render (`public/assets/js/shop-checkout.js` hanya `window.location.reload()` setelah ubah qty). Jadi tak ada JS yang perlu disesuaikan untuk baris ongkir.
4. `ShopCheckoutService::processXendit()` / `processCod()` dipakai BERSAMA oleh `/shop` dan `/agen-order`. Penambahan parameter WAJIB opsional dengan default, agar perilaku `/shop` tidak berubah (pola ini sudah dipakai untuk `$orderType = 'web'`).

## Langkah implementasi

### Langkah 1 — Controller: siapkan data pengiriman

Di `app/Http/Controllers/Agent/AgentOrderController.php`, method `checkout()` (sekitar baris 235-263), sebelum `return view(...)`, susun data pengiriman:

```php
$customer = $ctx->customer();
$agent = $customer->agent;              // relasi HasOne ke partner.agents (boleh null)
$originUnit = $ctx->branch();           // BusinessUnit distributor (boleh null)

$shipping = [
    'origin_city' => $originUnit?->city,
    'origin_province' => $originUnit?->province,
    'origin_name' => $originUnit?->brand_name ?: $originUnit?->name,
    'destination_city' => $agent?->city,
    'destination_province' => $agent?->province,
    'destination_name' => $agent?->name ?: $customer->name,
    'address' => old('shipping_address')
        ?: ($customer->address_shipping ?: ($customer->address ?: $agent?->address)),
    'amount' => 0,   // ongkir belum tersedia — selalu 0 untuk sekarang
];
```

Tambahkan ke array data view: `'shipping' => $shipping,`.

Verifikasi: `php -l app/Http/Controllers/Agent/AgentOrderController.php`.

### Langkah 2 — Partial ringkasan khusus agent (dengan baris ongkir)

Buat file BARU `resources/views/agent/order/_checkout-summary.blade.php` (SALIN isi `resources/views/customer/shop/_checkout-summary.blade.php` lalu sisipkan baris ongkir sebelum baris Total):

```blade
<div class="d-flex justify-content-between small mb-1">
    <span>Subtotal</span>
    <span id="checkoutSubtotal">Rp {{ number_format($summary['subtotal'], 0, ',', '.') }}</span>
</div>
@if ($summary['tax_enabled'])
    <div class="d-flex justify-content-between small text-muted mb-2">
        <span>PPN ({{ $summary['tax_rate'] }}%)</span>
        <span id="checkoutTax">Rp {{ number_format($summary['tax_amount'], 0, ',', '.') }}</span>
    </div>
@endif
<div class="d-flex justify-content-between small text-muted mb-2">
    <span>Ongkos Kirim</span>
    <span id="checkoutShipping">Rp {{ number_format($shippingAmount ?? 0, 0, ',', '.') }}</span>
</div>
<div class="d-flex justify-content-between fw-bold fs-5">
    <span>Total</span>
    <span class="text-primary" id="checkoutTotal">Rp {{ number_format($summary['total'] + ($shippingAmount ?? 0), 0, ',', '.') }}</span>
</div>
```

Catatan: karena `$shippingAmount` selalu 0 untuk sekarang, Total tidak berubah nilainya — penjumlahan ditulis eksplisit agar nanti tinggal mengganti angka ongkir tanpa mengubah struktur.

**JANGAN mengubah** `resources/views/customer/shop/_checkout-summary.blade.php`.

### Langkah 3 — Checkout view: kartu pengiriman + pakai partial baru

Di `resources/views/agent/order/checkout.blade.php`:

**(a)** Ganti KEDUA `@include('customer.shop._checkout-summary', ['summary' => $summary])` (baris ~25 desktop dan ~68 mobile) menjadi:
```blade
@include('agent.order._checkout-summary', ['summary' => $summary, 'shippingAmount' => $shipping['amount']])
```

**(b)** Tambahkan kartu Pengiriman di kolom kanan (`col-lg-5`), **SEBELUM** card Pembayaran:

```blade
<div class="card border-0 shadow-sm shop-checkout-card mb-3">
    <div class="card-header bg-white py-3">
        <h2 class="h6 mb-0 fw-semibold"><i class="ti ti-truck-delivery me-1"></i>Pengiriman</h2>
    </div>
    <div class="card-body pt-0">
        <div class="d-flex align-items-center justify-content-between gap-2 py-3">
            <div class="text-center flex-grow-1">
                <div class="small text-muted">Dari</div>
                <div class="fw-semibold">{{ $shipping['origin_city'] ?: '-' }}</div>
                @if ($shipping['origin_province'])
                    <div class="small text-muted">{{ $shipping['origin_province'] }}</div>
                @endif
            </div>
            <i class="ti ti-arrow-right text-muted"></i>
            <div class="text-center flex-grow-1">
                <div class="small text-muted">Ke</div>
                <div class="fw-semibold">{{ $shipping['destination_city'] ?: '-' }}</div>
                @if ($shipping['destination_province'])
                    <div class="small text-muted">{{ $shipping['destination_province'] }}</div>
                @endif
            </div>
        </div>

        @unless ($shipping['destination_city'])
            <div class="alert alert-warning small py-2 mb-3">
                Kota tujuan belum diatur pada data agen. Hubungi admin untuk melengkapi.
            </div>
        @endunless

        <label class="form-label small text-muted mb-1" for="shippingAddress">Alamat pengiriman</label>
        <textarea name="shipping_address" id="shippingAddress" form="checkoutForm"
            class="form-control form-control-sm" rows="3" maxlength="1000"
            placeholder="Alamat lengkap penerima">{{ $shipping['address'] }}</textarea>
        <div class="form-text small">Ongkos kirim saat ini belum dihitung sistem (Rp 0).</div>
    </div>
</div>
```

**PENTING:** textarea memakai atribut `form="checkoutForm"` agar ikut terkirim walau berada di LUAR `<form>` pembayaran. Alternatif: pindahkan textarea ke dalam `<form id="checkoutForm">`. Pilih salah satu, pastikan field `shipping_address` benar-benar ikut ter-POST.

Verifikasi: `php artisan view:cache` (harus sukses), lalu `php artisan view:clear`.

### Langkah 4 — Simpan alamat ke order

**(a)** Di `app/Http/Controllers/Agent/AgentOrderController.php` method `checkoutProcess()`, tambahkan aturan validasi:
```php
'shipping_address' => 'nullable|string|max:1000',
```
Lalu teruskan nilainya ke pemanggilan checkout service (argumen baru di Langkah 4b), mis. `$request->input('shipping_address')`.

**(b)** Di `app/Services/Shop/ShopCheckoutService.php`, tambahkan parameter OPSIONAL di akhir signature `processXendit(...)` dan `processCod(...)`:
```php
?string $shippingAddress = null
```
Di KEDUA method, pada blok `$order->update([...])` yang saat ini berisi `'customer_address' => $customer->address,` (ada di 2 tempat, sekitar baris 92 dan 198), ubah menjadi:
```php
'customer_address' => $shippingAddress ?: $customer->address,
'shipping_amount' => 0,
```
Default `null` menjaga `/shop` tidak berubah perilakunya.

Verifikasi: `php -l` pada kedua file. Pastikan `/shop` (customer biasa) masih checkout normal.

### Langkah 5 — Tampilkan di detail order agent

Di `resources/views/agent/order/orders/show.blade.php`, tambahkan blok kecil menampilkan alamat & ongkir order (data dari `$order->customer_address` dan `$order->shipping_amount`), mis:

```blade
<div class="mb-3">
    <div class="small text-muted">Alamat pengiriman</div>
    <div>{{ $order->customer_address ?: '-' }}</div>
</div>
<div class="d-flex justify-content-between small">
    <span>Ongkos Kirim</span>
    <span>Rp {{ number_format($order->shipping_amount ?? 0, 0, ',', '.') }}</span>
</div>
```
Sesuaikan penempatan agar serasi dengan struktur kartu yang sudah ada di file tsb.

Verifikasi: `php artisan view:cache` sukses, lalu `view:clear`.

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentOrderController.php
php -l app/Services/Shop/ShopCheckoutService.php
php artisan view:cache && php artisan view:clear
```

Smoke manual (login sebagai customer yang berstatus agent, mis. agen dengan kota Jakarta Barat / Sidoarjo):
- Buka `/agen-order` → tambah item → `/agen-order/checkout`.
- Kartu **Pengiriman** tampil: "Dari Bandung → Ke Jakarta Barat" (province agen kosong → hanya kota; province distributor tampil).
- Textarea alamat ter-prefill dari `address_shipping`/`address`; bisa diedit.
- Ringkasan menampilkan baris **Ongkos Kirim Rp 0**; Total tidak berubah.
- Checkout (COD/Xendit) → cek DB order terbaru:
  ```bash
  php artisan tinker --execute="
  \$o = App\Models\SalesOrder::where('order_type','web-order')->latest('created_at')->first();
  echo 'addr: '.(\$o->customer_address ?: '-').PHP_EOL;
  echo 'shipping_amount: '.\$o->shipping_amount.PHP_EOL;
  "
  ```
  → `customer_address` = alamat yang diedit agent, `shipping_amount` = 0.
- Buka `/agen-order/orders/{id}` → alamat & ongkir tampil.
- **Regresi:** `/shop` (customer biasa) checkout masih normal, ringkasannya TIDAK menampilkan baris ongkir.

## Checklist

- [ ] `checkout()` mengirim `$shipping` (origin/destination/address/amount) ke view.
- [ ] Partial BARU `agent/order/_checkout-summary.blade.php` dengan baris Ongkos Kirim; partial customer TIDAK disentuh.
- [ ] Kedua `@include` di checkout agent mengarah ke partial baru.
- [ ] Kartu Pengiriman tampil (Dari → Ke), fallback aman saat kota/province kosong.
- [ ] Textarea `shipping_address` benar-benar ter-POST (cek atribut `form="checkoutForm"`).
- [ ] `shipping_address` tervalidasi & tersimpan ke `sales_orders.customer_address`; `shipping_amount` = 0.
- [ ] Detail order agent menampilkan alamat + ongkir.
- [ ] `/shop` customer tidak berubah (tanpa baris ongkir, checkout normal).
