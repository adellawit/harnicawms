# Task: PO insert status locked to Draft

## Checklist
- [x] Kunci status create ke Draft (`PurchaseOrderStatus::resolveOnCreate()`)
- [x] Form insert: badge Draft, bukan dropdown
- [x] Form edit: status read-only (tidak bisa loncat via edit)
- [x] `insertData` ignore `status_id`; `editData` tidak update status
- [x] `php -l` + `view:cache`

## Review
- Create selalu `draft`. Receiving/Payment tidak bisa dipilih di form.
- Naik status tetap lewat index **Submit** (`validateManualUpdate` → process).
- Invoice boleh DP/lunas untuk PO Process yang belum receive.
- Tidak ada PHPUnit di repo; verifikasi compile view + lint.

---

# Previous: Promotion Marketing Multi-Target

Handoff: `docs/superpowers/plans/2026-08-12-promotion-marketing-multi-target-cursor-handoff.md`

Branch: `feature/promotion-multi-target`

## Checklist
- [x] L1 — Migrasi pivot + migrasi data + drop kolom single
- [x] L2 — Model Promotion belongsToMany + fillable
- [x] L3 — Admin form multi-select + scripts + edit selected ids
- [x] L4 — Controller validasi array + sync pivot
- [x] L5 — assertMarketingTargetMatches multi
- [x] L6 — POS payload + blade data-* + agent-pos.js
- [x] Fix admin index yang masih pakai targetAgent/targetReseller
- [x] Verifikasi migrate / php -l / view:cache

## Review
- Migrasi `2026_08_12_000010` DONE; kolom `target_agent_id`/`target_reseller_id` sudah drop.
- Data lama: 1 baris pivot reseller (PRM-202608-0003 → Armansyah) ter-load via `targetResellers`.
- `php -l` + `view:cache`/`view:clear` bersih.
- Extra: admin index menampilkan multi-nama (truncate +N).
- Belum commit (tunggu permintaan user).
- Smoke UI admin/POS masih manual di browser.
