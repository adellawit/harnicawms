# Master Plan (Based on Existing Features)

> Dokumen ini adalah plan pengembangan lanjutan di atas fitur yang sudah ada di project saat ini.

## 1. Objective

- **Goal Utama**: Menstabilkan, merapikan, dan memperluas modul yang sudah ada tanpa regressions.
- **Success Criteria**: Semua modul existing terdokumentasi, teruji, dan siap scale per prioritas bisnis.
- **Out of Scope**: Re-architecture total ke microservices pada fase ini.

## 2. Scope Plan

### 2.1 In Scope

- Hardening modul existing: Auth, HR, Customer, Product, Inventory, Purchasing, POS, Reporting.
- Konsolidasi dokumentasi architecture + feature inventory + epic/task.
- Penyelarasan route, menu, permission, dan reporting coverage.
- Penambahan schema `crm` untuk modul **CRM Membership** (konfigurasi poin & integrasi ke Customer/POS) sebagai ekstensi domain existing.

### 2.2 Out of Scope

- Pembuatan modul baru di luar domain existing.
- Migrasi teknologi frontend/backend utama.

## 3. Roadmap Phases

| Phase | Nama | Fokus | Output | Estimasi |
|---|---|---|---|---|
| `P1` | Discovery Existing | Validasi inventory fitur existing | Baseline docs + gap list | 1-2 minggu |
| `P2` | Core Stabilization | Auth/HR/Product/POS hardening | Bugfix & consistency pass | 2-4 minggu |
| `P3` | Reporting & Data Quality | Lengkapi report + data integrity | Report parity + data checks | 2-3 minggu |
| `P4` | Performance & Security | Optimasi query, permission, audit | Perf/security baseline | 1-2 minggu |
| `P5` | Release Governance | UAT, release checklist, rollback drill | Go-live readiness | 1 minggu |

## 4. Milestones

| Milestone | Deskripsi | Target Date | Dependency | Status |
|---|---|---|---|---|
| `M-01` | Feature inventory tervalidasi terhadap route+menu | `[YYYY-MM-DD]` | Product/Tech Lead approval | Planned |
| `M-02` | Modul POS + Product pricing stabil | `[YYYY-MM-DD]` | Data seeding valid | Planned |
| `M-03` | Reporting baseline complete (sales/po/transaction/stock) | `[YYYY-MM-DD]` | Dataset valid | Planned |
| `M-04` | Schema `crm` live dengan konfigurasi poin membership + integrasi ke Customer & POS | `[YYYY-MM-DD]` | POS & Customer module stable | Planned |

## 5. Dependency Plan

| Dependency | Type | Owner | Due Date | Note |
|---|---|---|---|---|
| Ketersediaan database dan sample data valid | Internal | Backend | `[YYYY-MM-DD]` | Wajib untuk test E2E |
| Sinkronisasi menu-permission-role | Internal | Backend + QA | `[YYYY-MM-DD]` | Untuk validasi akses |
| Desain skema `crm` dan relasi ke `customer` + `transaction` | Internal | Backend | `[YYYY-MM-DD]` | Untuk membership & poin loyalty |

## 6. Resource Plan

### 6.1 Team

- `[PM]`
- `[Backend Engineer]`
- `[Frontend Engineer]`
- `[QA]`
- `[DevOps]`

### 6.2 Capacity

| Role | Capacity per Sprint | Allocation | Notes |
|---|---|---|---|
| Backend | `[TBD]` | `[TBD]` | Fokus modul domain dan integrasi |
| Frontend | `[TBD]` | `[TBD]` | Fokus UX konsistensi page existing |
| QA | `[TBD]` | `[TBD]` | Regression cross-module |

## 7. Testing & Quality Plan

- **Unit Test**: Prioritas pada service/controller kritikal (POS, Product, Purchase Order).
- **Integration Test**: Alur end-to-end per modul existing.
- **UAT**: Scenario berbasis menu aktif dan role permission.
- **Performance Test**: Fokus listing/report query besar.

## 8. Release Plan

### 8.1 Release Strategy

- Incremental by module (rolling release).

### 8.2 Rollback Plan

- Backup DB + rollback migration/script per release batch.

### 8.3 Go-Live Checklist

- `[ ] Database migration approved`
- `[ ] Backup executed`
- `[ ] Smoke test passed`
- `[ ] Monitoring enabled`

## 9. Risk & Mitigation Plan

| Risk | Probability | Impact | Mitigation | Contingency |
|---|---|---|---|---|
| Regression lintas modul existing | Medium | High | Regression checklist per module | Hotfix window |
| Inkonsistensi seed data demo | High | Medium | Seeder validation script | Re-seed + data patch |

## 10. Communication Plan

| Cadence | Audience | Channel | PIC | Output |
|---|---|---|---|---|
| Weekly | Product + Engineering + QA | Sync meeting + docs update | Tech Lead | Progress vs module epic |
