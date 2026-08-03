# Dashboard period filter

## Goal
Default periode = tanggal 1 bulan berjalan → hari ini; Filter modal; hilangkan tanggal acak Jan dari flatpickr.

## Done
- [x] Controller default `monthStart` → `today` via `date_from`/`date_to`
- [x] Filter modal + badge di page header
- [x] Fix flatpickr: jangan prefill value dengan em dash; `setDate` dari data-attr server
- [x] Reset → URL bersih `/dashboard` (buang query date lama)

## Verify
- Buka `/dashboard` **tanpa** `?date_from=&date_to=` → badge `01 Aug 2026 — 03 Aug 2026`
- Jika masih Jan: klik **Reset** sekali (URL masih menyimpan query lama)
- Modal periode harus match badge (separator ` — `)

## Review
Root cause: Apply setelah flatpickr salah-parse menulis `date_from=2026-01-20&date_to=2026-01-24` ke URL; server lalu menampilkan itu dengan benar sesuai query.
