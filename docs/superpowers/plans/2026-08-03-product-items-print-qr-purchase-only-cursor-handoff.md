# Cursor Handoff — Print QR di Product > Items hanya untuk item Purchase (non-produksi)

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-08-03-product-items-print-qr-purchase-only-cursor-handoff.md".
> Aturan permanen di `.cursorrules`. SCOPE kecil: batasi action "Print Barcode"/QR di daftar Items agar **hanya muncul untuk item `procurement_type = purchase`**. Tak mengubah halaman/logic cetak QR yang sudah ada, tak menyentuh flow produksi.

## Konteks

- Daftar Items = `resources/views/admin/product/master/index.blade.php` (DataTables, baris aksi dibangun via JS).
- Sudah ADA action cetak di baris ~327 — **muncul untuk SEMUA item** (hanya di-gate izin read):
  ```js
  if (@json($hasReadPermission)) html += '<li><a class="dropdown-item" href="{{ url("product/items") }}/'+r.id+'/print-barcode"><i class="ti ti-printer me-2 text-info"></i>Print Barcode</a></li>';
  ```
- Halaman cetak (`product.print-barcode.*` + `ProductQrCodeService`) sudah jadi & juga dipakai production receiving (`admin/production/receive-print.blade.php`). **Jangan diubah.**
- `PROCUREMENT_TYPE` (parameter, `public.parameter_details`) nilainya: `purchase`, `produce`, `both`, `none`.
- **Keputusan:** action ini hanya untuk **`purchase`**. Item `produce`/`both`/`none` TIDAK menampilkannya (item produksi sudah mencetak QR di langkah production receiving).

## Langkah 1 — Expose `procurement_type_key` ke baris DataTables

Di `app/Http/Controllers/Admin/ProductController.php`, datatable index sudah eager-load `procurementType:id,value,key` (sekitar baris 169) & punya `addColumn('procurement_type_name', ...)` (~267). **Tambahkan** kolom key agar JS bisa mengecek:
```php
->addColumn('procurement_type_key', fn ($row) => $row->procurementType?->key)
```
(String biasa, tak perlu masuk `rawColumns`.) Pastikan kolom ini ikut terkirim di JSON response datatable. Verifikasi `php -l app/Http/Controllers/Admin/ProductController.php`.

## Langkah 2 — Gate action cetak di view (hanya purchase)

Di `resources/views/admin/product/master/index.blade.php` (~baris 327), bungkus baris action cetak dengan syarat `procurement_type_key === 'purchase'`:
```js
if (@json($hasReadPermission) && r.procurement_type_key === 'purchase') {
    html += '<li><a class="dropdown-item" href="{{ url("product/items") }}/'+r.id+'/print-barcode"><i class="ti ti-printer me-2 text-info"></i>Print QR</a></li>';
}
```
- Label boleh diganti "Print QR" (sesuai istilah yang dipakai) — opsional; boleh tetap "Print Barcode" bila ingin konsisten. Utamakan yang penting: **syarat purchase**.
- Bila DataTables meng-serialize key dengan nama lain, samakan (`r.procurement_type_key`). Jangan pakai `procurement_type_name` (itu label terlokalisasi, rapuh).

## Langkah 3 — (Opsional) Amankan endpoint

Halaman `product.print-barcode.view` masih bisa diakses via URL langsung untuk item non-purchase. Bila ingin konsisten (BUKAN wajib untuk task ini), boleh tambахkan guard di `ProductController::printBarcodeView()`: `abort_unless(optional($product->procurementType)->key === 'purchase', 404)`. **Konfirmasi dulu bila ragu** — karena halaman ini juga dipakai production receiving lewat `product_id`; pastикан guard tidak memblok jalur produksi (jalur produksi mengaksesnya dari order produksi, bukan dari item purchase). Bila ada risiko regresi ke produksi, **lewati langkah ini** dan cukup gating di UI (Langkah 2).

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Admin/ProductController.php
php artisan view:cache && php artisan view:clear
```
Smoke manual (login admin, Product > Items):
- Item `procurement_type = Purchase` → dropdown aksi menampilkan "Print QR" → membuka halaman cetak seperti biasa.
- Item `Produce`/`Both`/`None` → aksi cetak TIDAK muncul.
- Halaman print-barcode & production receiving tetap normal.

## Checklist

- [ ] `procurement_type_key` di-expose di datatable index Product.
- [ ] Action cetak di Items hanya render saat `procurement_type_key === 'purchase'` (+ izin read).
- [ ] (Opsional) guard endpoint tidak memblok jalur production receiving — atau dilewati.
- [ ] Halaman cetak QR & flow produksi tak berubah; view:cache bersih.
