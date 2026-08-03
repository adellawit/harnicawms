# Cutting Price Config — Design

**Date:** 2026-08-03  
**Status:** Approved  
**Scope:** Master konfigurasi cutting price (official + MAP) untuk FOREDI-FG + seeder  
**Out of scope (awal):** —  
**Implemented also:** menu CRUD admin + report wiring ke `map_price` (bukan price list)

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

## Admin UI

- Menu: Customer → Network → **Cutting Price Config**
- Route: `/partner-network/cutting-price-config`
- Permission name: `Cutting Price Config`

## Report wiring

Agent Cutting Price membandingkan `agent_net_price` vs `partner.cutting_price_configs.map_price` (unit match code/symbol/name). **Tidak** join `product_price_lists` / REGULER.
