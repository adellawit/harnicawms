# AI_USAGE — Laporan Penggunaan AI

> Laporan tool AI yang dipakai tim NATSEU selama AI Innovation Sprint 2026,
> beserta peruntukan dan biayanya.
>
> **Terakhir diperbarui:** 16 Agustus 2026 (deadline sprint)

---

## 1. Ringkasan

Ada dua jenis penggunaan AI yang perlu dibedakan, karena sumber biayanya berbeda:

| Jenis | Untuk apa | Ditanggung | Bukti |
|-------|-----------|------------|-------|
| **AI untuk membangun** | Menulis kode, menelusuri repo, menyusun dokumentasi | Langganan Cursor Pro | [billing-cursor.png](billing-cursor.png) |
| **AI di dalam produk** | Chatbot TITANIE yang dipakai user aplikasi | API DeepSeek pay-as-you-go | [billing-deepseek.png](billing-deepseek.png) |

---

## 2. AI untuk Membangun

| Tool | Model | Peruntukan | Skema biaya |
|------|-------|------------|-------------|
| Cursor IDE | Cursor Grok 4.5, Composer 2.5, plus kuota Other Models | Implementasi fitur, restrukturisasi, penulisan dokumentasi, review | Cursor Pro USD 20 / bulan per seat |
| Cursor Tab | Model autocomplete Cursor | Pelengkapan kode saat mengetik | Termasuk langganan |

**Cara kami memakainya**

Pola kerjanya dijelaskan di [VIBE.md](VIBE.md). Ringkasnya: AI diminta menelusuri dan merencanakan lebih dulu, baru mengimplementasikan; keputusan skema database dan batas modul tetap di tangan manusia.

Pekerjaan sprint yang dibantu AI secara signifikan:

- Audit modul existing dan pemetaan alur bisnis untuk menentukan scope
- Ekstraksi logika `ReplenishmentOrderController` ke service layer
- Penyatuan state machine status replenishment
- Implementasi `SearchDocsTool` dan `DocsKnowledgeBase`
- Penyusunan seluruh dokumen di `docs/`
- Penulisan smoke test di `scripts/`

### Pemakaian Cursor (tanggal ambil: 16 Agustus 2026)

Sumber: halaman Plan & Usage Cursor. Zona reset siklus: **11 September** (sisa 26 hari pada saat screenshot).

| Item | Nilai |
|------|-------|
| Paket | Pro, USD 20 / bulan |
| Cursor Models (Grok 4.5, Composer 2.5) | **30%** kuota terpakai |
| Other Models | **100%** kuota terpakai |
| Catatan kuota | Pemakaian di atas batas Cursor Models memakai kuota Other Models atau on-demand. Paket mencakup minimal USD 20 pemakaian API. |

Pemakaian Other Models penuh sesuai pola sprint: pekerjaan berat (audit repo, restrukturisasi, dokumentasi) memakai model di luar paket Cursor Models.

**Bukti biaya:**

![Billing Cursor — Plan & Usage, 16 Agustus 2026](billing-cursor.png)

---

## 3. AI di Dalam Produk — Chatbot TITANIE

| Item | Nilai |
|------|-------|
| Provider | DeepSeek (`AI_PROVIDER=deepseek`) |
| Model | `deepseek-chat` |
| Alternatif tersedia | OpenAI-compatible lewat `AI_PROVIDER=chatai` |
| Batas token per balasan | 800 (`DEEPSEEK_MAX_TOKENS`) |
| Batas panjang pesan user | 2.000 karakter |
| Batas putaran tool per pesan | 5 |
| Rate limit | 30 permintaan per menit per user |

Pemilihan DeepSeek didasari harga per token yang jauh lebih murah dari model kelas atas, sementara beban kerjanya ringan: merangkum kutipan dokumentasi yang sudah disaring server, bukan menalar dari nol.

### Pemakaian DeepSeek (dashboard provider)

Sumber: halaman Usage DeepSeek, filter **Last 30 days**, semua API key. Waktu dashboard **GMT+7**. Tanggal ambil: **16 Agustus 2026**.

| Metrik | Nilai |
|--------|-------|
| Total cost (periode tampilan) | **USD 0,05** |
| API requests | **248** |
| Tokens | **1.749.761** |
| Saldo top-up tersisa | USD 1,94 |

Angka token di dashboard lebih besar dari perkiraan karakter di database aplikasi, karena dashboard menghitung seluruh token request (system prompt, tool definition, kutipan `search_docs`, dan output), bukan hanya teks yang disimpan di `agent_messages`.

### Jejak di database aplikasi (pelengkap)

Aplikasi belum menyimpan field `usage` dari provider. Jejak lokal di `auth.agent_conversations`, `auth.agent_messages`, dan `auth.agent_tool_logs` (snapshot 13 Agustus 2026) hanya menunjukkan volume percakapan, bukan tagihan:

| Metrik | Nilai |
|--------|-------|
| Percakapan | 8 |
| Pesan tersimpan (user, assistant, tool) | 27 |
| Pemanggilan tool | 7, seluruhnya `search_docs` |
| Total karakter pesan | 27.984 |
| Perkiraan token dari karakter (÷ 4) | ± 7.000 |

Mencatat `usage` dari respons provider tetap kandidat perbaikan berikutnya; untuk laporan sprint, **angka resmi biaya adalah dashboard DeepSeek di atas**.

### Perkiraan biaya operasional ke depan

Dengan pemakaian sprint aktual **USD 0,05** untuk ~1,75 juta token / 248 request, dan tarif DeepSeek pada kisaran USD 0,27 per juta token input dan USD 1,10 per juta token output:

| Skenario | Perkiraan | Perkiraan biaya |
|----------|-----------|-----------------|
| Pengujian sprint (terukur 16 Agu) | 1,75 juta token, 248 request | **USD 0,05** |
| 10 user aktif, 20 pertanyaan per hari | ± 3 juta token / bulan | ± USD 1–3 |
| 50 user aktif, 20 pertanyaan per hari | ± 15 juta token / bulan | ± USD 6–15 |

Biaya per pertanyaan tetap kecil karena `search_docs` hanya mengirim potongan dokumen yang relevan, dibatasi 1.600 karakter per kutipan dan maksimal empat kutipan. Tanpa pembatasan itu, seluruh isi `docs/` akan ikut terkirim di setiap pertanyaan dan biayanya membengkak berkali-kali lipat.

**Bukti biaya:**

![Billing DeepSeek — Usage 30 hari, 16 Agustus 2026](billing-deepseek.png)

---

## 4. Yang Sengaja Tidak Dipakai

| Tidak dipakai | Alasan |
|---------------|--------|
| Vector database (Pinecone, Qdrant, pgvector) | Aturan sprint melarang menambah database engine. Pencarian dokumentasi cukup memakai pencocokan kata kunci di dalam aplikasi |
| Layanan embedding berbayar | Konsekuensi dari poin di atas — tidak ada embedding, tidak ada biayanya |
| Fine-tuning model | Tidak sepadan untuk cakupan sebesar ini; memperbaiki dokumentasi jauh lebih murah dan efeknya langsung terlihat |
| Model kelas atas untuk chatbot produk | Tugasnya merangkum kutipan yang sudah disaring, tidak butuh penalaran berat |

---

## 5. Cara Menekan Biaya

Keputusan teknis yang memang diambil untuk menahan biaya:

1. **Penyaringan dilakukan server, bukan model.** `DocsKnowledgeBase` memilih bagian yang relevan lebih dulu, sehingga model hanya menerima potongan kecil.
2. **Kutipan dipotong.** Maksimal 1.600 karakter per bagian, empat bagian per pencarian.
3. **Pertanyaan di luar cakupan ditolak lebih awal.** Kalau mayoritas kata tidak dikenal di dokumentasi, pencarian mengembalikan kosong dan model langsung menjawab tidak tahu tanpa putaran tool tambahan.
4. **Putaran tool dibatasi lima.** Mencegah percakapan berputar dan menghabiskan token.
5. **Folder `docs/superpowers/` dikecualikan.** Empat puluh dokumen handoff historis tidak ikut jadi kandidat kutipan.
6. **Verifikasi rutin tidak memanggil API.** `scripts/ai-docs-knowledge-test.php` dan `scripts/ai-chat-resilience-test.php` menguji logika secara lokal; hanya `scripts/ai-chat-live-check.php` yang memanggil provider, dan itu dijalankan manual seperlunya.

---

## 6. Dokumen Terkait

| Dokumen | Isi |
|---------|-----|
| [VIBE.md](VIBE.md) | Cara tim bekerja dengan AI |
| [PROMPTS.md](PROMPTS.md) | Prompt yang dipakai berulang |
| [AI_CONTEXT.md](AI_CONTEXT.md) | Konteks repo untuk AI |
| [ARCHITECTURE.md](ARCHITECTURE.md) §3.5 | Arsitektur subsistem chatbot |
| [billing-cursor.png](billing-cursor.png) | Screenshot Plan & Usage Cursor |
| [billing-deepseek.png](billing-deepseek.png) | Screenshot Usage DeepSeek |
