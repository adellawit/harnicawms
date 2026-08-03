# Foredi Price Tiers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (inline; user requested implementasi langsung).

**Goal:** Tabel master `partner.foredi_price_tiers` + model + seeder 6 baris harga FOREDI-FG (terpisah dari price list).

**Architecture:** Konfigurasi partner-only di schema `partner`. Tidak menyentuh `product_price_lists`. Seeder idempotent setelah `ForediProductSeeder`.

**Tech Stack:** Laravel, PostgreSQL, Eloquent SoftDeletes/HasUuids

## Global Constraints

- Tidak ubah `product.product_price_lists` / harga REGULER
- Scope: kategori FOREDI + produk FOREDI-FG saja
- Unit: BOX; level: RESMI | MAP | RESELLER | AGEN
- No UI, no auto-apply, no permission menu

---

### Task 1: Migration

**Files:**
- Create: `database/migrations/partner/2026_08_03_000010_create_foredi_price_tiers_table.php`

- [x] Create table + FK + partial unique indexes per spec
- [x] `php artisan migrate --path=database/migrations/partner/2026_08_03_000010_create_foredi_price_tiers_table.php`

### Task 2: Model

**Files:**
- Create: `app/Models/Partner/ForediPriceTier.php`

- [x] Model fillable/casts/relations `category()`, `product()`

### Task 3: Seeder

**Files:**
- Create: `database/seeders/ForediPriceTierSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (call after `ForediProductSeeder`)

- [x] Upsert 6 tiers; ensure kategori FOREDI; skip jika FOREDI-FG missing
- [x] Handle `min_qty` null via whereNull (bukan `= null`)
- [x] `php artisan db:seed --class=ForediPriceTierSeeder`
- [x] Verify 6 rows (idempotent re-run OK)

### Task 4: Commit

- [x] Commit migration + model + seeder (conventional commit)
