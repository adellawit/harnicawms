# Cursor Handoff — Halaman Reseller Agen (slice 5a)

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-23-agent-reseller-list-cursor-handoff.md".
> Aturan permanen ada di `.cursorrules`. Slice TERPISAH — hanya tambah route/method/view baru + update 1 tautan di dashboard. Jangan sentuh katalog/checkout/riwayat.

## Tujuan

Halaman **Reseller** untuk agen (`/agen-order/reseller`, guard `customer` + `agent`): daftar reseller yang ter-mapping ke agen (read-only). Mengganti placeholder "Segera hadir" pada tautan "Semua reseller" di dashboard.

## Konteks (sudah ada)

- Portal agen: controller `App\Http\Controllers\Agent\AgentOrderController`, route group `agent-order.*` (guard `auth:customer` + `agent`), layout `layouts.agent-order`.
- `App\Models\Partner\Agent::resellers()` HasMany → `App\Models\Partner\Reseller` (fields: `code`, `name`, `email`, `phone`, `city`, `province`, `status`). Customer→agent via `Customer::agent()`.
- Dashboard (`resources/views/agent/order/dashboard.blade.php`) sudah ada; tautan "Semua reseller" saat ini disabled "Segera hadir".

## Langkah 1 — Route

Di `routes/agent.php`, DI DALAM grup `['auth:customer','agent']`, tambah:
```php
Route::get('/reseller', [AgentOrderController::class, 'resellers'])->name('resellers');
```
Verifikasi: `php artisan route:list --name=agent-order.resellers`.

## Langkah 2 — Controller `resellers()`

```php
public function resellers(Request $request): View
{
    $customer = $this->context()->customer();
    $agent = $customer->agent;

    $query = $agent
        ? $agent->resellers()->getQuery()
        : \App\Models\Partner\Reseller::query()->whereRaw('1 = 0'); // agen null → kosong

    if ($status = $request->get('status')) {
        if (in_array($status, ['active', 'inactive'], true)) {
            $query->where('status', $status);
        }
    }
    if ($search = trim((string) $request->get('q', ''))) {
        $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%"));
    }

    $resellers = $query->orderBy('name')->paginate(20)->withQueryString();

    return view('agent.order.resellers', [
        'resellers' => $resellers,
        'activeStatus' => $status,
        'search' => $search,
    ]);
}
```
Catatan: cek nilai status reseller yang dipakai di data (`active`/`inactive` atau lain) via `DB::table('partner.resellers')->distinct()->pluck('status')` dan sesuaikan opsi filter. Verifikasi `php -l`.

## Langkah 3 — View `agent.order.resellers`

Buat `resources/views/agent/order/resellers.blade.php` extends `layouts.agent-order`. Struktur:
- Header: judul "Reseller Saya" + subjudul "Reseller yang ter-mapping ke agen Anda." + tombol kembali ke dashboard.
- Search form (`q`) + chip status opsional (Semua/Aktif/Nonaktif via `?status=`).
- Daftar/grid kartu reseller: **inisial** dari `name` (2 huruf), `name`, `code` (RS-xxxx), `city`/`province`, `phone`, badge `status` (AKTIF hijau / NONAKTIF abu). Tombol kontak opsional: `<a href="tel:{{ $r->phone }}">` dan/atau WA `https://wa.me/{{ preg_replace('/[^0-9]/','',$r->phone) }}` (hanya bila `phone` ada).
- Empty state: "Belum ada reseller." (bila agen tak punya reseller / filter kosong).
- Paginasi `{{ $resellers->links('pagination::bootstrap-5') }}` bila `hasPages()`.

Ikuti gaya kartu yang sudah dipakai di dashboard section "Reseller Saya" agar konsisten (boleh salin markup kartunya dari `dashboard.blade.php`).

Verifikasi: `php artisan view:cache` sukses, lalu `view:clear`.

## Langkah 4 — Aktifkan tautan di dashboard

Di `resources/views/agent/order/dashboard.blade.php`, ganti tautan "Semua reseller" yang saat ini disabled "Segera hadir" menjadi:
```blade
<a href="{{ route('agent-order.resellers') }}" class="small">Semua reseller →</a>
```
(Cari elemen disabled untuk reseller; hanya itu yang diubah. JANGAN sentuh placeholder Materi/Pelatihan di slice ini.)

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentOrderController.php
php artisan route:list --name=agent-order.resellers
php artisan view:cache && php artisan view:clear
```
Smoke manual (login customer-agent yang punya reseller):
- `/agen-order/reseller` → daftar reseller (inisial/nama/kode/kota/telp/status); search & filter status jalan; paginasi jaga query.
- Dashboard "Semua reseller →" mengarah ke halaman ini (bukan "Segera hadir").
- Agen tanpa reseller → empty state (tanpa error).
- Regresi: alur agent lain & `/shop` tak berubah.

## Checklist

- [ ] Route `agent-order.resellers` terdaftar (guard benar).
- [ ] `resellers()` null-safe pada `$agent`; filter status + search opsional; paginasi withQueryString.
- [ ] View reseller (kartu konsisten dgn dashboard); empty state; kontak opsional bila phone ada.
- [ ] Tautan dashboard "Semua reseller" diaktifkan (hanya itu).
- [ ] view:cache bersih; tak menyentuh slice lain.
