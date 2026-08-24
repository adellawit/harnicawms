# Lessons

## 2026-08-24 — Promotion create: “tidak pindah halaman” / data kosong

- **Symptom:** Save marketing promo → tetap di create ATAU pindah tapi detail kosong; list tidak bertambah.
- **Root cause (2):** (1) Select2 tidak ikut sync `disabled` saat ganti tipe → field `target_type`/syarat tidak terkirim → validasi gagal → redirect back (seolah tidak pindah). (2) `show.blade.php` hanya render blok product (Buy/Get) → marketing tampak kosong meski tersimpan.
- **Fix pattern:** Enable/disable Select2 via `$(el).prop('disabled', …).trigger('change.select2')`; re-toggle sebelum submit; show page branch `promotion_type === 'marketing'`.
- **Verify:** Save marketing → redirect ke show dengan Target/Syarat/Diskon; baris baru di index.

## 2026-08-24 — Promotion create 500: `exists:partner.agents`

- **Symptom:** Create marketing promo (pilih agen/reseller) → HTTP 500 `Database connection [partner] not configured`.
- **Root cause:** Rule string `exists:partner.agents,id` di-parse Laravel sebagai **connection** `partner` + table `agents`. Ada connection `product`/`transaction` di `config/database.php`, tapi **tidak ada** `partner`.
- **Fix pattern:** Pakai `Rule::exists(Agent::class, 'id')` / `Rule::exists(Reseller::class, 'id')`, atau `Rule::exists('pgsql.partner.agents', 'id')`. Jangan `exists:partner.*`.
- **Verify:** Validator dengan 2 agent UUID lolos; sync pivot OK.

## 2026-08-14 — Agent-order catalog shows Sachet price (Rp 750) not Karton (Rp 900.000)

- **Symptom:** `/agent-order` card Foredi (Barang Jadi) tampil Rp 750; harga distributor Karton Rp 900.000.
- **Root cause:** `min(selling_price)` di semua satuan. Seed FG: 1 Karton = 1200 Sachet → 900000/1200 = 750. Add-to-cart sudah pakai `default_unit_id` (Karton).
- **Fix pattern:** Harga katalog = `min(selling_price)` **hanya** di `product.default_unit_id`, jangan min lintas Sachet/Pack/Karton.
- **Verify:** `ProductVariantPrice::minCatalogSellingPrice(FOREDI-FG)` → 900000, bukan 750.

## 2026-08-14 — Agent-order "Varian tidak tersedia" padahal stok distributor ada

- **Symptom:** Modal Foredi (Barang Jadi) "Varian tidak tersedia" sementara stok FG ada.
- **Root cause:** Stok tercatat **600 BOX** di `SUHARA-BDG-WH-PRD`. Modal/cart lookup `unit_id = KARTON` → 0 baris → di-skip. 1 Karton = 300 Box → 600 Box = 2 Karton.
- **Fix pattern:** Pakai `StockAvailabilityService::availableQuantity()` (konversi satuan), jangan exact-match `unit_id` stok vs satuan jual.
- **Verify:** availableQuantity(FG, Karton, sales WH) = 2; modal tampil varian + stok 2 Karton.

## 2026-08-04 — Menu "ganti bagian X" vs "menu terbaru"

- **Symptom:** User bilang 11/12 Training/Pengaturan "masih belum ganti".
- **Root cause:** Salah baca intent. "ini menu terbaru" = target; "tolong ganti bagian menu" + list 11/12 = yang harus diganti (bukan target).
- **Fix pattern:** Kalau ada dua blok menu di request — blok "terbaru/seharusnya" vs blok "yang ada sekarang / ganti ini" — konfirmasi arah transformasi sebelum migrate. Nested parent sidebar butuh `has_page=true` atau children tidak tampil.

## 2026-08-03 — Dashboard period flatpickr shows wrong dates (e.g. Jan)

- **Symptom:** Badge/modal shows `20 Jan 2026 to 24 Jan 2026` instead of `01 Aug — 03 Aug`.
- **Root cause:** Prefilling flatpickr input with `01 Aug 2026 — 03 Aug 2026` (em dash) while locale expects ` to `; flatpickr mis-parses, then Apply writes bad `date_from`/`date_to` into the URL permanently.
- **Fix pattern:** Leave input `value` empty; init with `defaultDate`/`setDate` from server `Y-m-d` data attributes; set `locale.rangeSeparator`; Reset → clean `/dashboard` (no date query). Stale `date_from`/`date_to` without `period_custom=1` → server redirect scrub ke default bulan ini.
- **Verify:** Open `/dashboard` (even with old Jan query) → redirect → badge `01 Aug 2026 — 03 Aug 2026`.

## 2026-07-15 — Login 500 / schema migrations

- **Symptom:** Login HTTP 500 with `relation "auth.users" does not exist`.
- **Root cause:** `AppServiceProvider` was not registered in `bootstrap/app.php`, so `loadMigrationsFrom` never ran and schema migrations (`auth`, `human_resources`, …) stayed Pending even though schemas existed empty.
- **Secondary bug:** Once `AppServiceProvider` is loaded, `migrate:all` step 1 must use `--path=database/migrations` (root only). Calling bare `migrate` runs all `loadMigrationsFrom` paths out of dependency order (e.g. `customer` before `product`).
- **Fix pattern:** Register `AppServiceProvider` + keep `MigrateAllCommand` root step path-scoped; then `migrate:all` + `db:seed` for local empty DB.
- **Verify:** `Auth::attempt` + HTTP POST `/login` → 302 dashboard, not 500.

## 2026-07-15 — Local run “tidak bisa” after auth OK

- **Symptom:** Auth works server-side but browser local fails / redirects ke HTTPS.
- **Root cause:** `.env` lokal pakai `APP_URL=https://localhost:8000` + `SESSION_SECURE_COOKIE=true`. `AppServiceProvider` force HTTPS dari APP_URL; cookie `Secure` tidak tersimpan di `http://127.0.0.1:8000`.
- **Fix:** Local HTTP → `APP_URL=http://127.0.0.1:8000`, `SESSION_SECURE_COOKIE=false`, lalu `php artisan config:clear`.
- **Note:** DB remote (`192.168.10.250`) OK; credentials demo `demo@wit.id` / `demo2026*#`.
