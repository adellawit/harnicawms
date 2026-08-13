# Agent Cutting Price ↔ Config Alignment (Option 1)

## Checklist
- [x] Diagnose: config BOX vs SO KARTON — unit exact match failed
- [x] Implement MAP + unit conversion (recursive conversion paths)
- [x] Update detail UI + export columns
- [x] Verify TRX-120826-0001: factor 300, net_map 3000, MAP 229000, detected cutting

## Review
- Floor tetap `map_price` dari `/partner-network/cutting-price-config`
- Harga jual dikonversi ke unit config sebelum dibanding
- Gap amount = (MAP − net_per_map_unit) × (qty × factor)
