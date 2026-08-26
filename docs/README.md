# Dokumentasi TITANIE

Indeks dokumentasi proyek (Living Docs — **Map** role). Setiap fakta punya satu sumber kanonik; file lain hanya merujuk.

## Navigasi Cepat

| Dokumen | Role | Deskripsi |
|---------|------|-----------|
| [ARCHITECTURE.md](ARCHITECTURE.md) | Map | Arsitektur sistem, stack, bounded context |
| [STATUS.md](STATUS.md) | Status | Kesehatan proyek, blocker, delete-zone |
| [PLAN.md](PLAN.md) | History/Roadmap | Roadmap fase dan milestone |
| [FEATURE_BREAKDOWN.md](FEATURE_BREAKDOWN.md) | Map | Inventori fitur per modul |
| [EPIC_TASK_BY_MODULE.md](EPIC_TASK_BY_MODULE.md) | Map | Epic dan task per modul |
| [TASK_BY_MODULE_SPREADSHEET.md](TASK_BY_MODULE_SPREADSHEET.md) | Map | Spreadsheet referensi task |

## Dokumen AI Innovation Sprint 2026

| Dokumen | Role | Deskripsi |
|---------|------|-----------|
| [SCOPE.md](SCOPE.md) | Commitment | Alur inti yang direstrukturisasi dan batas out-of-scope — **terkunci** |
| [PRD.md](PRD.md) | Map | Kebutuhan produk alur Replenishment Distributor → Agen |
| [AI_CONTEXT.md](AI_CONTEXT.md) | Map | Konteks repo untuk AI: konvensi, jebakan, dan yang tidak ada di repo |
| [PROMPTS.md](PROMPTS.md) | Process | Prompt siap pakai untuk kerja sehari-hari |
| [VIBE.md](VIBE.md) | Process | Cara tim bekerja dengan AI |
| [AI_USAGE.md](AI_USAGE.md) | Status | Laporan tool AI, peruntukan, dan biaya |
| [PRODUCT_KNOWLEDGE.md](PRODUCT_KNOWLEDGE.md) | Commitment | Lima jawaban Product Knowledge — sumber tunggal untuk deck dan chatbot |
| [AI_BOT.md](AI_BOT.md) | Map | Chatbot in-app: apa yang bisa, apa yang tidak, syarat widget |
| [AI_BOT_DECK.md](AI_BOT_DECK.md) | Process | Outline slide AIBOT (bahasa Indonesia, siap tempel ke deck) |
| [FITUR.md](FITUR.md) | Map | Fitur dan fungsi per modul — panduan bicara demo |
| [deck/index.html](deck/index.html) | Process | Deck Demo Day, satu file HTML mandiri (buka langsung di browser) |

Dokumen di atas sekaligus menjadi basis pengetahuan chatbot TITANIE lewat tool `search_docs`. Memperbaiki jawaban chatbot berarti memperbaiki dokumen ini, bukan mengedit prompt.

## Instruksi Agent & Proses

| Dokumen | Lokasi |
|---------|--------|
| Agent instructions | [../AGENTS.md](../AGENTS.md) |
| AI quick context | [../CLAUDE.md](../CLAUDE.md) |
| Git & contributing | [../CONTRIBUTING.md](../CONTRIBUTING.md) |
| Security policy | [../SECURITY.md](../SECURITY.md) |

## ECC Harness

| Surface | Path |
|---------|------|
| Cursor rules | `../.cursor/rules/` |
| Agents | `../.cursor/agents/` |
| Skills | `../.cursor/skills/` |
| Install config | [../ecc-install.json](../ecc-install.json) |

## Urutan Baca (Agent / Developer Baru)

1. [../README.md](../README.md) — overview & setup
2. [AI_CONTEXT.md](AI_CONTEXT.md) — konvensi repo, baca sebelum menyentuh kode
3. [ARCHITECTURE.md](ARCHITECTURE.md) — peta sistem
4. [STATUS.md](STATUS.md) — apa yang blocked / sengaja dihapus
5. [PLAN.md](PLAN.md) — arah pengembangan
6. Modul spesifik → [FEATURE_BREAKDOWN.md](FEATURE_BREAKDOWN.md)

## Yang Tidak Di-commit

Folder berikut di-ignore (lihat `.gitignore`):

- `docs/superpowers/` — planning lokal
- `.env*` dengan credential nyata

## Memperbarui Dokumentasi

| Perubahan | Update |
|-----------|--------|
| Struktur modul / arsitektur | `ARCHITECTURE.md` |
| Blocker / milestone / delete-zone | `STATUS.md` |
| Roadmap fase | `PLAN.md` |
| Konvensi kode / jebakan repo | `AI_CONTEXT.md` |
| Kemampuan / batas chatbot in-app | `AI_BOT.md` |
| Kebutuhan alur replenishment | `PRD.md` |
| Git workflow / setup | `../CONTRIBUTING.md` |

Jangan duplikasi konten antar file — link ke sumber kanonik.
