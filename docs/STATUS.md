# Status Proyek TITANIE

> Living Docs — **Status** role. Perbarui saat blocker, milestone, atau intentional removal berubah.

**Terakhir diperbarui:** 2026-08-16  
**Branch aktif:** `development`  
**Harness:** ECC Cursor install (`feature/ecc-cursor-install`)

## Yang Dikerjakan di AI Innovation Sprint 2026

Sprint 11–16 Agustus 2026, tim NATSEU. Scope terkunci ada di [SCOPE.md](SCOPE.md).

| Perubahan | Detail |
|-----------|--------|
| **Restrukturisasi alur Replenishment** | Logika pembuatan order pindah dari `ReplenishmentOrderController` ke `App\Services\Distribution\ReplenishmentOrderService` |
| **State machine status disatukan** | Nilai status yang tadinya string literal tersebar di controller, service, dan view kini bersumber dari `App\Support\ReplenishmentStatus`, lengkap dengan aturan transisi |
| **Aturan approve sebelum kirim ditegakkan** | Order berstatus `submitted` tidak lagi bisa langsung dikirim |
| **Tombol persetujuan dipasang di UI** | Endpoint approve sudah lama ada tapi belum punya tombol, sehingga alur persetujuan tidak pernah terpakai |
| **Baris item hilang tidak lagi didiamkan** | Varian produk yang tidak ditemukan dulu dilewati diam-diam; sekarang pembuatan order gagal dengan pesan jelas |
| **Chatbot TITANIE berbasis dokumentasi** | Tool `search_docs` baru membaca markdown di `docs/`; pengetahuan produk tidak lagi ditulis di system prompt |
| **Pesan error chatbot tidak lagi bocor** | Detail exception hanya masuk log, user menerima pesan generik |
| **Bug tool definition diperbaiki** | `AbstractAgentTool` merujuk `LlmProviderManager` tanpa import, sehingga seluruh definisi tool gagal terbentuk |
| **README jadi clone-to-run** | Ditambah prasyarat PostgreSQL, `jwt:secret`, `db:seed`, dan kredensial login default |
| **`composer install` bisa jalan tanpa flag** | `composer.lock` menyematkan enam paket Symfony 8.1.0 yang menuntut PHP >= 8.4.1, padahal `composer.json` menyatakan `^8.2`. Paket diturunkan ke 7.4.x dan lantai versi dikunci lewat `config.platform` |
| **Lantai PHP dikoreksi jadi 8.3** | `jwt-auth` 2.9.2 dan `zipstream-php` 3.2.2 memang mewajibkan PHP `^8.3`, jadi klaim 8.2+ di README dan dokumen lain tidak pernah benar |
| **`npm install` bisa jalan tanpa flag** | `laravel-vite-plugin` 1.x hanya mendukung Vite 5 dan 6 sementara repo memakai Vite 7; dinaikkan ke 2.x |
| **Dokumentasi sprint** | `SCOPE.md`, `PRD.md`, `AI_CONTEXT.md`, `PROMPTS.md`, `VIBE.md`, `AI_USAGE.md`, `PRODUCT_KNOWLEDGE.md` |
| **Deck Demo Day** | `docs/deck/index.html` — satu file HTML mandiri, tanpa build step |
| **Smoke test manual** | `scripts/ai-docs-knowledge-test.php`, `scripts/ai-chat-resilience-test.php`, `scripts/ai-tour-guide-test.php`, `scripts/ai-open-page-test.php`, `scripts/ai-employee-chat-test.php` |

## Kesehatan Umum

| Area | Status | Catatan |
|------|--------|---------|
| Core WMS modules | 🟢 Active | Auth, Product, Inventory, POS, Purchasing |
| Accounting module | 🟢 Active | COA, jurnal, cash bank (merged ke development) |
| CRM membership | 🟡 Planned | Schema `crm` — lihat ARCHITECTURE.md §3.4 |
| Automated tests | 🔴 Gap | Belum ada `tests/` + `phpunit.xml`; CI `ci-test` skip |
| Replenishment (Distributor→Agen) | 🟢 Active | Direstrukturisasi pada sprint; state machine di `App\Support\ReplenishmentStatus` |
| Chatbot TITANIE | 🟢 Active | Pengetahuan dari `docs/` lewat `search_docs`; Product Tour (`guide_tour`); buka halaman admin dari chat (`open_page`); create master di chat (`manage_record`, karyawan tanpa form); butuh `AGENT_ENABLED=true` + API key |
| ECC harness | 🟢 Installed | `.cursor/` dengan PHP rules, agents, hooks |
| Documentation | 🟢 Baseline | AGENTS.md, CONTRIBUTING.md, docs index, dokumen sprint |

## Blocker Saat Ini

| ID | Blocker | Owner | Target |
|----|---------|-------|--------|
| B-01 | Test suite belum ada — CI lint-test gate no-op | Backend | Tambah `phpunit.xml` + `tests/` + Makefile `ci-test` |
| B-02 | `.env.example` perlu diselaraskan dengan semua env vars production | DevOps | Review `.env-development-example` → sanitize ke `.env.example` |

## Milestone Terdekat

Lihat [PLAN.md](PLAN.md) untuk roadmap lengkap.

| Milestone | Status |
|-----------|--------|
| M-01 Feature inventory tervalidasi | Planned |
| M-02 POS + Product pricing stabil | In progress |
| M-03 Reporting baseline complete | Planned |
| M-04 Schema CRM live | Planned |

## Delete-Zone (Jangan Recreate)

Path/fitur sengaja dihapus atau di-exclude — jangan dibuat ulang tanpa review:

| Path / Item | Alasan | Sejak |
|-------------|--------|-------|
| `app/Services/Demo/` | Demo seeder lokal, di-gitignore | — |
| `app/Console/Commands/DemoSeed*.php` | Demo seeder lokal | — |
| `docs/superpowers/` | Planning docs lokal | — |
| Re-architecture ke microservices | Out of scope fase ini | PLAN.md §2.2 |

## CI/CD Status

- Pipeline: `.gitlab-ci.yml` (validate, lint-test, tag, build, deploy)
- `lint-test`: menunggu Makefile `ci-test` dengan PostgreSQL service
- Deploy service: `wms` @ port 8181

## ECC Harness Audit

Jalankan audit lokal:

```bash
node .cursor/scripts/harness-audit.js --format text
```

Target perbaikan audit:

- [x] AGENTS.md / CLAUDE.md
- [x] CONTRIBUTING.md, SECURITY.md
- [x] `.gitignore` mengabaikan `.env`
- [ ] `tests/` + test script
- [ ] ADR di `docs/adr/` (opsional)

## Changelog Ringkas

| Tanggal | Perubahan |
|---------|-----------|
| 2026-08-13 | AI Innovation Sprint: restrukturisasi Replenishment, chatbot TITANIE berbasis `docs/`, dokumentasi sprint |
| 2026-08-12 | Init dokumentasi ECC git standard (AGENTS, CONTRIBUTING, docs governance) |
| 2026-08-12 | ECC Cursor harness install (PHP rules, agents, hooks) |
| 2026-04 | Accounting module merge ke development |
