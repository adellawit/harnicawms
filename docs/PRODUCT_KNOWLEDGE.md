# PRODUCT_KNOWLEDGE — Lima Jawaban Wajib

> Jawaban resmi tim NATSEU untuk lima pertanyaan Product Knowledge di Demo Day.
>
> Dokumen ini sengaja diletakkan di `docs/` supaya **deck dan chatbot TITANIE membaca
> sumber yang sama persis**. Kalau jawaban di deck berubah, ubah di sini — bukan di slide,
> bukan di system prompt.

---

## 1. Jelasin, apa sih ini?

**TITANIE adalah ERP distribusi berbasis jaringan reseller untuk CV Suhara Botanica (Harnica), distributor produk herbal.**

Harnica tidak menjual langsung ke konsumen sebagai jalur utama. Barang bergerak lewat rantai partner:

```
Produksi → Gudang Distributor → Agen → Reseller → Konsumen
```

Aplikasinya modular monolith Laravel 12 di atas PostgreSQL multi-schema, dengan admin UI Blade server-rendered. Satu aplikasi, banyak bounded context, masing-masing punya schema database sendiri: `product`, `partner`, `distribution`, `transaction`, `accounting`, `manufacturing`, `crm`, `training`, dan lainnya.

Modul yang aktif di repo: master produk, inventori multi-gudang, purchasing, produksi berbasis BOM, jaringan partner, replenishment distributor ke agen, POS, toko B2C, akuntansi, training academy, marketing center, dan pelaporan.

Ada dua kelompok pengguna dengan guard berbeda:

| Pengguna | Guard | Masuk lewat |
|----------|-------|-------------|
| Internal — owner, admin, gudang, finance, marketing | `web` | Backoffice `/admin` |
| Partner agen | `customer` | Portal `/agent-order` |

Yang kami pegang di sprint ini bukan seluruh repo, melainkan **satu alur bisnis inti** yang sudah kami kunci di [SCOPE.md](SCOPE.md) sejak 13 Agustus: alur Replenishment Order dari distributor ke agen.

---

## 2. Proses bisnisnya gimana?

Alur inti yang kami restrukturisasi adalah **satu siklus penuh perpindahan barang dan nilai dari distributor ke agen**.

```
Agen buat order  →  Distributor approve  →  Distributor kirim (stok keluar, FIFO)
    →  Agen terima (stok masuk, cost layer harga transfer)  →  Retur bila perlu
```

Setiap tahap punya entry point dan pemilik logika yang jelas:

| Tahap | Status setelahnya | Pemilik logika |
|-------|-------------------|----------------|
| Agen membuat order | `submitted` | `ReplenishmentOrderService::create()` |
| Distributor menyetujui | `approved` | `ReplenishmentOrderService::approve()` |
| Distributor mengirim | `shipped` | `ReplenishmentStockService::ship()` |
| Agen menerima | `partially_received` / `received` | `ReplenishmentStockService::receive()` |
| Retur barang | — | `ReplenishmentStockService::returnGoods()` |

**Yang membuat alur ini berat secara ERP bukan perpindahan statusnya, tapi efek sampingnya.** Saat distributor mengirim, stok keluar dari gudang asal memakai FIFO dan cost layer-nya dikonsumsi. Saat agen menerima, stok masuk ke gudang tujuan dengan **cost layer baru senilai harga transfer**, bukan HPP distributor — karena bagi agen, harga beli dari distributor itulah modalnya. Tanggal kadaluwarsa ikut diteruskan antar gudang, dan ini penting karena produknya herbal.

Semua mutasi tercatat di `product_stock_movements`, jadi pergerakan stok bisa ditelusuri mundur dari dokumen mana pun.

Satu hal yang sering membingungkan pembaca baru dan sudah kami catat eksplisit di [AI_CONTEXT.md](AI_CONTEXT.md): ada dua rute dengan nama mirip. `/agen-order` adalah layar admin untuk `ReplenishmentOrder`, sedangkan `/agent-order` adalah portal agen yang membuat `SalesOrder`. Nama mirip, entitas berbeda.

---

## 3. Fitur apa yang kalian tambahkan?

Sprint ini restrukturisasi, bukan lomba menambah fitur. Yang benar-benar **baru** ada dua, dan keduanya menjawab Definition of Done panitia.

### Chatbot TITANIE — pengetahuan dari `docs/`, bukan dari system prompt

Widget chat sudah ada di repo sebelum sprint, tapi pengetahuannya hanya dari query database. Kami tambahkan jalur pengetahuan baru:

- `app/Services/Ai/Docs/DocsKnowledgeBase.php` — membaca markdown di `docs/`, memecahnya per section, dan mencari yang relevan lewat pencocokan token.
- `app/Services/Ai/Tools/SearchDocsTool.php` — tool `search_docs`, didaftarkan di `AgentToolRegistry` seperti tool lain. `requiredPermission()` mengembalikan `null` supaya FAQ bisa diakses semua user.

System prompt di `WmsAgentService` hanya berisi **aturan perilaku**: wajib panggil `search_docs` untuk pertanyaan seputar aplikasi, jawab dalam Bahasa Indonesia, dan bilang tidak tahu kalau jawabannya tidak ada di `docs/`. Tidak ada satu pun fakta produk yang diketik di prompt. Konsekuensinya jelas dan memang disengaja: **kalau dokumentasi kami buruk, jawaban chatbot ikut buruk.**

Kami tidak menambah vector database — itu dilarang aturan sprint sebagai database engine kedua, dan untuk korpus sebesar folder `docs/` pencarian token sudah memadai.

### State machine status yang bisa ditegakkan

`app/Support/ReplenishmentStatus.php` — satu sumber konstanta status order, pengiriman, dan pembayaran, lengkap dengan tabel transisi yang sah. Sebelumnya nilai status berupa string literal yang tersebar di controller, service, dan view.

Efek sampingnya menemukan **bug nyata**: tombol approve tidak pernah ada di UI, sehingga order praktis bisa langsung dikirim tanpa persetujuan distributor. Sekarang tombolnya ada, dan `canShip()` menolak order yang belum `approved`.

### Selain itu

- Perbaikan `README.md` supaya benar-benar clone-to-run — `jwt:secret` dan `db:seed` sebelumnya tidak disebut, padahal tanpa seed tidak ada user untuk login dan sidebar kosong.
- Perbaikan bug impor `LlmProviderManager` yang hilang di `AbstractAgentTool`.
- Tiga skrip smoke test di `scripts/` untuk menguji basis pengetahuan dan ketahanan chatbot.

---

## 4. Apa yang kalian implementasikan?

Bedanya dengan pertanyaan 3: di sini yang diceritakan **restrukturisasi kode dan dokumentasinya**, bukan fitur barunya.

### Restrukturisasi alur replenishment

| Sebelum | Sesudah |
|---------|---------|
| Logika pembuatan order menumpuk di `ReplenishmentOrderController` | Pindah ke `App\Services\Distribution\ReplenishmentOrderService` |
| Status berupa string literal tersebar di controller, service, dan view | Satu sumber di `App\Support\ReplenishmentStatus` dengan aturan transisi |
| Transisi status tidak divalidasi | `validateTransition()` menolak lompatan status yang tidak sah |
| Penomoran order di controller | Pindah ke service, dekat dengan logika yang memakainya |
| Approve tidak punya tombol di UI | Tombol approve muncul saat status `submitted` |

Controller sekarang tipis: validasi request, delegasi ke service, redirect. Ini mengikuti konvensi yang memang sudah berlaku di repo — logika bisnis tinggal di `app/Services/`, bukan di controller.

### Dokumentasi sebagai deliverable, bukan lampiran

Tujuh dokumen di `docs/`, masing-masing punya peran yang berbeda:

| Dokumen | Peran |
|---------|-------|
| [SCOPE.md](SCOPE.md) | Komitmen di muka, terkunci 13 Agustus |
| [PRD.md](PRD.md) | Kebutuhan fungsional dan aturan bisnis alur replenishment |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Peta sistem, bounded context, subsistem AI |
| [AI_CONTEXT.md](AI_CONTEXT.md) | Konvensi repo untuk dibaca AI sebelum menyentuh kode |
| [PROMPTS.md](PROMPTS.md) | Prompt library siap pakai |
| [VIBE.md](VIBE.md) | Cara tim bekerja dengan AI |
| [AI_USAGE.md](AI_USAGE.md) | Laporan tool dan biaya |

`AI_CONTEXT.md` sengaja ditulis **sebelum** chatbot diuji. Urutan ini disengaja: kualitas dokumen langsung terlihat dari kualitas jawaban bot, jadi dokumentasi tidak bisa asal jadi.

### Pengujian yang dijalankan

- `scripts/ai-docs-knowledge-test.php` — memastikan dokumen wajib terbaca, pertanyaan Product Knowledge menemukan section yang benar, dan pertanyaan di luar cakupan tidak dijawab.
- `scripts/ai-chat-resilience-test.php` — agent dimatikan, input kosong, input sangat panjang, dan API key salah. Semua harus mengembalikan pesan sopan tanpa membocorkan detail internal.
- `scripts/ai-chat-live-check.php` — uji end-to-end ke provider sungguhan dengan lima pertanyaan Product Knowledge.

---

## 5. Pengembangannya ke mana ke depannya?

Ini pertanyaan yang paling menentukan, jadi jawabannya menunjuk bagian repo yang spesifik — bukan "scalability" atau "lebih banyak fitur".

### Lima gap konkret yang kami temukan

**1. Commission engine tidak ada sama sekali.**
Nol tabel, nol model. Pencarian kata `commission` di seluruh `app/` dan `database/` tidak menghasilkan apa pun. Ekonomi partner saat ini hanya ditangani `CuttingPriceConfig`, yang sebenarnya aturan penetapan harga, bukan akrual komisi yang bisa diaudit. Untuk bisnis yang mesin pertumbuhannya jaringan reseller, ini lubang paling besar. Yang dibutuhkan: tabel akrual komisi, status approval, dan payout yang bisa direkonsiliasi ke akuntansi.

**2. Batch tidak nyambung ke penjualan, jadi FEFO tidak bisa ditegakkan.**
Tabel `product_batches` ada dan `batch_id` dipakai di `purchase_order_items`, tapi **tidak ada di `product_stock_movements` maupun `sales_order_items`**. Artinya begitu barang masuk gudang, jejak batch-nya putus. Untuk produk herbal yang punya tanggal kadaluwarsa, ini bukan masalah teoretis — barang mendekati expired tidak bisa diprioritaskan keluar, dan penarikan produk per batch tidak bisa ditelusuri. Perbaikannya aditif: tambah kolom `batch_id` di dua tabel itu, lalu tegakkan FEFO saat picking.

**3. Sales order tidak punya atribusi partner.**
`sales_order_items` hanya terhubung ke `customer_id`. Tidak ada `agent_id` maupun `reseller_id`. Konsekuensinya bukan cuma laporan: tanpa atribusi, tidak ada pemicu alami untuk menghitung komisi. Gap ini dan gap nomor 1 harus diselesaikan bersamaan.

**4. Membership yang ada hanya B2C, bukan jenjang partner.**
`crm.membership_point_configurations` adalah poin loyalitas pelanggan akhir. Jenjang partner Silver sampai Diamond yang ada di blueprint Harnica belum punya representasi apa pun di database.

**5. Repo belum punya test suite.**
Tidak ada folder `tests/`, tidak ada `phpunit.xml`. Sudah tercatat sebagai blocker B-01 di [STATUS.md](STATUS.md) sejak sebelum sprint. Untuk alur yang menyentuh mutasi stok dan pembentukan HPP, tidak adanya jaring pengaman otomatis adalah risiko yang riil — satu regresi diam-diam bisa merusak nilai persediaan.

### Urutan yang kami rekomendasikan

| Prioritas | Pekerjaan | Alasan |
|-----------|-----------|--------|
| 1 | Test suite untuk alur replenishment | Prasyarat agar perubahan berikutnya aman |
| 2 | `batch_id` di stock movement dan sales order item | Aditif, murah, menutup risiko kadaluwarsa |
| 3 | Atribusi partner di sales order | Fondasi untuk komisi |
| 4 | Commission engine | Modul penuh, butuh desain sendiri |
| 5 | Jenjang partner dan wallet | Setelah ekonomi partner punya dasar data |

Kami menaruh test suite di urutan pertama secara sadar meskipun tidak masuk penilaian sprint. Alasannya sederhana: tiga pekerjaan setelahnya semuanya menyentuh nilai persediaan dan uang.

---

## Catatan Konsistensi

Chatbot TITANIE menjawab lima pertanyaan di atas dengan membaca dokumen ini lewat tool `search_docs`. Tidak ada jawaban yang ditulis ulang di system prompt maupun di slide. Kalau ada yang perlu diperbaiki, perbaiki di sini — deck dan chatbot ikut berubah dengan sendirinya.
