# Restore nested Training + Marketing menus

## Root cause
Request sebelumnya dibaca terbalik: "tolong ganti bagian menu 11/12" = **ganti yang ini**,
bukan target. Target = nested order 6/7 dari "ini menu terbaru".

## Target
```
6 Training Academy (training/academy)
  1 Course (training/courses)
  2 Academy (academy)
  3 Pengaturan Academy (training/settings)
7 Marketing Center (—)
  1 Marketing Category (marketing/categories)
  2 Marketing Assets (marketing/assets)
```

## Checklist
- [x] Migrasi restore nested + shift CRM+ order
- [x] Update TrainingAccessSeeder
- [x] Update MarketingAccessSeeder (create, bukan delete)
- [x] Grant IAM admin/marketing/agent
- [x] Verify DB tree
- [x] lessons.md

## Review
- Migrasi: `2026_08_04_000021_restore_nested_training_marketing_menus`
- Flat 11/12 Training/Pengaturan sudah diganti → nested 6/7
- **Relogin** wajib agar session sidebar refresh
