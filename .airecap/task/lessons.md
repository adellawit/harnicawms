# Lessons

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
