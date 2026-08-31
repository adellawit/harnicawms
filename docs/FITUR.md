# Fitur dan Fungsi TITANIE (WMS 3.0)

> Panduan bicara: apa yang ada di aplikasi, untuk siapa, dan apa fungsinya.
> Kondisi per **14 Agustus 2026**, branch `main`.
>
> Jawaban Demo Day yang lebih formal ada di [PRODUCT_KNOWLEDGE.md](PRODUCT_KNOWLEDGE.md).
> Inventori teknis per menu ada di [FEATURE_BREAKDOWN.md](FEATURE_BREAKDOWN.md).

**TITANIE** adalah ERP distribusi untuk **CV Suhara Botanica (Harnica)** — produk herbal yang bergerak lewat jaringan partner, bukan penjualan langsung sebagai jalur utama.

```
Produksi → Gudang distributor → Agen → Reseller → Konsumen
```

Satu aplikasi Laravel 12 (modular monolith) + PostgreSQL multi-schema. Ada dua pintu masuk:

| Siapa | Masuk lewat | Fungsi |
|-------|-------------|--------|
| Internal (admin, gudang, finance, marketing) | Backoffice `/admin` | Operasi gudang, order, akuntansi, master data |
| Agen | Portal `/agent-order` | Pesan barang ke distributor, lihat katalog, training, materi pemasaran |

---

## 1. Cara menjelaskan dalam 30 detik

TITANIE mengelola stok dan nilai barang herbal dari pabrik sampai agen. Intinya: **siapa yang punya stok, berapa HPP-nya, dan kapan kadaluwarsa**. Chatbot TITANIE membantu operator menanyakan alur itu dari dokumentasi, dan bisa menyiapkan penjualan tunai dengan konfirmasi di layar.

---

## 2. Alur bisnis inti — Replenishment Distributor → Agen

Ini alur yang paling penting untuk dijelaskan. Hanya jalur ini yang **end-to-end**: order → approve → kirim (stok keluar FIFO) → terima (stok masuk + HPP agen) → retur bila perlu.

**Layar:** `/agen-order` (bukan `/agent-order` — itu portal penjualan agen).

| Tahap | Siapa | Fungsi |
|-------|--------|--------|
| Buat order | Agen (atau admin untuk agen) | Memesan stok ke distributor |
| Approve | Distributor | Menyetujui sebelum barang boleh dikirim |
| Kirim | Distributor / gudang | Stok keluar gudang asal, FIFO, expiry ikut di shipment |
| Terima | Agen | Stok masuk gudang agen; cost layer baru = **harga transfer**, bukan HPP distributor |
| Retur | Agen | Barang kembali; stok agen berkurang, stok distributor bertambah |

**Kenapa ini “berat” secara ERP:** yang mahal bukan ganti status, tapi efek samping stok dan HPP. Salah di sini, margin agen dan persediaan distributor ikut salah.

**Perbaikan sprint (bisa disebut):**

- Logika order di `ReplenishmentOrderService` (bukan menumpuk di controller)
- Status satu sumber: `ReplenishmentStatus` — transisi tidak sah ditolak
- **Approve wajib** sebelum kirim; tombol approve ada di UI
- Varian produk yang tidak ketemu **gagal dengan pesan jelas**, tidak dilewati diam-diam

**Cara agen muncul di alur ini:** calon mengisi pendaftaran tipe Agent (`/partner/register` atau Applications di admin) → admin **Convert Agent**. Convert membuat record agen, gudang agen, dan akun login. Menu Agents hanya daftar, bukan form buat agen.

---

## 3. Fitur per modul — apa dan fungsinya

### 3.1 Gudang, stok, pembelian

| Fitur | Fungsi |
|-------|--------|
| Multi-gudang + cabang | Stok terpisah per lokasi (distributor vs agen) |
| PO + penerimaan | Barang masuk dari supplier tercatat ke gudang |
| Pergerakan stok | Jejak mutasi di `product_stock_movements` |
| HPP FIFO / FEFO | Nilai persediaan mengikuti lapisan biaya; expiry relevan untuk herbal |
| Barcode / QR | Identifikasi dan cetak label (box, batch, receiving) |

### 3.2 Produksi (BOM)

| Fitur | Fungsi |
|-------|--------|
| Bill of Material | Resep: bahan → barang jadi + HPP komponen |
| Production order | Draft → proses → receiving qty aktual |
| Variance bahan | Bandingkan pemakaian vs resep |
| Handoff barcode | Setelah receive, lanjut cetak label |

### 3.3 Jaringan partner

| Fitur | Fungsi |
|-------|--------|
| Pendaftaran publik `/partner/register` | Calon agen/reseller mengisi formulir |
| Convert Agent | Mengubah application jadi agen aktif + gudang + user |
| Convert Reseller | Mengaitkan reseller ke agen yang sudah ada |
| Master ongkir | Tarif kirim untuk order |
| Cutting price FOREDI | Aturan harga potong per kategori |
| Laporan MAP | Pantau harga vs kebijakan |

Agen vs reseller: **agen** yang replenish stok dari distributor; **reseller** di bawah agen, bukan pemesan replenishment.

### 3.4 Portal agen (`/agent-order`)

| Fitur | Fungsi |
|-------|--------|
| Landing + login terpisah | Pintu partner, bukan backoffice |
| Katalog, cart, checkout tunai | Agen belanja / order ke jalur sales (bukan replenishment) |
| Riwayat order | Filter dan detail pesanan |
| Training + materi pemasaran | Konten academy dan aset marketing untuk di lapangan |

### 3.5 POS, toko, Telegram

| Fitur | Fungsi |
|-------|--------|
| POS backoffice | Penjualan tunai di cabang; setelah convert agen sering dilanjutkan ke sini untuk pembayaran awal |
| Toko B2C | Penjualan ke konsumen akhir |
| Bot Telegram POS | Order/POS lewat Telegram |

### 3.6 Akuntansi

| Fitur | Fungsi |
|-------|--------|
| COA | Bagan akun |
| Jurnal | Pencatatan akuntansi |
| Kas/bank | Mutasi kas operasional |

### 3.7 Training Academy & Marketing Center

| Fitur | Fungsi |
|-------|--------|
| Kursus, modul, materi | Pelatihan internal / partner |
| Progress learner | Dashboard dan laporan kemajuan |
| Pustaka aset | Thumbnail, materi, aset pemasaran terpusat |

### 3.8 CRM membership

Poin loyalitas **pelanggan akhir (B2C)**. Bukan jenjang partner Silver–Diamond — itu belum ada di database.

### 3.9 Chatbot TITANIE (widget in-app)

| Fitur | Fungsi |
|-------|--------|
| Persona TITANIE | Asisten yang jawab dalam Bahasa Indonesia |
| Tool `search_docs` | Baca markdown di `docs/` — fakta produk tidak diketik di prompt |
| Error aman | User lihat pesan generik; detail exception hanya di log |
| Draf penjualan tunai | Siapkan item/customer/bayar; **transaksi baru terjadi setelah konfirmasi di UI** |
| Room tour | Prompt **Apa sih ini?** / **Jelasin halaman ini** = tur dalam halaman (submenu + judul/tombol/tabel/form). **Turin fiturnya dong** = keliling modul dari ruang 1, tiap modul sidebar + maks. 2 spot landing. Overlay: spotlight, tooltip (Kembali / Lanjut / Lewati / **Selesai** di langkah terakhir). **Selesai** dan **Lewati** menutup overlay + ringkasan chat, tanpa mengirim “lanjut”. Chat baru atau pertanyaan baru (bukan lanjut/kembali/stop) menutup overlay. Panel chat disembunyikan selama overlay; maskot tetap terlihat. Suara robot laki-laki opsional (default mati) lewat tombol speaker di tur **dan** di header chat (`speech.js`). Preferensi `agent_tour_voice`. |
| Buka halaman | **Buka halaman kategori** / **buka halamannya dong** memanggil `open_page`. Tanpa filter: tab yang sama. **Buka stok minuman**: tab baru ke URL Stok yang sudah ter-filter (`category_id` atau `variant_search`). Hanya menu yang terlihat di sidebar. |
| CRUD master di chat | Tambah karyawan/customer/kategori lewat `manage_record` tanpa buka form. Contoh: **Tambah karyawan Budi, role Staff, status aktif, join hari ini**. |

Syarat hidup: `AGENT_ENABLED=true` + API key LLM.

---

## 4. Yang dikerjakan sprint NATSEU (12–14 Agustus 2026)

Bukan “tambah modul baru”, melainkan **rapikan alur inti + AI yang jujur ke dokumen**.

| Perubahan | Fungsi untuk demo / operasi |
|-----------|-----------------------------|
| Service + state machine replenishment | Alur bisa dijelaskan dan tidak bisa loncat status |
| Tombol approve | Distributor benar-benar menyetujui sebelum kirim |
| Chatbot dari `docs/` | FAQ produk = isi dokumentasi; ubah docs = ubah jawaban bot |
| Clone-to-run (PHP 8.3, composer/npm tanpa flag) | Juri/tim bisa menjalankan repo |
| Docs sprint + deck Demo Day | Satu sumber untuk slide dan chatbot |

---

## 5. Urutan demo yang mudah diikuti

1. **Cerita bisnis** — rantai Produksi → Distributor → Agen → Reseller → Konsumen.
2. **Buat / tunjukkan agen** — application AGENT → Convert (atau agen dummy `DUMMY-AG-*` jika seeder lokal sudah jalan).
3. **Replenishment** — buat order → approve → ship → receive. Tunjukkan stok dua gudang dan ide HPP transfer.
4. **Portal agen** (opsional) — `/agent-order` sebagai wajah partner.
5. **Chatbot** — tanya “apa itu TITANIE?” / “proses replenishment?” — bot harus merujuk dokumen.
6. **POS / draf jual tunai** (opsional) — chatbot siapkan, user konfirmasi di widget.

---

## 6. Dua nama layar yang sering tertukar

| URL | Entitas | Fungsi |
|-----|---------|--------|
| `/agen-order` | `ReplenishmentOrder` | Stok distributor → gudang agen |
| `/agent-order` | `SalesOrder` | Portal agen (katalog / checkout) |

Sama-sama “agen”, dokumen dan stoknya beda.

---

## 7. Yang sengaja belum ada (kalau ditanya)

| Gap | Artinya |
|-----|---------|
| Commission engine | Belum ada tabel/model komisi |
| Batch di penjualan | Jejak batch putus setelah barang di gudang — FEFO penjualan belum tegak |
| Atribusi agen/reseller di sales order | Sulit hitung komisi dari nota jual |
| Test suite otomatis | Folder `tests/` belum ada |

Rekomendasi urutan: tes replenishment dulu, lalu `batch_id` di movement/sales, lalu atribusi partner, baru mesin komisi.

---

## 8. Stack singkat (kalau ditanya teknis)

Laravel 12, PHP 8.3, PostgreSQL multi-schema, Vite 7, Tailwind 4, Alpine.js. Auth: session (admin) + Sanctum/JWT (API). Deploy Docker port **8181**.
