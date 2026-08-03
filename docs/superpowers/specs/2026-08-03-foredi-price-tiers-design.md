# Foredi Price Tiers — Design

**Date:** 2026-08-03  
**Status:** Approved for planning  
**Scope:** Master konfigurasi harga tier kategori FOREDI + seeder awal  
**Out of scope:** Auto-apply ke POS / Agent Order / Replenishment, UI admin CRUD, perubahan `product_price_lists`

## Goal

Menyimpan struktur harga partner Foredi (dari catatan operasional) sebagai konfigurasi terpisah — **tidak** berpacu pada price list POS (`REGULER` dkk).

## Pricing (source of truth)

Unit: **BOX**. Produk target: **FOREDI-FG** (kategori `FOREDI`).

| Level | Code (`level`) | Min qty (box) | Harga / box (IDR) |
|---|---|---|---|
| Harga Resmi (H.K. Resmi) | `RESMI` | `null` | 249.000 |
| Minimum Advertised | `MAP` | `null` | 229.000 |
| Reseller | `RESELLER` | 30 | 180.000 |
| Reseller | `RESELLER` | 60 | 175.000 |
| Reseller | `RESELLER` | 120 | 170.000 |
| Agen | `AGEN` | 600 | 160.000 |

Catatan: “Cutting price” di catatan operasional merujuk ke konteks MAP sebagai floor iklan; fase ini hanya menyimpan angka, tidak mengikat laporan Cutting Price.

## Architecture

### Table: `partner.foredi_price_tiers`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | `uuid_generate_v7()` |
| `category_id` | uuid FK → `product.product_categories` | kategori FOREDI |
| `product_id` | uuid FK → `product.products` | FOREDI-FG |
| `level` | string(30) | `RESMI` \| `MAP` \| `RESELLER` \| `AGEN` |
| `min_qty` | decimal(18,4) nullable | null untuk RESMI/MAP |
| `unit_code` | string(20) | default `BOX` |
| `price` | decimal(18,4) | harga per unit |
| `sort_order` | int | urutan tampil |
| `is_active` | boolean | default true |
| `created_by` / `updated_by` / `deleted_by` | uuid nullable | audit |
| timestamps + soft deletes | | |

**Unique indexes (PostgreSQL NULL-safe):**

1. Partial: `(product_id, level) WHERE min_qty IS NULL AND deleted_at IS NULL` — satu RESMI dan satu MAP per produk.
2. Partial: `(product_id, level, min_qty) WHERE min_qty IS NOT NULL AND deleted_at IS NULL` — tier RESELLER/AGEN tidak dobel.

Indexes biasa: `category_id`, `product_id`, `level`, `is_active`, `sort_order`.

### Model

`App\Models\Partner\ForediPriceTier`

- Connection `pgsql`, table `partner.foredi_price_tiers`
- Relations: `category()`, `product()`
- SoftDeletes + HasUuids

### Seeder

`Database\Seeders\ForediPriceTierSeeder`

1. Resolve kategori `code = FOREDI` dan produk `code = FOREDI-FG`.
2. Jika salah satu hilang → error message + return (jangan throw agar `db:seed` lain tetap jalan).
3. Upsert 6 baris di atas (idempotent via `updateOrCreate` pada `product_id + level + min_qty`).
4. Didaftarkan di `DatabaseSeeder` **setelah** `ForediProductSeeder`.

### Explicit non-goals (fase ini)

- Tidak menambah/mengubah row di `product.product_price_lists`
- Tidak mengubah harga `REGULER` / `product_prices` / `product_variant_prices`
- Tidak ada service resolver qty → price
- Tidak ada menu/permission admin

## Error handling

| Kondisi | Perilaku |
|---|---|
| Kategori FOREDI / produk FOREDI-FG belum ada | Seeder skip + log error |
| Re-run seeder | Update harga/sort/is_active; tidak duplikat |
| Level invalid | Tidak di-seed (hanya 4 level yang diizinkan di seeder) |

## Verification

1. `php artisan migrate` → tabel `partner.foredi_price_tiers` ada  
2. `php artisan db:seed --class=ForediPriceTierSeeder` → 6 row aktif untuk FOREDI-FG  
3. Query cek:

```sql
SELECT level, min_qty, price, unit_code, sort_order
FROM partner.foredi_price_tiers
WHERE deleted_at IS NULL
ORDER BY sort_order;
```

## Follow-up (bukan fase ini)

- Resolver: role + qty → tier price
- Wiring Agent Order / POS partner
- Admin CRUD + permission
- Optional link ke laporan Agent Cutting Price (MAP sebagai floor)
