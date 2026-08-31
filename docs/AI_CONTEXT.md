# AI_CONTEXT — Konteks Repo untuk AI

> Dokumen ini ditulis untuk dibaca AI (dan manusia baru) sebelum menyentuh kode.
> Tujuannya satu: AI paham konvensi repo tanpa perlu dijelaskan panjang lebar.
>
> Dokumen ini juga menjadi sumber pengetahuan chatbot TITANIE lewat tool `search_docs`.

---

## 1. Aplikasi Ini Apa

TITANIE adalah sistem ERP distributor berbasis jaringan reseller untuk **CV Suhara Botanica (Harnica)**, distributor produk herbal. Aplikasinya modular monolith Laravel yang menangani satu rantai bisnis utuh:

**Produksi → Gudang Distributor → Agen → Reseller → Konsumen**

Di dalamnya ada master produk, inventori multi-gudang, produksi berbasis BOM, jaringan partner (agen dan reseller), order replenishment distributor ke agen, POS, toko B2C, akuntansi, training academy, dan pelaporan.

Ada dua kelompok pengguna:

- **Internal / backoffice** — owner, admin, gudang, finance, marketing. Masuk lewat guard `web`.
- **Partner (agen)** — memesan barang ke distributor lewat portal `/agent-order`. Masuk lewat guard `customer`.

---

## 2. Stack

| Lapisan | Teknologi |
|---------|-----------|
| Backend | Laravel 12, PHP 8.3+ |
| Database | PostgreSQL 14+, multi-schema |
| Frontend | Blade server-rendered + Alpine.js, Vite 7, Tailwind CSS 4, Bootstrap-based admin theme |
| Auth | Session (web + customer guard), Sanctum, JWT untuk API |
| AI | DeepSeek atau OpenAI-compatible, lewat `LlmProviderManager` |

Menjalankan lokal: lihat [../README.md](../README.md). Ringkasnya butuh `composer install`, `npm install`, `key:generate`, `jwt:secret`, `migrate`, lalu **`db:seed` yang wajib** karena menu dan permission digerakkan database.

---

## 3. Peta Direktori

```
app/
  Http/Controllers/Admin/      Controller modul backoffice (per domain)
  Http/Controllers/Agent/      Portal agen (guard customer)
  Http/Controllers/Customer/   Toko B2C (guard customer)
  Http/Controllers/Ai/         Endpoint chatbot
  Http/Middleware/             CheckPermissions, EnsureCustomerIsAgent, dll
  Models/                      Eloquent, sebagian dikelompokkan per domain
  Services/                    Logika bisnis — tempat menaruh aturan, bukan controller
  Support/                     Helper stateless (WmsContext, ChatAccess)
database/
  migrations/<domain>/         Migration dikelompokkan per schema
  seeders/                     MenuSeeder adalah sumber navigasi
resources/views/
  admin/<modul>/               Halaman backoffice
  agent/order/                 Portal agen
  layouts/                     app, navigation, agent-order, print
routes/
  web.php                      Entry utama, meng-include file lain di bagian bawah
  distribution.php             Produksi dan distribusi
  agent.php                    Portal agen
  customer.php                 Toko B2C
  training.php, marketing.php  Academy dan asset marketing
docs/                          Dokumentasi — sumber pengetahuan chatbot
```

---

## 4. Konvensi Database

**PostgreSQL multi-schema.** Setiap bounded context punya schema sendiri: `auth`, `master_data`, `customer`, `partner`, `product`, `transaction`, `distribution`, `accounting`, `crm`, `manufacturing`, `marketing`, `training`, `human_resources`, `configuration`, dan `public` untuk data referensi bersama.

Migration diletakkan di subfolder sesuai schema (`database/migrations/partner/`, dan seterusnya). Subfolder ini didaftarkan manual di `AppServiceProvider::boot()` lewat `loadMigrationsFrom()` — **kalau membuat folder schema baru, wajib menambahkannya di sana**, kalau tidak migration-nya tidak akan pernah jalan.

Setiap model mengikuti pola ini:

```php
class Agent extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'partner.agents';   // selalu prefix schema
}
```

Aturan yang berlaku hampir di semua tabel:

- **Primary key UUID**, bukan auto-increment. Ada fungsi `uuid_v7()` di database.
- **Soft delete** — kolom `deleted_at`.
- **Kolom audit** — `created_by`, `updated_by`, `deleted_by` berisi UUID user.
- **Nominal uang** pakai `decimal` dengan presisi 4, di-cast `decimal:4` di model.
- **Foreign key lintas schema** itu normal dan dipakai luas.

---

## 5. Otorisasi dan Navigasi

Permission berbasis **nama menu**, bukan nama role.

```php
Route::get('/', [ReplenishmentOrderController::class, 'index'])
    ->name('replenishment.index')
    ->middleware('permission:Replenishment,is_read');
```

Aksi yang tersedia: `is_read`, `is_create`, `is_update`, `is_delete`.

Alurnya: saat login, `SidebarService` membaca tabel `auth.iam_has_accesses` dan menaruh hasilnya di `session('permissions')` dengan kunci nama menu. Middleware `CheckPermissions` dan Blade directive `@permission('Nama Menu', 'is_create')` membaca session itu.

Role bernama `super admin` melewati semua pengecekan.

**Navigasi digerakkan database.** Sidebar dirender dari `session('sidebars')`, bukan dari file Blade statis. Menambah halaman baru berarti menambah entri menu lewat seeder atau migration, bukan sekadar menambah route.

---

## 6. Di Mana Menaruh Kode

Controller tipis, logika di service.

| Jenis kode | Tempat |
|------------|--------|
| Validasi request, redirect, flash message | Controller |
| Aturan bisnis, transaksi database, mutasi stok | `app/Services/<Domain>/` |
| Helper stateless tanpa dependency | `app/Support/` |
| Query kompleks yang dipakai berulang | Scope di model atau service |

Semua operasi yang menyentuh lebih dari satu tabel dibungkus `DB::transaction()`.

---

## 7. Alur Bisnis Inti: Replenishment Distributor ke Agen

Ini alur yang menjadi fokus restrukturisasi sprint (lihat [SCOPE.md](SCOPE.md)).

```
Agen buat order  →  Distributor approve  →  Distributor kirim  →  Agen terima  →  Retur (opsional)
   submitted           approved              shipped              received
```

Alur ini dijalankan dari layar admin `/agen-order` (guard `web`). Agen yang punya akun user bisa masuk ke layar yang sama, tapi otomatis hanya melihat dan membuat order miliknya sendiri — lihat pengecekan `Auth::user()?->partnerAgent` di controller.

### Status order

Nilai `replenishment_orders.status` didefinisikan di `App\Support\ReplenishmentStatus`:

| Status | Arti |
|--------|------|
| `draft` | Belum diajukan |
| `submitted` | Diajukan agen, menunggu persetujuan |
| `approved` | Disetujui distributor |
| `shipped` | Sebagian atau seluruh barang dikirim |
| `partially_received` | Sebagian diterima agen |
| `received` | Seluruh qty diterima |
| `cancelled` | Dibatalkan |

Transisi yang diizinkan diatur di konstanta `TRANSITIONS` pada class yang sama. Aturan pentingnya: order harus `approved` sebelum bisa dikirim, dan retur hanya boleh setelah barang diterima agen.

Status shipment: `preparing`, `in_transit`, `delivered`. Status pembayaran: `unpaid`, `partial`, `paid`.

### Dampak stok

Ditangani `App\Services\Distribution\ReplenishmentStockService`:

- **`ship()`** — stok keluar dari gudang Barang Jadi distributor memakai FIFO. Tanggal kadaluwarsa dari cost layer yang terpakai diteruskan ke `shipment_items.expiry_date` supaya FEFO tetap terjaga sampai ke agen. Nomor resi wajib diisi.
- **`receive()`** — stok masuk ke gudang agen pada **harga transfer** (`unit_price` di order item). Harga transfer inilah yang jadi HPP agen, dan cost layer baru terbentuk di sisi agen.
- **`returnGoods()`** — stok keluar dari agen, masuk kembali ke gudang Barang Jadi distributor pada harga transfer yang sama.

Semua mutasi stok lewat `StockMutationService::inbound()` dan `::outbound()`, tidak pernah dengan mengubah kolom stok secara langsung.

### File yang terlibat

```
routes/distribution.php                                   prefix /agen-order
app/Http/Controllers/Admin/ReplenishmentOrderController    controller (admin + user agen)
app/Services/Distribution/ReplenishmentOrderService        pembuatan order & transisi status
app/Services/Distribution/ReplenishmentStockService        pergerakan stok
app/Support/ReplenishmentStatus                            konstanta & aturan transisi status
app/Support/WmsContext                                     resolusi distributor, agen, gudang
app/Models/ReplenishmentOrder, ReplenishmentOrderItem
app/Models/Shipment, ShipmentItem, Receipt, ReceiptItem, ReturnOrder, ReturnItem
resources/views/admin/replenishment/                       index, create, show
```

### Jangan tertukar dengan portal agen

Portal agen di `/agent-order` (guard `customer`, `AgentOrderController`) adalah alur **berbeda**: itu toko web B2B yang menghasilkan `SalesOrder` dengan `order_type = 'web-order'`, bukan `ReplenishmentOrder`. Dua alur ini kebetulan sama-sama melayani agen tapi memakai entitas, tabel, dan guard yang berbeda.

---

## 8. Konvensi Stok dan Biaya

- Stok disimpan **per varian per gudang** di `product.product_variant_stock`.
- Setiap perubahan meninggalkan jejak di `product.product_stock_movements` dengan `reference_type` dan `reference_id` polimorfik.
- Harga pokok memakai **cost layer FIFO** di `product.product_cost_layers`, lengkap dengan tanggal kadaluwarsa sehingga FEFO bisa dijalankan.
- Konteks gudang dan business unit diambil lewat `App\Support\WmsContext` — jangan mencari gudang dengan query manual, pakai helper ini.

---

## 9. Subsistem Chatbot

Chatbot TITANIE terpasang di dalam UI aplikasi, bukan halaman terpisah.

```
resources/views/components/ai/chat-widget.blade.php   widget, di-include layouts/app.blade.php
routes/web.php  POST /agent/chat                      endpoint
app/Http/Controllers/Ai/ChatController                controller
app/Services/Ai/WmsAgentService                       orkestrasi percakapan + tool calling
app/Services/Ai/AgentToolRegistry                     daftar tool
app/Services/Ai/Tools/                                implementasi tool
app/Services/Ai/Docs/DocsKnowledgeBase                pembaca dan pencari markdown docs/
config/agent.php                                      konfigurasi
```

**Prinsip penting:** pengetahuan chatbot tentang produk TIDAK ditulis di system prompt. System prompt hanya berisi aturan perilaku. Semua fakta diambil saat runtime dari file markdown di `docs/` lewat tool `search_docs`. Konsekuensinya, memperbaiki jawaban chatbot dilakukan dengan memperbaiki dokumentasi, bukan dengan mengedit prompt.

Panduan operator (bisa apa / tidak bisa apa, syarat widget, izin tool): [AI_BOT.md](AI_BOT.md).

Menambah tool baru: buat class di `app/Services/Ai/Tools/` yang meng-extend `AbstractAgentTool`, daftarkan di constructor `AgentToolRegistry`, lalu tambahkan namanya ke `AGENT_ALLOWED_TOOLS`.

Action penjualan: tool `manage_sale` menyiapkan draf (item, customer, pembayaran tunai). Pembuatan transaksi hanya terjadi setelah user menekan konfirmasi di widget (`POST /agent/actions/confirm`), memakai `PosCheckoutService` yang sama dengan POS.

CRUD data lewat chat: tool `manage_record` mengolah hampir semua menu admin (HR, customer, partner, produk, gudang, bisnis, pembayaran, CRM, COA, settings, training, marketing) sesuai permission. Create master **selesai di chat** (karyawan: nama/email/role/jabatan/divisi/tanggal/status; kode+NIK+username+password digenerate bila perlu). Jangan buka form kecuali user minta. Transaksi POS tetap `manage_sale`. Stok/jurnal/PO/produksi/replenishment dari chat bersifat baca agar ledger tidak rusak.

Room tour: tool `guide_tour` (`here` / `start` / `next` / `prev` / `stop`) mengembalikan selector UI. `here` (“Apa sih ini?” / “Jelasin halaman ini”) = tur dalam halaman: menu induk, submenu, lalu 3–6 spot UI. `start` (“Turin fiturnya dong”) = 11 modul, masing-masing sidebar + maksimal 2 spot landing. Widget menampilkan overlay Product Tour di admin sungguhan. Panel chat disembunyikan selama overlay; FAB maskot tetap terlihat. Tombol **Selesai** (langkah terakhir) dan **Lewati** menutup overlay secara lokal lewat `POST /agent/tour/stop` — tidak mengirim “lanjut” ke `/agent/chat`. `start` selalu dari ruang 1; chat baru atau pertanyaan non-kontrol mereset tur. Suara opsional (default mati): teks Bahasa Indonesia, suara laki-laki Titanie lewat Web Speech API (`speech.js`), kunci `agent_tour_voice`; header chat punya tombol speaker yang sama. Widget mengirim `page_path`, `page_title`, dan `page_menu` di setiap `POST /agent/chat`. Fakta modul tetap dari `search_docs`; tur hanya memetakan ruangan.

Buka halaman: tool `open_page` (“buka halaman kategori”, “buka stok minuman”, “buka halamannya dong”) mengembalikan `{ success, url, label, needs_navigation, new_tab, filters }`. Tanpa filter: widget `location.assign` (tab yang sama). Dengan filter: `window.open` tab baru same-origin (fallback `location.assign` jika diblokir) ke path plus query yang halaman sudah honor (`category_id`, `variant_search` di Stok, `product` di Items). Hanya menu yang user bisa lihat (`is_read`). “Halamannya” = topik percakapan terakhir. Bukan tur — overlay tidak menyala.

---

## 10. Konvensi Frontend

- Blade server-rendered. Layout admin: `resources/views/layouts/app.blade.php`. Portal agen: `layouts/agent-order.blade.php`.
- Interaktivitas ringan pakai Alpine.js dan jQuery yang sudah ada di theme; tidak ada SPA.
- Halaman modul mengikuti pola `admin/<modul>/index.blade.php`, `insert.blade.php`, `edit.blade.php`, dengan partial diawali underscore.
- Aset lewat Vite. Jalankan `npm run dev` saat pengembangan.

---

## 11. Jebakan yang Sering Bikin Salah

1. **Lupa `db:seed`.** Aplikasi menyala tapi sidebar kosong dan tidak bisa login. Ini bukan bug.
2. **Menambah route tanpa menambah menu.** Halaman jadi tidak terlihat dan permission-nya tidak pernah cocok, karena permission dikunci ke nama menu.
3. **Membuat folder migration schema baru tanpa mendaftarkannya** di `AppServiceProvider::boot()`.
4. **Lupa prefix schema** di `$table`. Query akan mencari di schema `public` dan gagal.
5. **Mengubah kolom stok langsung.** Selalu lewat `StockMutationService` supaya ledger dan cost layer tetap konsisten.
6. **Menganggap `/agent-order` dan `/agen-order` sama.** Keduanya beda alur, beda guard, beda tabel: `/agent-order` adalah toko web B2B agen (guard `customer`, menghasilkan `SalesOrder`), sedangkan `/agen-order` adalah layar replenishment (guard `web`, menghasilkan `ReplenishmentOrder`). Penamaan ini membingungkan dan sengaja dipertahankan agar URL lama tidak rusak.
7. **Menganggap `/agent/chat` berkaitan dengan partner agen.** Itu endpoint chatbot AI, bukan modul agen.

---

## 12. Yang Tidak Ada di Repo Ini

Penting supaya AI tidak berhalusinasi:

- **Tidak ada modul komisi.** Tidak ada tabel, model, atau service komisi. Ekonomi partner ditangani lewat tier harga (`partner.foredi_price_tiers`) dan `partner.cutting_price_configs`, yang isinya aturan harga, bukan akrual komisi.
- **Tidak ada jenjang membership partner.** Yang ada di schema `crm` adalah poin loyalitas pelanggan B2C.
- **Tidak ada integrasi API kurir.** Nomor resi diinput manual.
- **Tidak ada test suite.** Belum ada `tests/` maupun `phpunit.xml`.
- **Tidak ada message broker.** Semua sinkron.

---

## 13. Aturan Kerja

- Immutability diutamakan; hindari mengubah objek di tempat.
- Validasi semua input di batas sistem.
- Tidak ada secret di kode; semua lewat environment variable.
- Diff seminimal mungkin, ikuti pola Laravel dan Blade di sekitarnya.
- Jangan commit `.env`, kredensial, atau seeder demo di `app/Services/Demo/`.
- Format PHP dengan `./vendor/bin/pint`.

---

## 14. Dokumen Terkait

| Dokumen | Isi |
|---------|-----|
| [SCOPE.md](SCOPE.md) | Scope sprint yang terkunci |
| [PRD.md](PRD.md) | Kebutuhan produk alur replenishment |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Arsitektur sistem |
| [AI_BOT.md](AI_BOT.md) | Kemampuan dan batas chatbot in-app |
| [PROMPTS.md](PROMPTS.md) | Prompt library untuk kerja sehari-hari |
| [VIBE.md](VIBE.md) | Cara tim bekerja dengan AI |
| [STATUS.md](STATUS.md) | Kesehatan proyek dan blocker |
