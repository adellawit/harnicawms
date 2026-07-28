# Theme Surface Colors — Design Spec

**Date:** 2026-07-28  
**Route:** `/settings/theme-configuration`  
**Status:** Approved

## Summary

Extend Appearance & Theme settings so admins can optionally override **navbar**, **sidebar**, and **page background** colors on the admin app layout. Defaults remain the existing Vuexy/template surfaces unless an override is enabled. Text and icon colors on navbar/sidebar are derived automatically from background luminance.

## Goals

- Allow optional per-surface color overrides (navbar, sidebar, background)
- Keep primary/secondary branding flow unchanged
- Preserve readability via automatic contrast for navbar/sidebar text/icons
- Apply only to the admin `app` layout (not customer shop / agent order)
- Remain compatible with existing glassmorphism toggle

## Non-Goals

- Manual text/icon color pickers
- Customer shop or agent-order layout theming
- Preset-only themes without free color pickers
- Dark-mode system redesign

## Decisions

| Topic | Decision |
|---|---|
| Control model | Optional override (null = default; hex = override) |
| Text/icon contrast | Automatic from luminance |
| Scope | Admin app layout only |
| Storage | Separate nullable columns on `configuration.app_theme_settings` |

## Data Model

Add three nullable columns to `configuration.app_theme_settings`:

| Column | Type | Meaning |
|---|---|---|
| `navbar_color` | `string(7)`, nullable | Hex `#RRGGBB`; `null` = template default |
| `sidebar_color` | `string(7)`, nullable | Hex `#RRGGBB`; `null` = template default |
| `background_color` | `string(7)`, nullable | Hex `#RRGGBB`; `null` = template default |

Rules:

- Hex values are normalized to uppercase (same as `primary_color` / `secondary_color`)
- Override switch off on the form always persists `null` (ignore hidden picker value)
- Override switch on requires a valid hex

## Behavior

1. When a surface column is `null`, no custom CSS variable override is applied for that surface; Vuexy defaults remain.
2. When a surface column has a hex value, inject CSS custom properties and map them in `theme-bridge.css` to:
   - Navbar: `#layout-navbar` / `.bg-navbar-theme`
   - Sidebar: `#layout-menu` / `.bg-menu-theme`
   - Background: `.layout-page` (admin content area)
3. Navbar and sidebar foreground (text/icons) are computed from luminance of the effective background:
   - Light background → dark ink
   - Dark background → light ink
4. Glass enabled: override colors apply as tinted glass surfaces (retain blur/transparency), not fully opaque blocks that kill the glass effect.
5. Customer and agent shop layouts are unchanged.

## UI

On `/settings/theme-configuration`, add a **Warna Surface** card below **Warna Tema**:

- Navbar — Override switch + color picker (picker enabled only when override is on)
- Sidebar — Override switch + color picker
- Background — Override switch + color picker

Right-hand preview: extend with a mini layout mock (navbar strip, sidebar column, content area) so all three colors are visible before save.

Existing cards (Warna Tema, Logo & Favicon, Efek Tampilan) stay as they are.

## CSS Tokens

`AppThemeService::viewData()` exposes tokens consumed by `theme-vars.blade.php`:

| Token | Source |
|---|---|
| `--brand-navbar-bg` | `navbar_color` when set |
| `--brand-navbar-color` | auto contrast from navbar bg |
| `--brand-sidebar-bg` | `sidebar_color` when set |
| `--brand-sidebar-color` | auto contrast from sidebar bg |
| `--brand-page-bg` | `background_color` when set |

`theme-bridge.css` maps these tokens to admin layout selectors listed above. Unset tokens fall back to current template styles.

## Validation & Errors

`ThemeConfigurationController@update`:

- `navbar_color`, `sidebar_color`, `background_color`: nullable; when present must match `^#[0-9A-Fa-f]{6}$`
- Override off → force `null` for that field
- Override on with empty/invalid value → validation error
- On success: clear theme cache (existing `AppThemeService` behavior) and flash success

## Architecture Flow

```
Form (override switches + pickers)
  → ThemeConfigurationController (validate + nullify)
  → AppThemeService::update (persist + cache forget)
  → AppThemeService::viewData (hex + luminance contrast)
  → theme-vars.blade.php (CSS custom properties)
  → theme-bridge.css (admin layout selectors)
```

## Manual Test Plan

1. All overrides off → admin navbar/sidebar/background look like current defaults
2. Enable navbar override, save → navbar color updates after reload
3. Enable sidebar + background overrides → both apply on admin layout
4. Turn overrides off and save → surfaces return to defaults
5. Dark surface color → light text/icons; light surface → dark text/icons
6. Glass on + surface overrides → glass still visible (tinted, not solid-dead)
7. Customer shop / agent order pages unchanged

## Files Touched (Expected)

- Migration: add three columns to `configuration.app_theme_settings`
- `app/Models/Configuration/AppThemeSetting.php` — fillable
- `app/Services/Theme/AppThemeService.php` — viewData + contrast helper
- `app/Http/Controllers/Admin/ThemeConfigurationController.php` — validate/nullify
- `resources/views/admin/settings/theme-configuration/index.blade.php` — UI
- `resources/views/layouts/partials/theme-vars.blade.php` — CSS vars
- `public/assets/css/theme-bridge.css` — surface mappings
- `public/assets/js/theme-settings.js` — picker enable/disable + preview
