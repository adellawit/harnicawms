# Agent Cutting Price Report — Design

Date: 2026-07-22  
Status: Approved — approach A (on-the-fly vs current REGULER list)

## Goal

Distributor melihat Agent mana yang menjual di bawah harga jual katalog Distributor (Price List **REGULER**), termasuk penjualan Reseller di bawah Agent.

## Rule

- Scope: `sales_orders.payment_status = paid`, item bukan promo free, qty > 0
- Agent mapping: customer Agent langsung, atau Reseller → `agent_id`
- `agent_unit_price` = `sales_order_items.unit_price`
- `agent_net_price` = `subtotal / quantity`
- `distributor_price` = `product_variant_prices.selling_price` untuk variant+unit pada price list `REGULER` (harga master **saat ini**)
- Cutting jika `agent_net_price < distributor_price`

## UI

- Menu: Reporting → Sales & Transaction → Agent Cutting Price
- Filter: date range, agent, product, min % under
- Summary per Agent + drill-down detail + Excel export

## Out of scope

- Historical REGULER snapshot on POS
- Auto-block / notifications
