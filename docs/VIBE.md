# VIBE — Cara Tim Bekerja dengan AI di Repo Ini

> Dokumen pendamping [AI_CONTEXT.md](AI_CONTEXT.md).
> Kalau `AI_CONTEXT.md` menjawab "repo ini seperti apa", dokumen ini menjawab
> "bagaimana cara kita bekerja bersama AI di repo ini".

---

## Prinsip

**Satu pertanyaan penentu.** Setiap kali ragu mengambil keputusan teknis, tanyakan: *apakah keputusan ini membantu AI dan orang berikutnya lebih cepat paham repo ini?* Kalau ya, jalan terus.

**Dokumentasi adalah kode.** Chatbot mengambil jawabannya dari `docs/`, dan AI di IDE mengambil konteksnya dari sana juga. Dokumentasi yang basi bukan sekadar tidak rapi — dia langsung menurunkan kualitas kerja AI dan jawaban chatbot. Karena itu memperbaiki dokumentasi dihitung sebagai pekerjaan teknis, bukan pekerjaan sisa.

**AI mengusulkan, manusia memutuskan.** AI boleh menulis sebagian besar kode. Yang tidak boleh didelegasikan adalah keputusan: skema database, batas modul, dan aturan bisnis. Kalau tidak ada anggota tim yang bisa menjelaskan kenapa sebuah kode ada, kode itu belum layak masuk.

---

## Alur Kerja Standar

### 1. Pahami dulu, jangan langsung menulis

Sebelum menyentuh kode, minta AI menelusuri alurnya lebih dulu. Prompt penelusuran ada di [PROMPTS.md](PROMPTS.md) bagian "Memahami Repo". Tahap ini murah dan mencegah perubahan yang salah sasaran.

### 2. Minta rencana sebelum implementasi

Untuk apa pun yang menyentuh lebih dari satu file, minta AI menyebutkan dulu file yang akan dibuat atau diubah beserta alasannya. Rencana yang salah lebih mudah dikoreksi daripada kode yang salah.

### 3. Implementasi dengan diff kecil

Satu perubahan, satu tujuan. Diff besar sulit direview dan sulit di-rollback.

### 4. Verifikasi dengan mata sendiri

Jalankan aplikasinya. AI bisa menghasilkan kode yang lolos baca tapi gagal saat dijalankan. Minimal buka halaman yang terpengaruh dan coba alurnya sekali.

### 5. Rawat dokumentasi di commit yang sama

Kalau perubahan menyentuh arsitektur, alur bisnis, atau menambah tabel, perbarui dokumen yang relevan di commit yang sama. Menunda berarti tidak akan pernah dikerjakan.

---

## Apa yang Dikerjakan AI, Apa yang Tidak

| Cocok didelegasikan ke AI | Tetap keputusan manusia |
|---------------------------|-------------------------|
| Menelusuri dan menjelaskan alur kode | Desain skema database |
| Menulis boilerplate CRUD mengikuti pola yang ada | Menentukan batas modul dan tanggung jawabnya |
| Mengeluarkan logika dari controller ke service | Aturan bisnis dan kebijakan harga |
| Menulis draf dokumentasi dari kode | Menyetujui perubahan yang menyentuh data produksi |
| Review kode dan mencari penyimpangan konvensi | Trade-off yang mengorbankan kualitas demi kecepatan |
| Menyusun pesan commit dan deskripsi PR | Keputusan scope |

---

## Kebiasaan yang Terbukti Membantu

**Sebutkan file, jangan deskripsikan.** "Perbaiki `ReplenishmentOrderController@store`" jauh lebih efektif daripada "perbaiki bagian pembuatan order".

**Minta AI menyebut sumbernya.** Saat AI menjelaskan cara kerja sesuatu, minta ia menunjuk file dan barisnya. Ini cara tercepat mendeteksi halusinasi.

**Kalau AI salah dua kali dengan cara yang sama, masalahnya di dokumentasi.** Jangan mengulang koreksi manual di setiap sesi. Tulis konvensinya di `AI_CONTEXT.md` supaya AI berikutnya tidak jatuh di lubang yang sama.

**Mulai sesi baru untuk topik baru.** Konteks yang menumpuk dari pekerjaan sebelumnya membuat jawaban melenceng.

**Simpan prompt yang berhasil.** Kalau sebuah prompt menghasilkan output bagus, masukkan ke [PROMPTS.md](PROMPTS.md) supaya anggota tim lain tidak perlu menemukannya ulang.

---

## Batasan yang Kami Terapkan

- Tidak menempelkan kredensial, isi `.env`, atau data pelanggan nyata ke prompt.
- Tidak menerima perubahan skema database dari AI tanpa satu orang membacanya baris per baris.
- Tidak memakai output AI sebagai dokumentasi tanpa diverifikasi ke kode. Dokumentasi yang salah lebih berbahaya daripada tidak ada dokumentasi, karena chatbot akan menyebarkannya.
- Tidak menjalankan `git rebase` ke seluruh riwayat. Riwayat commit adalah bukti proses.

---

## Hubungannya dengan Chatbot

Chatbot TITANIE bukan fitur terpisah, melainkan cermin dari dokumentasi ini. Rantainya:

```
Kualitas docs/  →  Kualitas jawaban chatbot  →  Kualitas onboarding orang baru
```

Kalau chatbot menjawab dengan buruk, yang diperbaiki adalah dokumennya, bukan prompt-nya. Ini disengaja: satu-satunya cara membuat chatbot pintar adalah dengan benar-benar merawat dokumentasi.

---

## Dokumen Terkait

| Dokumen | Isi |
|---------|-----|
| [AI_CONTEXT.md](AI_CONTEXT.md) | Konteks repo untuk AI |
| [PROMPTS.md](PROMPTS.md) | Prompt siap pakai |
| [AI_USAGE.md](AI_USAGE.md) | Laporan pemakaian token |
| [../CONTRIBUTING.md](../CONTRIBUTING.md) | Alur git dan standar kontribusi |
