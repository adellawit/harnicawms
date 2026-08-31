# AI_BOT — Apa yang Bisa dan Tidak Bisa Dilakukan Chatbot TITANIE

> Panduan praktis untuk operator, demo, dan developer: **bot in-app bisa apa, dan di mana batasnya.**
> Isi mengikuti kode yang hidup sekarang (`config/agent.php`, `config/agent_records.php`, tool di `app/Services/Ai/Tools/`, widget di `resources/views/components/ai/chat-widget.blade.php`).
>
> Konteks teknis repo: [AI_CONTEXT.md](AI_CONTEXT.md) §9. Arsitektur: [ARCHITECTURE.md](ARCHITECTURE.md) §3.5.

---

## 1. Ini apa

TITANIE adalah asisten di **panel admin** (bukan halaman terpisah, bukan portal agen). Tombol maskot di pojok layar membuka panel chat. Bot menjawab dalam Bahasa Indonesia, memakai tool di server, lalu merangkum hasilnya.

Dua jenis kerja yang perlu dibedakan:

| Jenis | Sumber | Contoh |
|-------|--------|--------|
| FAQ / cara pakai aplikasi | File markdown di `docs/` lewat tool `search_docs` | “Aplikasi ini untuk apa?”, “Gimana alur order agen?” |
| Data operasional | Database cabang aktif lewat tool data | Cek stok, cari customer, draf jual tunai, CRUD master, draf PO |

Fakta produk **tidak** ditulis di system prompt. Kalau jawaban FAQ salah, perbaiki dokumen di `docs/`, bukan prompt. Folder `docs/superpowers/` sengaja dikecualikan (handoff historis, bukan keadaan sistem sekarang).

---

## 2. Syarat bot muncul dan merespons

Semua syarat ini harus terpenuhi:

1. User **login** di backoffice (guard `web`). Widget hanya di-include dari `layouts/app.blade.php` — portal `/agent-order` tidak punya chat.
2. `AGENT_ENABLED=true` **dan** `AGENT_WIDGET_ENABLED=true` di `.env`.
3. Provider LLM terkonfigurasi (`AI_PROVIDER` + API key). Tanpa itu widget bisa tampil tapi chat menolak.
4. Izin menu **AI Assistant** `is_read`, atau role `super admin`.
5. Untuk data/aksi operasional: **cabang aktif** sudah dipilih di profil. Tanpa cabang, bot akan minta user memilih cabang dulu.

Endpoint chat: `POST /agent/chat` (bukan modul partner agen). Konfirmasi aksi: `POST /agent/actions/confirm`. Rate limit default 30 pesan/menit. Panjang pesan max 2.000 karakter.

Percakapan tersimpan per user. Reload atau buka lagi widget memulihkan thread terakhir. Ikon jam di header membuka **Riwayat chat**; plus (+) mulai thread baru tanpa menghapus riwayat. Daftar thread: `GET /agent/conversations`. Isi thread: `GET /agent/conversations/{id}/messages`.

Panggilan LLM di-retry **sekali** jika timeout atau HTTP 5xx. Kalau LLM gagal **setelah** tulis DB berhasil, hasil tulis tetap dikirim ke user (bukan pesan error generik). Pesan error membedakan jaringan, layanan AI, dan validasi — tanpa membocorkan endpoint atau stack.

---

## 3. Yang BISA

### 3.1 FAQ dari dokumentasi

Tanya apa itu aplikasi, alur bisnis, modul, cara pakai fitur, arsitektur, atau scope sprint. Bot wajib mencari di `docs/` dulu (`search_docs`). Jawaban ke user tidak menampilkan nama file atau “(sumber: …)” — sitasi hanya dipakai internal.

Kalau tidak ada di dokumen, bot harus bilang tidak tahu — bukan mengarang.

Chip saran di panel (klik langsung mengirim):

- Apa sih ini?
- Turin fiturnya dong
- Buka halaman kategori
- Gimana alur order agen?
- Tampilkan stok
- Tambahkan divisi Management
- Daftar produk
- Jual 2 kopi arabica tunai

### 3.2 Baca data operasional (cabang aktif)

| Tool | Izin menu | Yang dikembalikan |
|------|-----------|-------------------|
| `search_product` | Product `is_read` | Nama, SKU, harga, stok |
| `get_stock` | Stock `is_read` | Ringkasan cabang + max 10 SKU (query kosong) atau filter nama/SKU |
| `search_customer` | Customer `is_read` | Kode dan nama pelanggan |
| `get_sales_summary` | Sales Summary `is_read` | Jumlah transaksi dan total (default hari ini) |
| `get_help` | tidak perlu | Daftar kemampuan dan contoh pertanyaan |
| `guide_tour` | tidak perlu | Selector spotlight + langkah tur halaman |
| `open_page` | menu tujuan `is_read` | URL halaman admin, opsional query filter; tab baru jika ada filter |

Hasil pencarian produk/stok/customer tampil sebagai tabel di bubble chat.

### 3.3 Penjualan tunai — draf dulu, transaksi setelah konfirmasi

Tool `manage_sale` (izin POS `is_create`) **tidak** membuat transaksi. Alurnya:

1. Tambah item (bisa berulang) → opsional set customer → propose.
2. Widget menampilkan kartu **Buat transaksi** / **Batal**.
3. Baru setelah user menekan konfirmasi, server memanggil `PosCheckoutService` yang sama dengan POS (`POST /agent/actions/confirm`).

Batasan penjualan dari chat:

- Hanya pembayaran **tunai** (`CASH` / `TUNAI`) di cabang aktif.
- Nomor penjualan memakai prefix `AIT` (bisa diubah lewat `AGENT_SALES_NUMBER_PREFIX`).
- Draf disimpan di cache percakapan, kedaluwarsa default 60 menit.
- Walk-in customer boleh (customer kosong).

### 3.4 CRUD master lewat chat (`manage_record`)

Hampir semua menu admin bisa diolah lewat chat **jika user punya izin menu yang sama** (`is_read` / `is_create` / `is_update` / `is_delete`). Operasi: list, get, create, update, delete, restore (soft delete), plus `post` untuk jurnal draf yang seimbang.

**Form di chat:** create master (karyawan, pelanggan, produk, …) **selesai di chat**. Kalau field wajib kurang, tool mengembalikan `{ success:false, missing:[...] }` — bot bertanya di chat, **jangan** menyuruh klik **Tambah**. Kolom DB yang nullable memakai default.

**Konfirmasi wajib** (kartu seperti penjualan) sebelum dijalankan:

- Hapus entitas apa pun
- Penetapan role **Super Admin**
- Buat agen partner
- Tulis stok / draf PO / draf jurnal / draf produksi / draf replenishment / posting jurnal

Password tidak dikembalikan ke chat. Boleh tawarkan “buka halamannya” setelah sukses — `open_page` hanya jika user setuju.

#### Bisa ditulis (create / update / delete)

| Domain | Entitas (sebutan di chat) |
|--------|---------------------------|
| HR | divisi, jabatan, karyawan, akun login |
| Customer | grup pelanggan, pelanggan (nama; grup default UMUM) |
| Partner | pengajuan partner; **agen** (nama cukup — registrasi + Convert Agent di server); reseller, cutting price |
| Produk | tipe, satuan, kategori, produk (nama + flag dijual; SKU/kode digenerate jika kosong; varian default jika dijual), daftar harga, atribut, tag, koleksi |
| Pembelian | supplier; **draf PO** (header: supplier + catatan) |
| Stok | penyesuaian lewat `StockMutationService` (bukan tulis kolom quantity) |
| Produksi | bill of materials; **draf** production order (stok bahan belum dipotong) |
| Distribusi | **draf** replenishment (status draft, tanpa item) |
| CRM | konfigurasi membership, promosi |
| Pembayaran | metode pembayaran |
| Bisnis | holding, company, cabang, gudang |
| Settings | role, menu, parameter, ongkir, notifikasi, tema |
| Akuntansi | COA, account mapping, kategori arus kas, kalender fiskal; **draf jurnal** + post jika seimbang |
| Academy | kategori kursus, kursus |
| Marketing | kategori marketing, aset marketing |

Contoh yang sah: “Tambahkan divisi Management”, “Buat customer PT Maju”, “Tambah produk Kopi Arabica, dijual”, “Buat agen Toko Makmur Jaya”, “Buat draf replenishment untuk agen Toko Makmur Jaya”, “Buat draf PO ke PT Sumber”, “Tambah karyawan Budi Santoso, email budi@wit.id, role Staff, status aktif, join hari ini”.

#### Karyawan dari chat

Satu kalimat cukup. Bot memanggil `manage_record` create `employee` (bukan `open_page`). Field: nama, email, role (nama → `role_id`), jabatan, divisi, `join_date` (“hari ini”), status aktif. Username dari email; password digenerate jika tidak disebut; kode karyawan dan NIK placeholder otomatis; cabang dari konteks login.

Role **Super Admin** menampilkan kartu konfirmasi dulu — karyawan belum dibuat sampai user menekan tombol.

Kalau nama belum ada, tool mengembalikan `{ success:false, missing:['fullname'] }`.

#### Agen partner dari chat

Bisa. Bot memanggil `manage_record` create `partner_agent` — **jangan** menolak, **jangan** `open_page` sebagai ganti. Menu Agents di UI memang tidak punya tombol Tambah; chat menjalankan jalur yang sama dengan UI: pendaftaran partner lalu **Convert Agent** dalam satu transaksi (`PartnerAgentChatService`).

Satu kalimat cukup, misalnya: “Buat agen Toko Makmur Jaya, telepon 081200000000, kota Cirebon”.

| Wajib | Opsional (disimpan jika disebut) | Otomatis (tidak perlu disebut) |
|-------|----------------------------------|-------------------------------|
| Nama toko/agen (`name` / `nama` / `nama_toko`) | Telepon, email, alamat, kota, provinsi, kode pos, kuota pembelian, kode agen, catatan | Tipe `AGENT`; nomor pengajuan `PAPP-…`; kode agen `AG-yymm-NNNN` jika kosong; status `active`/`approved`; gudang agen; akun login (username dari email atau `{kode}@agent.local`, password digenerate); customer di grup PARTNER; kuota pembelian 600 jika kosong; catatan bahwa dokumen dan pembayaran POS belum dilengkapi |

Tanpa nama, tool mengembalikan `{ success:false, missing:['name'] }`. Kartu **Buat agen baru?** muncul dulu — data belum dibuat sampai user menekan tombol.

Setelah agen ada, draf replenishment bisa langsung: “Buat draf replenishment untuk agen Toko Makmur Jaya” (nama sebagian diterima jika tidak ambigu). Submit/approve/kirim tetap di modul Replenishment.

Yang tetap tidak dari chat: unggah tanda tangan/formulir registrasi, pembayaran awal di POS. Akun yang sudah login sebagai agen tidak bisa membuat agen lain.

#### Stok, PO, jurnal, produksi, replenishment

| Entitas | Yang bisa dari chat | Yang tidak |
|---------|---------------------|------------|
| Stok | Penyesuaian (set/tambah/kurang) lewat `StockMutationService` + konfirmasi | Tulis `quantity` langsung; hapus baris stok |
| PO | Draf header (supplier + catatan) + list/get | Receive barang, ubah status ke process |
| Jurnal | Draf lewat `JournalService::create`; `post` hanya jika debit=kredit | Post jurnal tidak seimbang |
| Production order | Draf (`status=draft`) + list/get | Submit yang memotong stok bahan |
| Replenishment | Draf (`status=draft`) + list/get; wajib sebut agen (buat agen dulu dari chat jika belum ada) | Submit/approve/kirim (service create langsung SUBMITTED, tidak dipakai) |

Invoice/kontrabon, saldo awal, dan transaksi penjualan yang sudah ada tetap list/get. Jual baru tunai: `manage_sale`.

### 3.5 Widget

- Maskot bisa **digeser**; posisi tersimpan di browser (`localStorage`).
- Panel mengikuti posisi tombol (atas/bawah, kiri/kanan).
- Percakapan bisa dilanjutkan; tombol **+** memulai chat baru: overlay tur ditutup, suara dihentikan, percakapan dan chip saran kembali ke awal.
- Indikator “TITANIE sedang mengetik…”.
- Super admin melihat widget tanpa cek menu AI Assistant.
- Room tour: panel chat **disembunyikan** selama overlay; maskot FAB tetap terlihat. Navigasi di tooltip overlay (**Kembali** / **Lanjut** / **Lewati** / **Selesai**). Chat terbuka lagi dengan ringkasan saat tur selesai atau dilewati.
- Buka halaman dari chat: “Buka halaman kategori” / “buka halamannya dong” memanggil `open_page`. Tanpa filter: tab yang sama (`location.assign`). Dengan filter (“buka stok minuman”): tab baru (`window.open` same-origin; fallback `location.assign` jika popup diblokir) ke URL yang sudah berisi query, misalnya `/product/stock?category_id=…` atau `?variant_search=minuman`. Panel chat tetap terbuka.
- Suara **opsional, default mati** (lihat §5).

---

## 4. Yang TIDAK BISA / batas

| Tidak bisa | Kenapa / ke mana |
|------------|------------------|
| Ubah quantity stok langsung di tabel | Wajib `StockMutationService` (FIFO/FEFO) |
| Post jurnal tidak seimbang | `JournalService::post` menolak |
| Receive PO / ubah status PO dari chat | Hanya draf header |
| Submit production yang memotong stok | Hanya draf; proses di modul Production Order |
| Approve / kirim replenishment | Hanya draf; `ReplenishmentOrderService::create` langsung submitted — tidak dipanggil dari chat |
| Unggah dokumen/tanda tangan registrasi agen | Application dibuat tanpa dokumen; lengkapi di Partner Application dan POS |
| Ubah transaksi penjualan yang sudah ada | `sales_order` read-only; jual baru hanya tunai via `manage_sale` |
| POS non-tunai (transfer, kartu, tempo, split) | Kode pembayaran chat dibatasi `CASH`/`TUNAI` |
| Hapus / Super Admin tanpa kartu konfirmasi | Widget `action_card` dulu |
| Mengarang fakta atau angka | System prompt: jawab hanya dari hasil tool |
| Bertindak tanpa izin menu | Tool disembunyikan / ditolak per permission session |
| Chat di portal agen `/agent-order` | Widget hanya di layout admin |
| Pertanyaan di luar aplikasi | Ditolak sopan; bot bukan asisten umum |
| Modul yang tidak ada di repo (komisi, jenjang membership partner, API kurir) | Lihat [AI_CONTEXT.md](AI_CONTEXT.md) §12 |

Batas teknis lain: max 5 putaran tool per pesan; basis pengetahuan hanya `docs/` (bukan vector DB, bukan internet).

---

## 5. Tur ruangan (Product Tour)

Tool `guide_tour` sudah terpasang. Widget mengirim lokasi halaman (`page_path`, `page_title`, `page_menu`) di setiap `POST /agent/chat`. Overlay vanilla JS (`public/assets/ai/tour.js`) menyorot menu sidebar **atau** elemen halaman (judul, tombol tambah, filter, tabel, form). Selama tur, panel chat disembunyikan agar tidak menutupi spotlight; maskot FAB tetap bisa diklik untuk buka chat tanpa menghentikan tur. Fakta modul tetap dari `search_docs`; tool tur hanya memetakan ruangan + selector UI.

Suara **opsional, default mati**. Tombol speaker di tooltip tur dan di header chat memakai modul yang sama (`public/assets/ai/speech.js`, Web Speech API). Teks Bahasa Indonesia, karakter Titanie: suara **laki-laki** jika browser punya. Preferensi satu kunci `localStorage` (`agent_tour_voice=0/1`). Kalau overlay tur aktif, tur yang bicara (sapaan chat tidak menyela).

| Prompt user | Operasi | Yang terjadi |
|-------------|---------|--------------|
| “Apa sih ini?”, “ini halaman apa?”, “jelasin halaman ini” | `here` | Tur **dalam halaman ini**: menu induk, submenu, lalu 3–6 spot UI. Bukan keliling 11 modul |
| “Turin dong”, “turin fiturnya dong” | `start` | Tur **modul** dari **ruang 1** (Dashboard). Tiap modul: sidebar + paling banyak 2 spot landing |
| Pertanyaan baru (bukan lanjut/kembali/stop) atau **Chat baru** | — | Overlay + suara tur dihentikan; state tur di-reset |
| “lanjut” / tombol **Lanjut** di overlay | `next` | Langkah berikutnya |
| “kembali” / tombol **Kembali** | `prev` | Langkah sebelumnya |
| Tombol **Selesai** (langkah terakhir) / **Lewati** / “stop” / “cukup” | `stop` (tanpa LLM) | Overlay ditutup, chat terbuka lagi. **Tidak** mengirim “lanjut” ke `/agent/chat`. **Selesai ≠ Lanjut** |

Urutan tur: `config/agent_tour.php`. Smoke test: `php scripts/ai-tour-guide-test.php`.

### 5.1 Buka halaman dari chat

Tool `open_page` membuka menu admin. Tanpa filter: **tab yang sama**. Dengan filter (kategori/pencarian): **tab browser baru** (`window.open` same-origin); jika popup diblokir, fallback tab yang sama. Bukan tur: tidak ada overlay, panel chat tetap terbuka.

| Prompt user | Yang terjadi |
|-------------|--------------|
| “Buka halaman kategori” | Pindah ke `/product/category` jika user punya izin Category (tab yang sama) |
| “Buka POS” / “pergi ke stok” | Pindah ke `/transaction/pos` atau `/product/stock` |
| “Buka stok minuman” | Tab baru ke `/product/stock?category_id=…` jika kategori Minuman ada, else `?variant_search=minuman`. Jangan minta user mengetik di kotak filter |
| “Buka item Pocky” | Tab baru ke `/product/items?product=Pocky` |
| “Buka halamannya dong” | Halaman tersirat topik terakhir |
| Menu di luar izin sidebar | Ditolak; bot tidak mengarang URL |

Widget hanya membuka URL same-origin. Smoke test: `php scripts/ai-open-page-test.php`.

---

## 6. Izin per tool (ringkas)

| Tool | Izin |
|------|------|
| `search_docs`, `get_help`, `guide_tour` | Tidak perlu (selama widget boleh dibuka) |
| `open_page` | Menu tujuan `is_read` (hanya menu yang terlihat di sidebar) |
| `search_product` | Product `is_read` |
| `get_stock` | Stock `is_read` |
| `search_customer` | Customer `is_read` |
| `get_sales_summary` | Sales Summary `is_read` |
| `manage_sale` | POS `is_create` |
| `manage_record` | Menu entitas + aksi (`is_read` / `is_create` / `is_update` / `is_delete`) |

Katalog entitas: `config/agent_records.php`. Daftar tool yang diizinkan env: `AGENT_ALLOWED_TOOLS` (default: `search_docs,search_product,get_stock,search_customer,get_sales_summary,get_help,manage_sale,manage_record,guide_tour,open_page`).

Eval tanpa LLM: `php scripts/ai-bot-eval.php`.

---

## 7. Contoh percakapan

**Boleh**

- “Aplikasi ini sebenarnya untuk apa?”
- “Apa sih ini?” / “Turin fiturnya dong” / “Buka halaman kategori”
- “Tampilkan stok” / “Seluruh stok” / “Stok semua” / “Daftar stok” (ringkasan + 10 SKU di chat, bukan minta SKU, bukan alihkan ke halaman Stok)
- “Stok kopi arabica berapa?”
- “Jual 2 kopi arabica tunai”
- “Tambahkan divisi Management”
- “Tambah karyawan Budi, email budi@wit.id, role Staff, status aktif, join hari ini”
- “Tambah produk Kopi Arabica, dijual”
- “Buat customer PT Maju”
- “Buat agen Toko Makmur Jaya, telepon 081200000000, kota Cirebon”
- “Buat draf replenishment untuk agen Toko Makmur Jaya”
- “Buat draf PO ke supplier PT Sumber, catatan urgent”
- “Sesuaikan stok Kopi jadi 10” (lalu konfirmasi kartu)
- “Hapus divisi Management” (lalu konfirmasi kartu)

**Akan ditolak atau dialihkan**

- “Kurangi stok 10 pcs tanpa dokumen / tanpa konfirmasi”
- “Posting jurnal yang debit ≠ kredit”
- “Approve order replenishment dari chat”
- “Jual pakai transfer bank”
- “Berapa komisi agen bulan ini?”

---

## 8. File terkait

```
config/agent.php                                          flag, tool, docs path, pembayaran tunai
config/agent_tour.php                                     peta ruangan, submenu, selector halaman
config/agent_records.php                                  katalog writable vs read-only
app/Services/Ai/Tour/                                     katalog, urutan langkah (overview vs page-deep)
app/Services/Ai/WmsAgentService.php                       aturan perilaku + retry LLM
app/Services/Ai/Tools/                                    search_docs, data, manage_sale, manage_record, guide_tour, open_page
app/Services/Ai/Actions/                                  employee/product/agen/stock/PO/jurnal/produksi/replenishment + konfirmasi
resources/views/components/ai/chat-widget.blade.php       widget + chip saran + tombol suara chat
public/assets/ai/speech.js                                pilih suara laki-laki id-ID, speak/cancel
public/assets/ai/chat.js                                  drag maskot, riwayat chat, konfirmasi draf, chat baru, TTS, hide chat saat tur
public/assets/ai/tour.js                                  overlay Product Tour (spotlight + tooltip + suara opsional)
scripts/ai-bot-eval.php                                   cek registry, missing fields, tur stop, sanitizer, konfirmasi, create agen
```
