# Cursor Handoff — POS Agen Slice 5: Layout Polish

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-29-agent-pos-slice5-layout-polish-cursor-handoff.md".
> Spec: `docs/superpowers/specs/2026-07-28-agent-pos-design.md`. Prasyarat: Slice 1–4 selesai (POS agen jalan di `/agent-order/pos`).
> SCOPE = 5 penyesuaian tampilan/UX POS agen dari feedback. **JANGAN ubah admin POS** & **/shop customer**. File yang disentuh: `resources/views/agent/pos/index.blade.php`, `resources/views/layouts/agent-pos.blade.php`, `public/assets/js/agent-pos.js`, `app/Http/Controllers/Agent/AgentPosController.php`, `routes/agent.php` (khusus poin 2). Pakai token warna brand (`--bs-primary`), bukan hex.

## Poin 1 — Judul header: hilangkan "· WIT. Management System", pakai format meta transaksi

Sekarang ada DUA judul yang redundan:
- Topbar (layout `layouts/agent-pos.blade.php:34`): `POS Agen · {{ $shopCompanyName ?? config('app.name') }}`
- Sub-bar (`agent/pos/index.blade.php:22-29`): "POS Agen" + tanggal + jam + `N item` (pakai id dinamis `#posClock`, `#cartItemCount`).

**Target:** hanya satu judul, berformat **`POS Agen · {tanggal} {jam} · {N} item`** (bukan nama perusahaan).

Cara paling aman (pertahankan angka dinamis):
- Di `layouts/agent-pos.blade.php:34`, ganti `POS Agen · {{ ... }}` menjadi cukup label statis `POS Agen` ATAU biarkan brand logo saja.
- Pertahankan sub-bar (`index.blade.php:22-29`) sebagai sumber meta transaksi (tanggal/jam/item) karena sudah punya id dinamis. Bila topbar dan sub-bar terasa dobel "POS Agen", hapus teks "POS Agen" di salah satu (sisakan satu). Hasil akhir yang diharapkan di layar: satu baris **POS Agen · 29 Jul 2026 17:04 · 1 item** (angka item mengikuti `#cartItemCount`).
- `<title>` tag (`layouts/agent-pos.blade.php:11`) boleh dibiarkan (itu judul tab browser).

## Poin 2 — Dropdown reseller: searchable + AJAX (default reseller agen, cari ≥3 huruf jangkau reseller lain)

Sekarang `#customerSelect` (`index.blade.php:135-145`) hanya berisi reseller milik agen (di-render server-side) + Walk-in. `#customerSelect` sudah pakai Select2 (`public/assets/js/agent-pos.js:27`).

**Aturan yang diinginkan:**
- **Default (tanpa mengetik):** tampilkan reseller **di bawah agen yang login**.
- **Saat mengetik ≥ 3 huruf:** cari **semua reseller** (termasuk yang TIDAK terikat agen yang login), by nama/kode.
- Value tetap = `reseller.customer_id` (hanya reseller yang `customer_id`-nya terisi).

### 2a. Endpoint pencarian
Di `routes/agent.php` (grup `['auth:customer','agent']`, nama `agent-order.`):
```php
Route::get('/pos/resellers-search', [AgentPosController::class, 'resellerSearch'])->name('pos.resellers-search');
```
Di `AgentPosController`:
```php
public function resellerSearch(Request $request)
{
    $q = trim((string) $request->get('q', ''));
    $agent = $this->agent();

    $base = \App\Models\Partner\Reseller::query()
        ->with('customer:id,code,name')
        ->whereNotNull('customer_id')
        ->where('status', 'active');

    if (mb_strlen($q) >= 3) {
        // cari SEMUA reseller (lintas agen) by nama / kode reseller / nama-kode customer
        $base->where(function ($w) use ($q) {
            $w->where('name', 'ilike', "%{$q}%")
              ->orWhere('code', 'ilike', "%{$q}%")
              ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', "%{$q}%")->orWhere('code', 'ilike', "%{$q}%"));
        });
    } else {
        // default: hanya reseller milik agen yang login
        $base->where('agent_id', $agent->id);
    }

    $rows = $base->orderBy('name')->limit(30)->get();

    return response()->json([
        'results' => $rows->map(fn ($r) => [
            'id' => $r->customer_id,
            'text' => trim(($r->name ?: $r->customer?->name).($r->customer?->code ? ' · '.$r->customer->code : '')),
            'own' => $r->agent_id === $agent->id,   // opsional: untuk penanda "reseller Anda"
        ])->values(),
    ]);
}
```
> Cek kolom `code`/`status`/`agent_id` di `App\Models\Partner\Reseller` (grep) sebelum pakai; sesuaikan bila beda. `ilike` = Postgres case-insensitive (DB project pgsql). Verifikasi `php -l` + `php artisan route:list --name=agent-order.pos.resellers-search`.

### 2b. Select2 AJAX di frontend
Di `public/assets/js/agent-pos.js` (init `#customerSelect` sekitar baris 27), ubah jadi AJAX:
```js
$('#customerSelect').select2({
    placeholder: 'Pelanggan Umum (Walk-in)',
    allowClear: true,
    minimumInputLength: 0,              // default (0 huruf) tetap load reseller agen
    ajax: {
        url: window.agentPosRoutes.resellerSearch,   // definisikan route ini (lihat 2c)
        dataType: 'json',
        delay: 250,
        data: (params) => ({ q: params.term || '' }),
        processResults: (data) => ({ results: data.results }),
    },
});
```
> Select2 AJAX dengan `minimumInputLength: 0` akan memuat daftar default (reseller agen) saat dibuka; endpoint mengembalikan semua reseller hanya bila term ≥ 3 huruf (logika ada di server). Pertahankan opsi Walk-in: sisakan `<option value="">Pelanggan Umum (Walk-in)</option>` sebagai placeholder/clear (allowClear).

### 2c. Daftarkan route di Blade
Di `index.blade.php` blok `window.agentPosRoutes` (sekitar baris 390), tambah:
```blade
resellerSearch: @json(route('agent-order.pos.resellers-search')),
```
Hapus render `@foreach($resellers ...)` server-side di `#customerSelect` (baris 137-144) — kini via AJAX. (Boleh sisakan sebagai fallback bila JS mati, tapi lebih bersih dihapus.)

## Poin 3 — "Reguler" (price list) sejajar dengan pilih reseller

Sekarang `#priceListSelect` ada di top-bar (`index.blade.php:30-40`, blok `.pos-controls`), terpisah dari `#customerSelect` yang ada di panel kanan (`.pos-cart-top`, baris 133-147).

**Pindahkan** `#priceListWrapper`/`#priceListSelect` ke DALAM `.pos-cart-top` (baris 134), **satu baris dengan** `#customerSelect` (mis. flex row: reseller melebar, price list lebar tetap ~160px di sampingnya). Hapus blok `.pos-controls` di top-bar bila jadi kosong. Pastikan init select2 `#priceListSelect` (agent-pos.js:22) tetap jalan & handler `change` (baris 51) tak berubah. Sesuaikan CSS `.pos-cart-top` agar dua kontrol rapi sebaris.

## Poin 4 — Hapus label shortcut (kbd) di tombol

Hapus elemen `<kbd>…</kbd>` di:
- `index.blade.php:195` Diskon `<kbd>F4</kbd>`
- `:196` Promo `<kbd>F5</kbd>`
- `:197` Ongkir `<kbd>F6</kbd>` (lihat poin 5 — tombol ini kemungkinan dihapus)
- `:198` Catatan `<kbd>F7</kbd>`
- `:199` Hapus Semua `<kbd>F8</kbd>`
- `:236` BAYAR `<kbd>F1</kbd>`

Dan hapus handler keyboard terkait di `public/assets/js/agent-pos.js:142-165` (blok `$(document).on('keydown', …)` untuk F1/F4/F5/F7/F8) supaya tak ada shortcut tersembunyi. Fungsi tombol (klik) tetap jalan.

## Poin 5 — Tombol "Ongkir" meluber

Baris `index.blade.php:197`:
```blade
<button type="button" class="pos-tool-btn" disabled title="Ongkir portal agen = Rp 0">Ongkir <kbd>F6</kbd> Rp 0</button>
```
Teks "Ongkir F6 Rp 0" terlalu panjang → meluber melewati batas tombol dan menempel ke tombol sebelahnya.

**Rekomendasi: HAPUS tombol Ongkir dari toolbar ini.** Alasannya: ongkir portal agen selalu Rp 0 dan **sudah tampil** di ringkasan (baris 222-225, `.pos-shipping-row` "Ongkir … Rp 0"). Tombol disabled yang duplikatif tak berguna. Menghapusnya sekaligus menyelesaikan overflow.

(Bila tetap ingin dipertahankan: buang teks "Rp 0" & `<kbd>`, sisakan label "Ongkir" saja, dan pastikan `.pos-toolbar-row` memakai `flex-wrap`/lebar tombol yang muat.)

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentPosController.php
php artisan route:list --name=agent-order.pos
php artisan view:cache && php artisan view:clear
```
Smoke manual (login agen → `/agent-order/pos`, lebar desktop):
- Judul header satu baris: "POS Agen · {tanggal} {jam} · {N} item" (tanpa nama perusahaan); jumlah item ikut berubah saat menambah produk.
- Dropdown reseller: saat dibuka menampilkan reseller milik agen; ketik ≥3 huruf → reseller lintas agen ikut muncul; pilih → tersimpan sebagai customer_id. Walk-in masih bisa (clear).
- "Reguler"/price list sebaris dengan pilih reseller, rapi.
- Tombol Diskon/Promo/Catatan/Hapus Semua/BAYAR tanpa label F#; tak ada shortcut keyboard.
- Tombol Ongkir yang meluber hilang (atau muat rapi); ongkir tetap Rp 0 di ringkasan.
- Tak ada error konsol; admin POS & /shop tak berubah.

## Checklist

- [ ] Judul header jadi format "POS Agen · {tanggal} {jam} · {N} item" (hapus "· WIT. Management System"), tanpa dobel.
- [ ] Endpoint `agent-order.pos.resellers-search` + select2 AJAX: default reseller agen, ≥3 huruf jangkau semua reseller; value = customer_id; Walk-in tetap ada.
- [ ] Price list "Reguler" dipindah sebaris dengan pilih reseller; select2 & handler tetap jalan.
- [ ] Semua `<kbd>` shortcut dihapus + handler keydown F1/F4/F5/F7/F8 dihapus.
- [ ] Tombol Ongkir yang meluber dihapus (atau dirapikan); ongkir Rp 0 tetap di ringkasan.
- [ ] view:cache bersih; admin POS & /shop tak berubah; token brand dipakai.
