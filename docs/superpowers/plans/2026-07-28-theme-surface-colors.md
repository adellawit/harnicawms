# Theme Surface Colors Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add optional navbar, sidebar, and page background color overrides on `/settings/theme-configuration` for the admin app layout.

**Architecture:** Nullable hex columns on `app_theme_settings`; `AppThemeService` computes CSS tokens + luminance contrast; `theme-vars` + `theme-bridge` apply colors to admin layout only.

**Tech Stack:** Laravel, Blade, CSS custom properties, vanilla JS (`theme-settings.js`)

## Global Constraints

- Scope: admin `app` layout only (not customer/agent shop)
- Override off always persists `null`
- Text/icon colors are automatic from luminance (no manual pickers)
- Glass toggle must remain visually compatible (tinted, not opaque-dead)
- Follow existing theme patterns (`primary_color` / `secondary_color`)

---

### Task 1: Migration + Model

**Files:**
- Create: `database/migrations/configuration/2026_07_28_000001_add_surface_colors_to_app_theme_settings.php`
- Modify: `app/Models/Configuration/AppThemeSetting.php`

**Interfaces:**
- Produces: fillable fields `navbar_color`, `sidebar_color`, `background_color` (nullable string hex)

- [ ] **Step 1: Create migration** adding three nullable `string(7)` columns
- [ ] **Step 2: Add columns to model `$fillable`**
- [ ] **Step 3: Run migration** `php artisan migrate --path=database/migrations/configuration/2026_07_28_000001_add_surface_colors_to_app_theme_settings.php`
- [ ] **Step 4: Commit**

---

### Task 2: AppThemeService tokens + contrast

**Files:**
- Modify: `app/Services/Theme/AppThemeService.php`
- Create: `scripts/theme-surface-contrast-test.php` (repo uses script tests)

**Interfaces:**
- Produces in `viewData()`:
  - `navbar_bg`, `navbar_color`, `navbar_rgb` (or nulls when unset)
  - `sidebar_bg`, `sidebar_color`, `sidebar_rgb`
  - `page_bg`, `page_bg_rgb`
- Produces: `contrastInk(string $hex): string` returning `#2F3A44` or `#FFFFFF` based on relative luminance

- [ ] **Step 1: Write script test for luminance contrast**
- [ ] **Step 2: Implement `contrastInk` + surface fields in `viewData` / defaults in `current()` create path unchanged for new cols (DB nullable)
- [ ] **Step 3: Run script test — expect PASS
- [ ] **Step 4: Commit**

---

### Task 3: Controller validation

**Files:**
- Modify: `app/Http/Controllers/Admin/ThemeConfigurationController.php`

**Interfaces:**
- Consumes: request fields `override_navbar`, `navbar_color`, `override_sidebar`, `sidebar_color`, `override_background`, `background_color`
- Produces: update payload with null when override off

- [ ] **Step 1: Validate optional hex; nullify when override checkbox absent**
- [ ] **Step 2: Pass surface colors into `$this->theme->update(...)`**
- [ ] **Step 3: Commit**

---

### Task 4: CSS vars + theme bridge

**Files:**
- Modify: `resources/views/layouts/partials/theme-vars.blade.php`
- Modify: `public/assets/css/theme-bridge.css`

- [ ] **Step 1: Inject surface CSS vars when set**
- [ ] **Step 2: Map vars to `#layout-navbar`, `#layout-menu`, `.layout-page` with glass-aware rules
- [ ] **Step 3: Commit**

---

### Task 5: Settings UI + preview JS

**Files:**
- Modify: `resources/views/admin/settings/theme-configuration/index.blade.php`
- Modify: `public/assets/js/theme-settings.js`

- [ ] **Step 1: Add Warna Surface card (3 override switches + pickers)**
- [ ] **Step 2: Extend preview mini-layout**
- [ ] **Step 3: JS enable/disable pickers + live preview**
- [ ] **Step 4: Commit**

---

### Task 6: Manual verification

- [ ] Override off → defaults
- [ ] Override on → colors apply on admin after save/reload
- [ ] Dark bg → light text; light bg → dark text
- [ ] Customer/agent layouts unaffected
- [ ] Final commit if polish needed
