# Production Order: Receiving Flow & Create-Form Redesign

Date: 2026-07-07
Status: Approved (design), not yet implemented

## Background

Today, completing a Production Order is a single, immediate action: creating a PO with the
"Langsung selesaikan produksi" checkbox checked (or clicking "Selesaikan Produksi" later) runs
`ProductionService::complete()`, which silently defaults `produced_qty` to `planned_qty` (there is
no input for actual output anywhere), deducts raw materials for that full planned quantity, and
adds the same quantity of finished goods to stock — all in one transaction.

This is wrong because real produced quantity often differs from planned quantity, and there is no
way to record or see that variance, nor any leftover raw material.

## Goals

- Separate "production work is physically done" from "goods have been received into warehouse
  stock". Stock must only move once actual output is known.
- Let the user enter only the actual produced quantity at receiving time; raw material consumption
  and leftover are calculated automatically (proportional to the BOM recipe), not entered manually
  per ingredient.
- Simplify the create form: no warehouse pickers, pick a finished-good product (searchable) instead
  of a BOM record.
- Add missing edit/delete for draft orders, and a grand-total column on the index.

## Non-goals (explicitly deferred)

- Auto-routing raw material consumption across multiple warehouses per ingredient based on where
  stock actually sits. A product/variant's stock is not tied to one fixed warehouse in this app
  (`ProductVariantStock` and `ProductCostLayer` are keyed by `(product_variant_id, warehouse_id)`,
  no default-warehouse column exists on products). This redesign uses a simple, single
  company-wide warehouse fallback per role (source vs output), same pattern as the BOM HPP fix.
  True per-ingredient multi-warehouse routing is a separate future task the user will break down
  themselves.
- Editing/re-running a completed (received) production order. Corrections are handled by creating
  a new Production Order for the delta quantity.

## Status Flow

| Status | Set by | Meaning | Stock movement |
|---|---|---|---|
| `draft` | Create PO (checkbox unchecked) | Not started | None |
| `in_progress` | User clicks "Mulai Produksi" | Being worked on the floor | None |
| `pending_receiving` | User clicks "Selesaikan Produksi", or create PO with checkbox checked (skips draft/in_progress) | Physical production finished, awaiting warehouse receiving | None |
| `completed` | User submits the Receiving form | Received into stock | Yes — final, locked |
| `cancelled` | User cancels (from draft/in_progress) | Abandoned | None |

`in_progress` currently exists in the badge map but is never set anywhere in the code — this design
is what activates it.

Transitions are one-directional, single-click actions with no extra form except Receiving.

## Create Form Redesign (`/production/create`)

- Remove the "Gudang Bahan Baku" and "Gudang Produk Jadi" `<select>` fields entirely. Resolve both
  warehouses server-side using the same fallback pattern as `BomController::resolveWipContext()`:
  - Source (raw material) warehouse: WIP → RAW_MATERIAL → FG → any company warehouse.
  - Output (finished goods) warehouse: FG → WIP → any company warehouse.
- Replace the "Resep (BOM)" `<select>` with a searchable product picker, reusing the existing
  `resources/views/admin/partials/product-variant-select2.blade.php` partial (Select2 + AJAX via
  `helper.product-variants`), filtered to `nature=FINISHED_GOOD`.
- On product selection, resolve the product's active BOM server-side
  (`BillOfMaterial::where('product_variant_id', $id)->where('is_active', true)->first()`):
  - No BOM found → show an inline warning "Produk ini belum punya resep (BOM). Buat resep dulu →"
    linking to `bom.create`, and block submission.
  - BOM found → the existing "Kebutuhan Bahan Baku" preview table appears automatically (same
    preview mechanism as today, keyed by the resolved BOM/product instead of a directly-chosen
    `bom_id`), checked against the fallback source warehouse's stock.
- Keep: Qty Produksi, Satuan, Overhead, Tanggal Produksi, Expired Produk Jadi, Catatan, and the
  "Simulasi Produksi" modal (same mechanics, data resolved via product instead of BOM select).
- The "Langsung selesaikan produksi" checkbox is relabeled "Tandai produksi langsung selesai
  (lewati draft, lanjut ke Receiving)". When checked, the created PO's status is `pending_receiving`
  instead of `draft` — no stock movement happens at creation regardless of this checkbox.

## Index Page (`/production`)

- Add a **Grand Total** column after HPP/Unit: `output_unit_cost × produced_qty (or planned_qty)`.
- Actions column:
  - Status `draft`: dropdown "⋮" menu (same visual pattern as the BOM index redesign) with
    **Lihat** (primary, eye icon), **Edit** (warning, pencil icon), **Hapus** (danger, trash icon,
    soft delete — `ProductionOrder` already has `SoftDeletes`).
  - Any other status: a single "Lihat" icon button, no dropdown (no edit/delete available once
    production has started).

## Detail Page (`/production/{id}`) Actions

Single-click, no intermediate form, except where noted:

- `draft` → "Mulai Produksi" button → `POST production.start` → status `in_progress`.
- `in_progress` → "Selesaikan Produksi" button → `POST production.finish` → status
  `pending_receiving`.
- `pending_receiving` → "Terima Hasil Produksi" button → links to the new Receiving page.
- `completed` → no action buttons; shows final materials/output summary.

## Receiving Page (new)

- `GET production.receive` — shows the order's product/BOM/planned qty (read-only) and a single
  input: **Qty Aktual Produksi**.
- Live preview table (recalculated client-side as the user types, purely for feedback — server
  recalculates authoritatively on submit): per BOM ingredient, shows **Qty Rencana** (BOM qty ×
  planned_qty), **Qty Aktual Terpakai** (BOM qty × actual_qty, proportional to the recipe), and
  **Sisa/Hemat** (Rencana − Aktual, informational only — this is a derived comparison, not a
  reservation being released, since nothing was deducted before this step).
- `POST production.receive.store` — server recalculates `scale = actual_qty / bom.output_quantity`
  from scratch (never trusts client numbers), runs `StockAvailabilityService::assertSufficient()`
  per ingredient against the actual (not planned) requirement, then:
  1. Deducts raw materials via `StockMutationService::outbound()` (FIFO), same as today's
     `ProductionService::complete()` body.
  2. Computes HPP = (total material cost + overhead) / actual_qty.
  3. Adds finished goods via `StockMutationService::inbound()` with that HPP and expiry (FEFO).
  4. Persists `ProductionOrderMaterial` rows including both `expected_qty` (new column, planned
     consumption for this ingredient) and `qty_consumed` (actual), so the variance is visible
     later on the show page.
  5. Persists `ProductionOrderOutput` and sets the order's status to `completed` and
     `produced_qty` to the actual value. This is final — the record cannot be edited or re-run
     afterward; a correction is a new Production Order for the delta quantity.
- The completed order's show page displays the materials table with three columns: Rencana /
  Aktual Terpakai / Sisa, sourced from the stored `expected_qty` vs `qty_consumed`.

## Data Model Changes

- New migration: add nullable `expected_qty` (`decimal(18,4)`) to
  `manufacturing.production_order_materials`, positioned near `qty_consumed`.
- No other schema changes. `ProductionOrder.produced_qty` already exists and is reused to store the
  final actual quantity recorded at receiving.

## Controller / Service Changes (implementation-level summary)

- `ProductionOrderController`:
  - `store()`: stop calling `ProductionService::complete()`. Set status to `pending_receiving`
    when the checkbox is checked, `draft` otherwise. No stock movement at creation.
  - Add `edit()`, `update()` — reuse the create form, prefill, only reachable/submittable while
    status is `draft`.
  - Add `destroy()` — soft delete, only while status is `draft`.
  - Add `start()` (`draft` → `in_progress`) and `finish()` (`in_progress` → `pending_receiving`) —
    plain status-transition actions, no stock movement.
  - Add `receiveView()` (GET) and `receive()` (POST) — replaces the old `complete()` action.
  - `create()`: resolve source/output warehouses via the new fallback helper instead of requiring
    user selection; resolve BOM from the selected product instead of a direct BOM pick.
- `ProductionService`: rename/refactor `complete()` into `receive(ProductionOrder $order, float
  $actualQty, ?string $userId)` — same body as today's `complete()`, except `$actualQty` is an
  explicit required parameter (no more silent fallback to `planned_qty`), and it also persists
  `expected_qty` per material (`item->quantity * plannedScale`) alongside the actual `qty_consumed`.
- Warehouse fallback: extract `BomController::resolveWipContext()`-equivalent logic into a shared
  helper (or duplicate the same pattern) so both BOM and Production Order controllers use
  consistent warehouse resolution.

## Edge Cases

- Insufficient raw material stock at receiving time (based on actual qty, which may exceed what
  planned qty would have needed): `StockAvailabilityService::assertSufficient()` throws, receiving
  form re-shows with an error, no partial stock movement (whole action is one DB transaction).
- Product selected in create form has no active BOM: submission blocked client- and server-side.
- User produces more than planned (actual_qty > planned_qty): handled naturally since consumption
  is calculated from actual_qty, not capped by planned_qty.
