# Cursor Handoff — Marketing Campaign Slice A: Relabel Menu

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-08-03-marketing-campaign-slice-a-relabel-menu-cursor-handoff.md".
> Spec: `docs/superpowers/specs/2026-08-03-marketing-campaign-design.md`. Aturan permanen di `.cursorrules`.
> SCOPE kecil: perbaiki LABEL menu yang salah — menu berlabel "Marketing Campaign" saat ini isinya Marketing Assets. Relabel jadi "Marketing Assets". TIDAK membangun fitur campaign di sini (itu Slice B). Tak menyentuh controller/logic aset.

## Konteks

- Menu di project ini DB-driven (tabel via model `App\Models\Menu`, sebagian di-seed `database/seeders/MenuSeeder.php`, sebagian ditambah lewat UI Access Management). Field penting: `name`, `text_sidebar`, `code`, `slug`, `route_name`, `url_path`, `icon`, `parent_id`, `order_number`.
- Konten "Marketing Campaign" yang salah = halaman **Marketing Assets** = `AssetController` (route `marketing.assets.index`, `resources/views/admin/marketing/assets/index.blade.php`, permission "Marketing Center").

## Langkah 1 — Temukan record menu yang salah label

Cari menu yang mengarah ke marketing assets tapi berlabel "Marketing Campaign". Contoh query (tinker):
```php
\App\Models\Menu::where('name','like','%Campaign%')
    ->orWhere('text_sidebar','like','%Campaign%')
    ->orWhere('route_name','like','%asset%')
    ->orWhere('url_path','like','%asset%')
    ->get(['id','name','text_sidebar','code','slug','route_name','url_path','parent_id']);
```
Identifikasi record yang `route_name`/`url_path`-nya menuju marketing **assets** namun `name`/`text_sidebar`-nya "Marketing Campaign". (Bila ternyata labelnya sudah benar atau tak ada, LAPOR — jangan mengarang perubahan.)

## Langkah 2 — Relabel jadi "Marketing Assets"

Ubah record tersebut agar konsisten menunjuk Marketing Assets:
- `name` → `Marketing Assets`
- `text_sidebar` → `Marketing Assets`
- `slug` → `marketing-assets` (bila dipakai untuk URL menu; pastikan `route_name`/`url_path` tetap mengarah ke `marketing.assets.index`)
- (opsional) `code` → `marketing_assets` bila `code` lama menyiratkan "campaign" — HATI-HATI: `code` mungkin dipakai referensi permission/kode lain; ubah hanya bila aman (cek pemakaian `code` tsb dulu).
- `icon` boleh disesuaikan (mis. `ti ti-photo`).

Lakukan lewat **migrasi/seeder idempotent** (lebih baik daripada edit manual DB, agar reproducible). Contoh migrasi:
```php
// database/migrations/xxxx_relabel_marketing_campaign_menu_to_assets.php
public function up(): void {
    \App\Models\Menu::query()
        ->where(function ($q) { $q->where('route_name','like','%asset%')->orWhere('url_path','like','%asset%'); })
        ->where(function ($q) { $q->where('name','like','%Campaign%')->orWhere('text_sidebar','like','%Campaign%'); })
        ->update(['name' => 'Marketing Assets', 'text_sidebar' => 'Marketing Assets']);
}
```
> Sesuaikan kolom nyata (grep `MenuSeeder.php` untuk daftar field yang benar). Bila project punya konvensi seeder menu tersendiri, ikuti itu. JANGAN mengubah `MenuSeeder` baris lain yang tak terkait.

## Langkah 3 — Catatan untuk Slice B

Menu **baru** "Marketing Campaign" (mengarah ke CRUD campaign) dibuat di **Slice B** setelah route `marketing.campaigns.*` ada. JANGAN buat menu campaign kosong di sini (belum ada route-nya).

## Verifikasi akhir

```bash
php artisan migrate        # bila pakai migrasi relabel
```
Smoke manual (login admin): sidebar menampilkan "Marketing Assets" (bukan "Marketing Campaign") yang membuka daftar aset seperti biasa; fungsi CRUD aset tak berubah. Tak ada menu lain yang labelnya berubah.

## Checklist

- [ ] Record menu marketing-assets yang salah label ditemukan (atau dilaporkan bila tak ada).
- [ ] Label diubah ke "Marketing Assets" (name + text_sidebar; slug/icon opsional), tetap menuju `marketing.assets.index`.
- [ ] Perubahan idempotent (migrasi/seeder), bukan edit manual sekali pakai.
- [ ] Tidak membuat menu "Marketing Campaign" baru di sini (ditunda ke Slice B).
- [ ] CRUD aset & menu lain tak berubah.
