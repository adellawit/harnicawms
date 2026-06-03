# Mapping: Transaksi Excel → Skema `transaction.*`

Sumber: `docs/Transaksi 1 May 2024 - 24 May 2026.xlsx` (sheet **Net Revenue**)

Data diekspor ke: `database/seeders/data/transactions_history.json` (488 baris = 488 order POS, 1 produk per order).

---

## Ringkasan data

| Aspek | Nilai |
|--------|--------|
| Periode | 2024-10-01 s/d 2026-05-21 |
| Jenis | Point Of Sales (semua) |
| Status | Complete (semua) |
| Cabang asumsi impor | **Bandung** (`WWW-BDG-001`) |
| Price list | **REGULER** (company-level) |
| Produk unik | 29 nama |
| Pembayaran | OVO 477, Debit Card 6, Cash 4, Online Order 1 |

> **Catatan Excel:** Baris data hanya ada di **12 kolom kiri**. Header baris 1 juga berisi kolom duplikat (blok kanan kosong) — parser harus memakai indeks kolom 0–11, bukan nama header duplikat.

---

## Kolom Excel → Tabel database

### `transaction.sales_orders`

| Kolom Excel | Field DB | Transformasi |
|-------------|----------|----------------|
| `ID Transaksi` | `sales_number` | Langsung (unik, contoh `1-01102024-8`) |
| `ID Transaksi` | `reference` | Sama — referensi sistem lama |
| `Tanggal` | `sales_date` | Date dari datetime transaksi |
| `Jenis` | `order_type` | `Point Of Sales` → **`pos`** |
| `Status Transaksi` | `status` | `Complete` → **`completed`** |
| — | `payment_status` | **`paid`** (semua lunas) |
| `Grand Total` | `subtotal`, `total` | Sama (1 item, tanpa pajak/order discount) |
| `Discount` | `item_discount_total`, `discount_amount` | Diskon baris |
| — | `tax_enabled` | **`false`** |
| — | `tax_amount` | **0** |
| — | `company_id` | WWW (`WWW-001`) |
| — | `branch_id` | Bandung (`WWW-BDG-001`) |
| — | `price_list_id` | REGULER |
| `Pembayaran` | `method_payment_id` | Lihat mapping pembayaran |
| `Pembayaran` | `paid_at` | Parse tanggal bayar dari string |
| `Tanggal` / `paid_at` | `fulfilled_at` | `paid_at` atau `sales_date` |
| — | `customer_name` | **`Walk-in Customer`** |
| `Keterangan` + legacy | `notes` | Gabungan catatan |

### `transaction.sales_order_items`

| Kolom Excel | Field DB | Transformasi |
|-------------|----------|----------------|
| `Produk` | `product_id`, `product_variant_id` | Resolve ke master produk (lihat bawah) |
| — | `unit_id` | PCS (`default_unit_id` produk) |
| — | `quantity` | **1** |
| `Price` | `unit_price` | Harga baris |
| `Discount` | `discount_amount` | Diskon baris |
| `Grand Total` | `subtotal` | Net setelah diskon |
| Nama asli (jika alias) | `notes` | `Imported: {nama Excel}` |

### `transaction.sales_order_payments`

| Kolom Excel | Field DB | Transformasi |
|-------------|----------|----------------|
| — | `sales_order_id` | FK order |
| `Pembayaran` | `method_payment_id` | Lihat mapping |
| `Grand Total` | `amount` | Total dibayar |
| — | `change_amount` | **0** |
| — | `status` | **`completed`** |
| `Pembayaran` | `notes` | String mentah |

Format `Pembayaran`:

```text
28 October 2024 17:34 - OVO - 19000.0000,
```

Regex: `{tanggal} - {metode} - {jumlah}`

---

## Mapping metode pembayaran

| Excel | `method_payments.code` | Keterangan |
|--------|-------------------------|------------|
| OVO | **QRIS** | OVO masuk kategori QRIS di seeder |
| Debit Card | **TRANSFER** | Kartu debit ≈ non-tunai bank |
| Cash | **CASH** | Tunai |
| Online Order | **EWALLET** | Pesanan online |

Lookup: `master_data.method_payments` per `branch_id` + `code`.

---

## Mapping produk (29 nama Excel)

### Match langsung ke menu `product_wwwcoffee_menu.json`

Chocolate (Hot), Croisant Reguler (Chocolate), Custom, Espresso, Filter Coffee (Hot), Hot Caffe Latte, Hot Cappucino, Hot Chocoloate, Hot Long Black, Ice Lemon Tea, Ice Manual Brew (Japanese), Iced Apple Black, Iced Bold, Iced Light, Iced Light 1 Liter, Latte (Hot), Light & Sweet event, Magic (Hot), Matcha (Hot), Matcha Premium, Sea Salt Coffee Milk

### Alias (nama legacy / typo Excel)

| Nama di Excel | Produk di sistem | Varian |
|---------------|------------------|--------|
| Ice Bold 1 Liter | Any Menu 1 Liter | (default) |
| Iced Cappucino (Hot) | Cappucino | Hot |
| Iced Cappucino (Ice) | Cappucino | Ice |
| Iced Long Black (Hot) | Hot Long Black | (default) — **konfirmasi bisnis** |
| Iced Long Black (Ice) | Hot Long Black | (default) — **konfirmasi bisnis** |
| Orange Americano | Iced Orange Black | (default) |
| Espresso on the rock | On The Rock | (default) |
| Iced Mochaccino (Ice) | Mochaccino | Ice |

### Produk `Custom` (27 transaksi)

Tetap map ke produk **Custom**; harga dari kolom `Price` / `Grand Total` (bukan harga master 0).

### Resolusi varian

1. Coba kunci penuh: `{nama produk}` lowercase, mis. `latte (hot)`
2. Parse `Nama (Hot|Ice|…)` → produk + atribut varian
3. Cek tabel alias di atas
4. Fallback: produk **Custom** + `notes`: `Imported: {nama}`

---

## Alur impor

```bash
# Prasyarat
php artisan db:seed --class=BusinessUnitSeeder
php artisan db:seed --class=MethodPaymentSeeder
php artisan db:seed --class=ProductPriceListSeeder
php artisan db:seed --class=ProductMenuSeeder

# Impor historis (idempotent: skip jika sales_number sudah ada)
php artisan db:seed --class=TransactionHistorySeeder
```

---

## Batasan & rekomendasi

1. **Satu baris = satu order** — tidak ada multi-item di file ini.
2. **Cabang** — Excel tidak punya kolom cabang; default Bandung. Ubah konstanta di seeder jika data multi-cabang.
3. **Tanggal bayar vs tanggal transaksi** — OVO sering dibayar hari lain (lihat kolom `Pembayaran`); `paid_at` memakai tanggal bayar jika ter-parse.
4. **Iced Long Black** — tidak ada di menu baru; dipetakan ke Hot Long Black sementara — validasi dengan tim operasional.
5. **Stok** — produk menu `is_stock_item=false`; tidak ada mutasi stok saat impor.
