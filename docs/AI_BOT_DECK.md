# Outline Deck — AIBOT TITANIE (Demo Day)

> Outline naratif untuk **deck lomba/pitch**, bukan project brief.
> Bahasa: Indonesia. Protagonisnya **AIBOT**; TITANIE (WMS) hanya panggung.
>
> Deck jadi: `docs/AI_BOT_DECK.pptx` — **22 slide**. Pratinjau PNG per slide: `docs/deck-preview/`.
>
> Sumber fakta: [AI_BOT.md](AI_BOT.md). Jangan menambah angka yang tidak ada di dokumen.
>
> **Cara pakai:** talk track lengkap sudah ada di *speaker notes* tiap slide PPTX. File ini adalah peta narasi + bank konten (prompt demo, checklist screenshot).

**Durasi saran:** 10–14 menit + Q&A. **Struktur:** 4 babak, dipisahkan slide barrier hitam.

| Babak | Slide | Tujuan |
|-------|-------|--------|
| Pembuka | 1–2 | Hook + satu janji |
| 01 · Masalah & Tesis | 3–6 | Rasa sakit → insight → persona |
| 02 · Demo Langsung | 7–13 | Bukti, satu slide per babak demo |
| 03 · Arsitektur & Kepercayaan | 14–18 | Cara kerja, pagar, kejujuran |
| 04 · Dampak & Penutup | 19–21 | Kenapa penting + closing statement |
| Lampiran | 22 | Prompt siap pakai (tidak wajib tampil) |

---

# Peta slide

| # | Slide | Pola visual | Butuh aset |
|---|-------|-------------|------------|
| 1 | Cover — “Ngobrol Saja. Gudangnya Yang Ngerti.” | Cover hitam + maskot | `mascot-full.png` · **TBD:** audiens, tanggal, presenter |
| 2 | Satu Janji — Menu → Chat | Kartu statement + 3 kartu (Tanya / Tunjukkan / Tulis) | — |
| 3 | Barrier **01 · Masalah & Tesis** | Angka raksasa + maskot | `mascot-pose.png` |
| 4 | Kenapa Operator Tersesat | 3 kartu, footer “Biaya nyata” merah | — |
| 5 | Chat Sebagai Antarmuka, Bukan Tab FAQ | Split panel Cara lama (hitam) vs Dengan AIBOT (biru) | — |
| 6 | Kenalan Dulu: TITANIE | Kartu maskot + 4 poin persona | `mascot-full.png` |
| 7 | Barrier **02 · Demo Langsung** | Angka raksasa + maskot | `mascot-head.png` |
| 8 | Lima Babak Demo | Pipeline chevron + chip nama tool | — |
| 9 | Babak 1 — Tanya | Frame screenshot + bubble prompt + 3 poin | **Screenshot** |
| 10 | Babak 2 — Buka Halaman | idem | **Screenshot** |
| 11 | Babak 3 — Tur | idem | **Screenshot** |
| 12 | Babak 4 — Tulis | idem | **Screenshot** |
| 13 | Babak 5 — Konfirmasi | idem | **Screenshot** (wajib) |
| 14 | Barrier **03 · Arsitektur & Kepercayaan** | Angka raksasa + maskot | `mascot-pose.png` |
| 15 | Cara Kerja: Tool-Calling | Pipeline 4 langkah + 3 kartu prinsip | — |
| 16 | Peta Kemampuan & Tool | Tabel 5 baris + insight izin | — |
| 17 | Pagar Pengaman | Split panel Wajib klik manusia (biru) vs Dikunci mati (merah) | — |
| 18 | Nyata Hari Ini vs Arah Berikutnya | Kartu “sudah jalan” vs panel merah “belum — dan sengaja” | — |
| 19 | Barrier **04 · Dampak & Penutup** | Angka raksasa + maskot | `mascot-head.png` |
| 20 | Kenapa Ini Penting | 3 kartu, footer “Ukuran dampak: **TBD**” | **TBD:** metrik |
| 21 | Yang Ingin Diingat | Closing hitam + 3 takeaway + maskot | `mascot-pose.png` |
| 22 | Lampiran — Prompt Siap Pakai & Prasyarat | 2 kartu: aman untuk live vs sengaja tidak didemo | — |

---

# Alur cerita (kenapa urutannya begini)

1. **Hook (1)** — judul percakapan, bukan judul fitur. Maskot lebih dulu terlihat daripada arsitektur.
2. **Janji (2)** — satu kalimat kontrak dengan juri: *operator tidak perlu hafal menu*. Tiga kartu jadi peta isi deck.
3. **Masalah (4)** — biaya nyata ditandai merah: ongkos belajar, konteks terputus, keputusan tertunda. Bukan “belum punya ERP”.
4. **Tesis (5)** — before/after berdampingan. Yang berubah cuma pintunya; service di belakang sama.
5. **Persona (6)** — maskot dijelaskan sebagai kewenangan yang dibatasi kode, bukan gimmick.
6. **Demo (8–13)** — janji lima babak dulu, lalu buktikan satu-satu. Babak 5 (konfirmasi) adalah klimaksnya.
7. **Arsitektur (15–16)** — baru setelah juri melihat hasilnya. Satu diagram, tanpa dump kode.
8. **Kepercayaan (17–18)** — pagar dan batas dinyatakan sendiri sebelum ditanya.
9. **Dampak (20)** — klaim kualitatif; slot angka sengaja **TBD**.
10. **Closing (21)** — “LLM mengusulkan. Manusia mengonfirmasi. Service WMS yang menulis.”

---

# Yang masih perlu diisi sebelum hari-H

- **Slide 1** — audiens, tanggal, nama presenter (semua `TBD`).
- **Slide 9–13** — lima screenshot produk. Frame, caption, dan instruksi crop sudah ada di slide; tinggal ganti area wireframe dengan gambar.
- **Slide 20** — tiga slot `Ukuran dampak: TBD`. Isi hanya jika ada laporan internal; kalau tidak ada, biarkan TBD dan katakan apa adanya.

---

# Bank konten (tidak perlu jadi slide)

## Tiga one-liner judul (pilih satu)

1. **Chat sebagai antarmuka gudang** — paling netral, cocok internal.
2. **Tanya, buka, tulis — tanpa hilang di menu** — cocok demo operator.
3. **LLM mengusulkan, manusia mengonfirmasi, WMS yang menulis** — cocok audiens teknis/keamanan.

## Prompt demo (8–12)

Pakai urutan ini agar lima babak demo (slide 8–13) terasa utuh. Tandai mana yang butuh konfirmasi.

| # | Ketik di chat | Yang diharapkan | Risiko |
|---|----------------|-----------------|--------|
| 1 | Apa sih ini? | Tur dalam halaman + penjelasan | Rendah |
| 2 | Aplikasi ini sebenarnya untuk apa? | FAQ dari docs, tanpa `(sumber: …md)` | Rendah |
| 3 | Buka halaman kategori | Pindah ke kategori, chat tetap terbuka | Rendah |
| 4 | Tampilkan stok | Tabel stok cabang aktif | Rendah |
| 5 | Stok kopi arabica berapa? | Angka dari tool stok/produk | Rendah |
| 6 | Tambahkan divisi Management | Create master di chat | Sedang — hapus lagi dengan konfirmasi |
| 7 | Tambah karyawan [ISI: nama dummy], email [ISI: email tes], role Staff, status aktif, join hari ini | Form/create di chat; password tidak diungkap | Sedang |
| 8 | Tambah produk [ISI: nama tes], dijual | Create produk (+ varian default jika dijual) | Sedang |
| 9 | Buat customer PT Maju | Create pelanggan (grup default UMUM) | Sedang |
| 10 | Jual 2 kopi arabica tunai | Kartu Buat transaksi — **konfirmasi hanya jika data demo** | Tinggi |
| 11 | Sesuaikan stok [ISI: SKU tes] jadi [ISI: qty] | Kartu konfirmasi mutasi — jangan di produksi | Tinggi |
| 12 | Hapus divisi Management | Kartu hapus wajib | Sedang |

**Sengaja jangan didemo live** (biar kelihatan pagar, atau karena merusak):

- “Jual pakai transfer bank” → ditolak
- “Approve order replenishment dari chat” → ditolak / dialihkan
- “Posting jurnal yang debit tidak sama kredit” → ditolak
- “Berapa komisi agen bulan ini?” → tidak ada modul

## Checklist screenshot

Lima frame di slide 9–13 menunggu gambar ini. Ambil sebelum hari-H, crop ketat (maskot + panel, bukan seluruh IDE):

- [ ] **Slide 9 · Tanya** — bubble FAQ tanpa sitasi `.md`, panel chat + chip saran
- [ ] **Slide 10 · Buka halaman** — setelah `open_page`: URL admin baru, panel masih terbuka
- [ ] **Slide 11 · Tur** — overlay spotlight (sidebar atau tombol Tambah) + tooltip Lanjut/Lewati
- [ ] **Slide 12 · Tulis** — missing-field: bot minta nama/email di chat (bukan “silakan klik Tambah”)
- [ ] **Slide 13 · Konfirmasi** — kartu Buat transaksi / Batal, data dummy (frame paling penting)

Cadangan kalau ada waktu:

- [ ] Maskot di pojok sebelum panel dibuka
- [ ] Tabel stok / produk di chat
- [ ] Kartu konfirmasi hapus
- [ ] Kartu konfirmasi Super Admin (kalau boleh di lingkungan tes)
- [ ] Tombol speaker (suara mati vs nyala) — opsional

## Chip saran bawaan (disebut di slide 16)

Apa sih ini? · Turin fiturnya dong · Buka halaman kategori · Gimana alur order agen? · Tampilkan stok · Tambahkan divisi Management · Daftar produk · Jual 2 kopi arabica tunai

## Cadangan jawaban Q&A

- *Kenapa bukan ChatGPT biasa?* Karena tool terikat cabang, izin menu, dan service stok/POS — bukan copy-paste ke model publik.
- *Apakah bot bisa merusak stok?* Tidak lewat kolom quantity. Penyesuaian lewat mutasi + kartu. Production/replenish berat tidak di-submit dari chat.
- *FAQ salah?* Perbaiki markdown di `docs/`, refresh — bukan fine-tune.
- *Suara tidak nyala?* Default mati; nyalakan speaker. Bergantung browser (Web Speech API).
- *Angka pemakaian?* Jangan mengarang. [ISI: isi hanya jika ada laporan internal terkini — AI_USAGE membedakan biaya bangun vs API bot, bukan KPI operator.]
- *Kalau demo gagal?* Cek cabang aktif, izin menu, lalu API key. Jangan debug `.env` di depan juri; siapkan akun cadangan.

## Prasyarat sebelum membuka maskot

1. Login backoffice (bukan portal agen)
2. Pilih **cabang aktif** di profil
3. Env: `AGENT_ENABLED=true`, `AGENT_WIDGET_ENABLED=true`, provider + API key
4. Izin menu **AI Assistant** `is_read`, atau role Super Admin

## Yang tidak diisi di deck ini

- Metrik adopsi, CSAT, penghematan waktu, biaya per pesan — **tidak ada angka kanonik untuk operator**. Jangan mengarang.
- Daftar 11 modul WMS — lihat [FITUR.md](FITUR.md) jika ada sesi terpisah.
- Pitch fundraising (pasar, raise, use of funds) — bukan tujuan file ini.
