# Login 500 Fix

## Problem
Login returns HTTP 500.

## Root Cause
- `SQLSTATE[42P01]: relation "auth.users" does not exist`
- `AppServiceProvider` was not registered in `bootstrap/app.php`, so schema migrations were never applied via normal migrate discovery
- Local DB `wit-pos` had empty `auth` schema and no HR/users data

## Plan
- [x] Register `AppServiceProvider` in `bootstrap/app.php`
- [x] Fix `MigrateAllCommand` root step to use `--path=database/migrations`
- [x] Run `php artisan migrate:all --force`
- [x] Run `php artisan db:seed --force`
- [x] Verify login (Auth::attempt + HTTP 302 → dashboard)

## Review
- Login fixed: auth against `demo@wit.id` / `demo2026*#` succeeds (302 → `/dashboard`).
- Code changes: `bootstrap/app.php`, `MigrateAllCommand.php`.
- DB: migrations + seed applied on local `wit-pos` (not a code-only fix).
