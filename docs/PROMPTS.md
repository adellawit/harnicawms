# PROMPTS — Prompt Library TITANIE

> Prompt siap pakai untuk kerja sehari-hari di repo ini. Semuanya mengasumsikan
> AI sudah membaca [AI_CONTEXT.md](AI_CONTEXT.md) lebih dulu.
>
> Cara pakai: salin prompt, ganti bagian dalam kurung siku, tempel ke AI di IDE.

---

## Aturan Umum

Selalu awali sesi kerja dengan ini supaya AI tidak menebak konvensi:

```
Baca docs/AI_CONTEXT.md lebih dulu sebelum menyentuh kode apa pun.
Ikuti konvensi yang ada di sana: model pakai HasUuids + SoftDeletes + prefix
schema di $table, logika bisnis di app/Services/, controller tipis, permission
berbasis nama menu, dan setiap operasi multi-tabel dibungkus DB::transaction().
Buat diff seminimal mungkin dan ikuti gaya kode di sekitarnya.
```

---

## 1. Memahami Repo

### Menelusuri satu alur bisnis

```
Telusuri alur [nama alur, misal: order replenishment dari agen sampai barang diterima]
di repo ini. Untuk tiap tahap sebutkan: route, controller, service, model, tabel
database, dan view yang terlibat. Tandai bagian yang logikanya masih ada di
controller dan seharusnya pindah ke service. Jangan mengubah kode apa pun.
```

### Memahami satu modul sebelum mengubahnya

```
Jelaskan modul [nama modul] di repo ini: apa fungsinya secara bisnis, file apa
saja yang menyusunnya, bagaimana permission-nya diatur, dan apa saja
ketergantungannya ke modul lain. Sebutkan risiko yang perlu diperhatikan kalau
modul ini diubah.
```

### Mencari dampak sebuah perubahan

```
Kalau saya mengubah [nama kolom / method / tabel], bagian mana saja di repo ini
yang ikut terpengaruh? Cek controller, service, model, view Blade, seeder, dan
migration. Tampilkan daftar file beserta baris yang relevan.
```

---

## 2. Menambah Fitur

### Menambah halaman admin baru

```
Tambahkan modul admin baru bernama [Nama Modul] untuk mengelola [entitas].

Ikuti pola modul yang sudah ada di repo:
1. Migration di database/migrations/[schema]/ — UUID primary key, soft delete,
   kolom audit created_by/updated_by/deleted_by.
2. Model dengan HasUuids, SoftDeletes, $connection = 'pgsql', dan $table
   berprefix schema.
3. Controller di app/Http/Controllers/Admin/ yang tipis; taruh logika bisnisnya
   di app/Services/.
4. Route dengan middleware permission:[Nama Menu],is_read dan seterusnya.
5. Entri menu lewat seeder supaya muncul di sidebar — halaman tidak akan
   terlihat tanpa langkah ini.
6. View di resources/views/admin/[modul]/ mengikuti pola index/insert/edit.

Sebelum menulis kode, tunjukkan dulu rencana file yang akan dibuat atau diubah.
```

### Menambah kolom ke tabel yang sudah ada

```
Tambahkan kolom [nama kolom] bertipe [tipe] ke tabel [schema].[tabel].

Syarat: migration baru yang bersifat aditif saja — dilarang menghapus kolom atau
mengubah tipe data yang bisa merusak data lama. Perbarui $fillable dan $casts di
model terkait, lalu perbarui form dan tampilan yang relevan. Catat perubahannya
satu baris di docs/ARCHITECTURE.md.
```

### Menambah tool baru untuk chatbot

```
Tambahkan tool baru untuk chatbot bernama [nama_tool] yang [fungsinya].

Ikuti pola app/Services/Ai/Tools/: extend AbstractAgentTool, implementasikan
name(), description(), parameters(), requiredPermission(), dan execute().
Daftarkan di constructor AgentToolRegistry, lalu tambahkan namanya ke default
AGENT_ALLOWED_TOOLS di config/agent.php. Kembalikan array dengan kunci 'success'
dan pesan error yang ramah bila gagal.
```

---

## 3. Restrukturisasi

### Mengeluarkan logika dari controller

```
Controller [nama controller] method [nama method] masih menyimpan logika bisnis.
Pindahkan logikanya ke service di app/Services/[Domain]/ tanpa mengubah perilaku
yang terlihat user.

Syarat: controller hanya menyisakan validasi, pemanggilan service, dan
redirect/flash. Bungkus operasi multi-tabel dengan DB::transaction(). Jangan
mengubah nama route, nama view, maupun struktur database.
```

### Menyeragamkan status yang tersebar

```
Nilai status [entitas] saat ini ditulis sebagai string literal di beberapa
tempat. Kumpulkan menjadi satu sumber konstanta, ganti semua pemakaian string
literal dengan konstanta itu, dan pastikan tidak ada nilai status yang tercecer.
Tunjukkan daftar file yang diubah beserta alasannya.
```

---

## 4. Debugging

### Menelusuri error

```
Saya dapat error berikut saat [aksi yang dilakukan]:

[tempel pesan error dan stack trace]

Telusuri penyebabnya di repo ini. Jelaskan akar masalahnya lebih dulu sebelum
mengusulkan perbaikan, dan sebutkan file beserta barisnya.
```

### Masalah permission atau menu tidak muncul

```
Halaman [nama halaman] mengembalikan 403 atau menunya tidak muncul di sidebar.
Periksa berurutan: apakah route punya middleware permission, apakah nama menu di
middleware sama persis dengan nama menu di seeder, apakah entri menu sudah ada
di MenuSeeder, dan apakah role terkait punya akses di IamHasAccessSeeder.
```

### Stok tidak sesuai

```
Stok [produk] di gudang [gudang] tidak sesuai setelah [aksi]. Telusuri lewat
product.product_stock_movements dan product.product_cost_layers. Periksa apakah
ada perubahan stok yang tidak lewat StockMutationService.
```

---

## 5. Merawat Dokumentasi

### Menyelaraskan dokumentasi dengan kode

```
Bandingkan isi docs/ARCHITECTURE.md dengan keadaan kode sekarang. Sebutkan
bagian yang sudah tidak akurat, yang hilang, dan yang berlebihan. Perbarui
dokumennya agar sesuai, tanpa menduplikasi isi dokumen lain — cukup rujuk.
```

### Memperbaiki jawaban chatbot

```
Chatbot menjawab [pertanyaan] dengan kurang tepat.

Ingat: pengetahuan chatbot berasal dari file markdown di docs/, bukan dari
system prompt. Jadi perbaiki dokumentasinya, jangan mengedit prompt. Cari
dokumen yang seharusnya memuat jawaban itu, lalu lengkapi bagian yang kurang.
```

---

## 6. Review Sebelum Commit

### Review kode

```
Review perubahan yang belum di-commit. Fokus pada: logika bisnis yang bocor ke
controller, operasi multi-tabel tanpa transaksi, query N+1, input yang tidak
divalidasi, dan penyimpangan dari konvensi di docs/AI_CONTEXT.md. Urutkan
temuan dari yang paling penting.
```

### Review keamanan

```
Periksa perubahan ini dari sisi keamanan: secret yang ter-hardcode, input yang
tidak divalidasi, query yang tidak ter-parameterisasi, output yang tidak
di-escape di Blade, route yang kehilangan middleware permission, dan pesan error
yang membocorkan informasi internal.
```

### Menulis pesan commit

```
Buat pesan commit untuk perubahan ini mengikuti conventional commits: huruf
kecil, imperatif, format "tipe: deskripsi". Tipe yang dipakai di repo ini: feat,
fix, docs, test, chore, refactor, perf, ci. Jelaskan alasan perubahannya, bukan
sekadar daftar file.
```

---

## 7. Prompt untuk Demo Day

Prompt cadangan saat wasit meminta fitur kecil yang belum pernah dicoba:

```
Baca docs/AI_CONTEXT.md, lalu kerjakan permintaan ini: [permintaan wasit].

Sebelum menulis kode, sebutkan dulu file apa saja yang akan dibuat atau diubah
dan kenapa. Ikuti konvensi repo. Kalau permintaan ini butuh entri menu atau
permission baru, sebutkan juga.
```
