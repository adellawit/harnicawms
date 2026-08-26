# SCOPE — Ngoding Merdeka 2026

> **Status: TERKUNCI** sejak 13 Agustus 2026, 23:59 WIB.
> Dokumen ini adalah deklarasi komitmen di muka, bukan laporan hasil.
> Tidak boleh diubah sampai sprint berakhir 16 Agustus 2026.

| | |
|---|---|
| **Tim** | NATSEU |
| **ICON** | TITANIE |
| **Repository** | TITANIE (WMS 3.0) |
| **Codebase Challenge** | Harnica — CV Suhara Botanica |
| **Anggota** | Nathan, Riksa, Randy |
| **Periode Sprint** | 11–16 Agustus 2026 |

---

## 1. Alur Bisnis Inti yang Direstrukturisasi

**Alur Replenishment Order: Distributor → Agen**

Satu siklus penuh perpindahan barang dan nilai dari distributor ke agen:

```
Agen membuat order  →  Distributor approve  →  Distributor kirim (stok keluar, FIFO)
      →  Agen terima (stok masuk, layer HPP harga transfer)  →  Retur bila perlu
```

### Cakupan konkret

| Tahap | Entry point | Komponen |
|-------|-------------|----------|
| Buat order | `/agen-order/create` | `ReplenishmentOrderService::create()` |
| Approve | `/agen-order/{id}/approve` | `ReplenishmentOrderService::approve()` |
| Kirim | `/agen-order/{id}/ship` | `ReplenishmentStockService::ship()` |
| Terima | `/agen-order/{id}/receive/{shipmentId}` | `ReplenishmentStockService::receive()` |
| Retur | `/agen-order/{id}/return` | `ReplenishmentStockService::returnGoods()` |

Layar ini dipakai dua peran: admin distributor melihat seluruh order, sedangkan user agen otomatis dibatasi hanya pada order miliknya sendiri.

### Entitas yang terlibat

- `distribution.replenishment_orders` + `replenishment_order_items`
- `distribution.shipments` + `shipment_items` (kurir, nomor resi, status)
- `distribution.receipts` + `receipt_items`
- `distribution.returns` + `return_items`
- `product.product_variant_stock`, `product_stock_movements`, `product_cost_layers` (dampak stok dan HPP)
- `partner.agents` (pemesan), `master_data.warehouses` (gudang asal dan tujuan)

### Pekerjaan yang dilakukan

1. **Restrukturisasi** — ekstrak logika pembuatan order dari `ReplenishmentOrderController` ke service layer, dan satukan state machine status order yang sekarang tersebar sebagai string literal di controller, service, dan view menjadi satu sumber konstanta beserta aturan transisinya.
2. **Dokumentasi alur** — petakan alur ini secara utuh di `ARCHITECTURE.md` dan `PRD.md` sehingga bisa ditelusuri dari UI ke kode ke database.
3. **AI-friendly** — jadikan alur ini materi utama `AI_CONTEXT.md`, sehingga chatbot TITANIE dan AI di IDE bisa menjelaskan serta memodifikasinya tanpa penjelasan panjang.

---

## 2. Alasan Pemilihan

**Ini satu-satunya alur di repo yang benar-benar end-to-end.** Repo TITANIE punya empat jalur order berbeda: POS retail, B2C shop, Telegram bot, dan replenishment distributor-agen. Tiga yang pertama berhenti di pembayaran. Hanya replenishment yang punya siklus lengkap sampai barang berpindah gudang, stok bergerak dua arah, dan HPP terbentuk di sisi penerima.

**Paling dekat dengan inti bisnis Harnica.** Blueprint WITHerbal menempatkan jaringan reseller sebagai mesin pertumbuhan, bukan fitur sampingan. Alur distributor-agen adalah fondasi jaringan itu. Modul lain seperti akuntansi atau HR memang ada di repo, tapi bukan yang membedakan Harnica dari ERP umum.

**Punya dua peran yang bisa didemokan dalam satu alur.** Admin distributor memproses seluruh order, sementara user agen melihat layar yang sama tapi terbatas pada ordernya sendiri. Wasit bisa melihat satu transaksi dari dua sudut peran dalam satu demo.

**Menyentuh logika ERP yang paling berisiko.** Alur ini memicu mutasi stok FIFO, pembentukan cost layer, dan penerusan tanggal kadaluwarsa antar gudang — hal yang kritis untuk produk herbal dan paling mahal bila salah. Memahami dan merapikan bagian ini membuktikan pemahaman ERP, bukan sekadar merapikan tampilan.

**Realistis untuk sisa waktu.** Fondasinya sudah ada dan berfungsi. Sisa waktu dipakai untuk merestrukturisasi dan mendokumentasikan, bukan membangun dari nol sambil tetap menjalankan pekerjaan normal.

---

## 3. Yang Eksplisit TIDAK Dikerjakan

Keputusan sadar, bukan karena kehabisan waktu.

### Tidak membangun modul baru

| Item | Alasan |
|------|--------|
| **Commission engine** | Repo tidak punya satu pun tabel atau model komisi. Membangun akrual, approval, dan payout yang bisa diaudit adalah modul baru berukuran penuh, bukan restrukturisasi. Gap ini kami dokumentasikan sebagai rekomendasi roadmap. |
| **Membership level partner (Silver–Diamond)** | Yang ada di `crm` adalah poin loyalitas B2C, bukan jenjang partner. Butuh desain aturan naik level, integrasi ke training dan kepatuhan harga. Di luar satu alur inti. |
| **Wallet / saldo reseller** | Tidak ada entitas saldo di repo. Modul baru. |
| **Territory management** | Tidak ada konsep wilayah pada agen atau reseller. Modul baru. |

### Tidak menyentuh alur lain

| Item | Alasan |
|------|--------|
| **POS dan B2C shop** | Channel penjualan terpisah dengan model `SalesOrder` yang berbeda dari `ReplenishmentOrder`. Merestrukturisasi keduanya sekaligus melebihi kapasitas sprint. |
| **Telegram bot checkout** | Channel keempat, tidak berkaitan dengan alur distributor-agen. |
| **Manufacturing (BOM, production order)** | Domain hulu sebelum barang jadi masuk gudang. Berbatasan dengan scope tapi bukan bagian dari siklus distributor-agen. |
| **Accounting** | Modul sudah stabil dan bukan pembeda bisnis Harnica. |
| **HR** | Tidak berkaitan dengan alur inti. |

### Tidak mengubah teknologi

| Item | Alasan |
|------|--------|
| **Integrasi API kurir (Biteship, RajaOngkir)** | Integrasi eksternal butuh kredensial dan pengujian yang tidak muat di sisa waktu. Nomor resi tetap diinput manual seperti sekarang. |
| **Re-architecture ke microservices** | Sudah tercatat out-of-scope di `PLAN.md` sejak sebelum sprint. Panduan juga menegaskan ini restrukturisasi, bukan tulis ulang. |
| **Ganti framework, bahasa, atau database engine** | Dilarang aturan sprint. Tetap Laravel 12 + PostgreSQL. |
| **Vector database untuk chatbot** | Dilarang aturan sprint (database engine kedua). Chatbot mengambil pengetahuan langsung dari file markdown di `docs/` lewat tool, bukan lewat vector store. |

### Tidak dikejar sebagai prasyarat

| Item | Alasan |
|------|--------|
| **Test suite otomatis** | Repo belum punya `tests/` maupun `phpunit.xml` (tercatat sebagai blocker B-01 di `STATUS.md`). Tidak termasuk Definition of Done panitia maupun bobot penilaian. Dikerjakan hanya bila waktu tersisa, dan tetap dilaporkan sebagai gap yang diketahui. |
| **Deployment** | Panduan menyatakan demo dijalankan lokal. |

---

## 4. Deliverable Sprint

| Deliverable | Lokasi |
|-------------|--------|
| Scope terkunci | `docs/SCOPE.md` (dokumen ini) |
| Product requirement | `docs/PRD.md` |
| Arsitektur dan alur bisnis | `docs/ARCHITECTURE.md` |
| Konteks untuk AI | `docs/AI_CONTEXT.md` |
| Prompt library | `docs/PROMPTS.md` |
| Cara kerja tim dengan AI | `docs/VIBE.md` |
| Laporan penggunaan token | `docs/AI_USAGE.md` |
| Chatbot TITANIE | Widget di dalam UI aplikasi, pengetahuan dari `docs/` |
| Repo yang bisa dijalankan | `README.md` clone-to-run tanpa bertanya ke tim |

---

## 5. Perubahan Database

Sesuai aturan sprint, penambahan tabel wajib lewat migration yang ter-commit dan dicatat di `ARCHITECTURE.md`.

**Rencana saat ini: tidak ada tabel baru.** Alur replenishment sudah punya struktur data yang memadai. Bila dalam pelaksanaan ternyata dibutuhkan kolom tambahan, perubahannya bersifat aditif (tidak menghapus kolom, tidak mengubah tipe data yang merusak data lama) dan akan dicatat di `ARCHITECTURE.md`.

---

## 6. Referensi

- `docs/ARCHITECTURE.md` — arsitektur sistem dan peta modul
- `docs/STATUS.md` — kesehatan proyek dan blocker
- `docs/FEATURE_BREAKDOWN.md` — inventori fitur per modul
- `CONTRIBUTING.md` — alur git dan standar kontribusi
