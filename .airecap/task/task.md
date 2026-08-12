# Theme Appearance Studio

## Checklist
- [x] Explore context + clarify (A upgrade, A full tokens, B mock preview)
- [x] Approach 1 approved
- [x] Design sections approved (layout, tokens, components)
- [x] Spec written
- [x] User review spec → implementation
- [x] Implementation plan
- [x] Migration + model
- [x] AppThemeService tokens + ThemePaletteGenerator
- [x] Controller + generate route
- [x] CSS + Blade studio UI + theme-appearance.js
- [ ] Manual verify on `/settings/theme-configuration` (hard refresh)

## Spec
`docs/superpowers/specs/2026-08-06-theme-appearance-studio-design.md`

## Plan
`docs/superpowers/plans/2026-08-06-theme-appearance-studio.md`

## Review
- Page Appearance & Theme di-upgrade jadi studio: preview mock dashboard (sidebar+navbar+scenes) + panel token kanan
- Token light/dark JSONB, AI generate, Save sync ke legacy primary/secondary/surfaces
- Navbar tokens custom (`navbar-background` / `navbar-foreground`); Import/Export dihapus
- Dark mode: preview + persist saja (belum global app toggle)
- Verified: migration OK, routes registered, Blade cache compiles, token resolve includes navbar
