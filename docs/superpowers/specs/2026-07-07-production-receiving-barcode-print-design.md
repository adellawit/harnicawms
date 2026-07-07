# Production Receiving: Actual-Qty Unit Picker + Barcode Print Handoff

Date: 2026-07-07
Status: Approved (design), not yet implemented

## Background

Today, the Production Order Receiving page (`resources/views/admin/production/receive.blade.php`) has a
single numeric "Qty Aktual" input with no unit selector — the value is implicitly interpreted in the
order's `output_unit_id` (the BOM's base unit, e.g. karton). This breaks down in a real scenario: if
production was planned for 1 karton but the floor only actually produced 25 pack (less than a full
karton), staff have to manually compute `25 / 30 = 0.8333...` karton and type that decimal — unintuitive
and error-prone.

Separately, there is no way to print physical barcodes for the units received after a production run.
An existing, fully-built barcode printing feature already exists on the Product Items page
(`/product/items/{id}/print-barcode`), including a hierarchy engine
(`Product::getBarcodeQuantityBreakdown()`) that expands a quantity at one unit level down through the
product's full conversion chain (e.g. 1 karton → 30 pack → 300 box), a per-level serial allocator
(`ProductLabelSerialService`), and PDF rendering. This feature is not wired into the production flow at
all today — a user has to separately navigate to Product Items and manually re-enter the same quantity/unit
that was just received in Production Order.

## Goals

- Let staff enter the actual received quantity in whichever unit matches what was physically counted
  (karton, pack, box, sachet, etc.), not just the order's base unit.
- After a successful Receiving submission, take the user straight into the existing barcode print flow,
  pre-filled with the exact product/variant/quantity/unit that was just received, so they can review the
  breakdown and print without re-entering anything.
- Reuse the existing barcode engine as-is — no changes to `getBarcodeQuantityBreakdown()`, serial
  allocation, QR generation, or PDF rendering logic.

## Non-goals (explicitly deferred)

- Removing or relocating the existing "Print Barcode" action on `/product/items` — it stays as a general
  manual/reprint utility. No PM directive to remove it yet.
- Supporting mixed/multi-line actual-qty entry (e.g. "20 pack + 5 box" in one submission). A single
  qty + single unit picker is sufficient for the reported scenario.
- Changing the `Product,is_read` permission requirement on the print-barcode routes to also accept
  `Production Order` permission. Assumed for now that staff who receive production also have Product
  module access; this can be granted via the Settings role UI if it turns out not to hold.

## Design

### 1. Receiving form: unit picker for actual qty

`resources/views/admin/production/receive.blade.php` gets a unit `<select>` next to the "Qty Aktual"
input, populated from the finished-good product's own conversion chain (the same unit-options helper
already used by the create form, e.g. `ProductionSimulationService::unitOptions()`), defaulting to the
order's `output_unit_id`.

On submit, `ProductionOrderController::receive()` converts the entered `(actual_qty, actual_unit_id)` pair
to the order's `output_unit_id` using the existing `ProductionQuantityNormalizer::toBomOutputUnit()` /
`Product::convertQuantity()` path (the same conversion machinery just fixed for precision) before calling
`ProductionService::receive($order, $actualQtyInOutputUnit, $userId)` — that service method's signature and
internal logic do not change. The Rencana/Aktual/Sisa material variance table (already built) is
unaffected in its calculation, since it already operates on the order's output unit internally.

### 2. Post-receive redirect to barcode printing

On successful receive, instead of redirecting to `production.show`, redirect to
`route('product.print-barcode.view', $order->product_id)` with query parameters:
- `variant_id` = `$order->product_variant_id`
- `unit_id` = the unit the staff actually typed the quantity in (not the converted output unit — see
  §3 for why)
- `quantity` = the quantity the staff typed (in that same unit)
- `print_mode` = `hierarchy`

A flash success message still confirms the production order was received; the barcode print page is a
separate, subsequent screen the user reviews before printing (same two-step preview → PDF flow the
feature already has).

### 3. Barcode breakdown starts at the unit actually received, not the base unit

The hierarchy breakdown must start from whatever unit the staff reported, because that's the physical
reality of what's on the floor. Example: if the order was for 1 karton but only 25 pack came out, the
printed labels are 25 pack-level labels + 250 box-level labels (25 × 10 boxes/pack) — there is no
karton-level label, because no complete karton exists. This falls out naturally from passing
`(unit_id=pack, quantity=25)` as the starting point to the unchanged `getBarcodeQuantityBreakdown()`
engine; no changes to that engine are needed.

### 4. Pre-fill support on the existing print-barcode page

`ProductController::printBarcodeView()` and `resources/views/admin/product/master/print-barcode.blade.php`
currently have no mechanism to pre-fill variant/unit/quantity/mode from the URL — the view always defaults
to the product's own `default_unit_id` and `old('quantity', 1)`. This needs a small, additive change:
`printBarcodeView()` reads optional `variant_id`, `unit_id`, `quantity`, `print_mode` query parameters and
passes them to the view as pre-fill defaults (falling back to today's behavior — product's default unit,
qty 1, no variant, single mode — when the query params are absent, so manual access from `/product/items`
is unaffected). The view's variant/unit `<select>` and quantity input use these passed-in defaults via
`old(..., $default)`.

### 5. Permission

No change. Both `production.receive.store` and `product.print-barcode.view` keep their current
permission gates (`Production Order,is_update` and `Product,is_read` respectively). A user without
`Product,is_read` who completes a Receiving will be redirected to a page they can't view — accepted for
now per the Non-goals section; revisit only if this proves to be a real access problem in practice.

## Edge Cases

- Staff enters a unit/qty combination that converts to an `output_unit_id` value of 0 or negative:
  already rejected by `ProductionService::receive()`'s existing `$actualQty <= 0` guard — no new
  validation needed beyond what conversion already produces.
- The finished-good product has no conversion chain at all (single-unit product, e.g. always sold as
  "pcs"): the unit picker shows only that one unit, functionally identical to today's behavior (no
  picker meaningfully needed, but harmless to show a single-option dropdown).
