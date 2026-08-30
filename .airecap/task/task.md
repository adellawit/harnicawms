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

# Previous: Agent Cutting Price ↔ Config Alignment (Option 1)

## Checklist
- [x] Diagnose: config BOX vs SO KARTON — unit exact match failed
- [x] Implement MAP + unit conversion (recursive conversion paths)
- [x] Update detail UI + export columns
- [x] Verify TRX-120826-0001: factor 300, net_map 3000, MAP 229000, detected cutting

## Review
- Floor tetap `map_price` dari `/partner-network/cutting-price-config`
- Harga jual dikonversi ke unit config sebelum dibanding
- Gap amount = (MAP − net_per_map_unit) × (qty × factor)
