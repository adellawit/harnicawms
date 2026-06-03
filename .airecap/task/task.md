# Task: Enhance Sidebar Layout (Modern Workspace-Switcher Style)

## Reference
User-provided mock: top brand area with workspace switcher card (logo box + "WIT."
+ "Cabang Bandung" subtitle + dual-chevron selector), section labels
("Platform", "Projects"), clean menu items, indented sub-items without bullets,
left-line accent for active leaf.

## Constraints
- Do not break existing `menu.js` interactions (relies on `.menu-item`,
  `.menu-toggle`, `.menu-link`, `.menu-sub`, `.open`, `.active` classes).
- No DB / route changes. Reuse existing data: `session('sidebars')` and the
  `auth()->user()->businessUnit` + `getSwitchableBusinessUnitIds()` already
  consumed in `layouts/header.blade.php`.
- Preserve theme variables (`--mono-700`, etc.).
- Keep responsive (mobile: sidebar still toggles).

## Plan
- [x] Read current sidebar (`layouts/navigation.blade.php`), layout
  (`layouts/app.blade.php`), header (`layouts/header.blade.php`), Menu model,
  SidebarService, and existing sidebar CSS.
- [x] Rebuild brand area in `navigation.blade.php` as a workspace card:
  - Logo chip (mono dark, rounded, "WIT" mark).
  - Two-line label: "WIT." (bold) + branch name (muted).
  - Dual-chevron selector icon on right; clicking opens branch dropdown
    (only if `canSwitchBranch`), reusing the existing `profile.switch-branch`
    POST flow.
  - Keep `layout-menu-toggle` for mobile collapse (compact, subtle).
- [x] Render `is_label` menu rows as section headers (graceful fallback when
  no rows are flagged).
- [x] Tighten markup of leaf/branch menu items; rely on existing classes.
- [x] Add scoped CSS at the end of `public/assets/css/custom.css`:
  - Workspace card layout, logo chip, dropdown styling.
  - Section header refinements (uppercase, tracking, small font).
  - Menu item: rounded hover, compact padding, refined chevron weight.
  - Submenu: indent guideline, no bullet, left-bar accent on active leaf.
  - Mobile/desktop tweaks.
- [x] Run lint check on touched files.
- [x] Manual review: walk through markup mentally for `menu.js` compatibility.

## Review
- Brand area is now a single workspace switcher card with mono logo chip,
  branch subtitle, and a dual chevron. When the user can switch branches it
  opens a Bootstrap dropdown that submits to the existing
  `profile.switch-branch` route (no new server logic).
- `is_label` menus now render as small uppercase section headers; menus
  without that flag continue to render exactly as before.
- Sub-menu items use a left guide rail and an accent bar for the active
  leaf; bullet pseudo-elements are suppressed for a cleaner look.
- Hover/active treatments use existing mono palette tokens, so the change
  is purely cosmetic and inherits theme controls.
- All vendor classes (`menu-item`, `menu-toggle`, `menu-link`, `menu-sub`,
  `open`, `active`) are preserved so `menu.js` keeps working.

## Lessons
- See `.airecap/task/lessons.md` if any patterns emerge.
