# Cutting Price Config — Design

**Date:** 2026-08-03  
**Status:** Approved  
**Scope:** Master konfigurasi cutting price (official + MAP) untuk FOREDI-FG + seeder  
**Out of scope:** Wiring report Agent Cutting Price, menu/UI admin, perubahan `foredi_price_tiers`

## Goal

Menyimpan H.K. Resmi dan Minimum Advertised Price (MAP) sebagai konfigurasi terpisah. Nanti report Agent Cutting Price (admin distributor) memakai **MAP** sebagai floor: penjualan agen di bawah MAP = melanggar.

## Pricing

| Field | Meaning | Value (FOREDI-FG / BOX) |
|---|---|---|
| `official_price` | H.K. Resmi | 249.000 |
| `map_price` | Minimum Advertised (floor report) | 229.000 |

## Table: `partner.cutting_price_configs`

- `id` uuid PK  
- `category_id` → `product.product_categories` (FOREDI)  
- `product_id` → `product.products` (FOREDI-FG)  
- `unit_code` string (default `BOX`)  
- `official_price` decimal(18,4)  
- `map_price` decimal(18,4)  
- `is_active`, `sort_order`, audit, soft deletes  

Unique (active): `(product_id, unit_code) WHERE deleted_at IS NULL`

## Model / Seeder

- Model: `App\Models\Partner\CuttingPriceConfig`
- Seeder: `CuttingPriceConfigSeeder` — ensure kategori FOREDI, upsert 1 row; skip jika FOREDI-FG hilang
- `DatabaseSeeder`: setelah `ForediProductSeeder`

## Follow-up

- Report: ganti pembanding REGULER → `map_price` dari config
- Menu CRUD admin distributor only
