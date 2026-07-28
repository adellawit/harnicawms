# Cursor Handoff — POS Agen Slice 3: Strip Campaign

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-28-agent-pos-slice3-campaign-cursor-handoff.md".
> Spec: `docs/superpowers/specs/2026-07-28-agent-pos-design.md`. Prasyarat: Slice 1 & 2 (POS agen backend + view) sudah ada.
> SCOPE kecil: isi placeholder strip campaign di panel kiri-bawah POS agen. Tak menyentuh alur bayar/cart.

## Konteks

- Di mockup, kiri-bawah ada kartu campaign (mis. "Diskon Minuman 10%", "Beli 2 Lebih Hemat 15%", "Member Special 5%"). Persentase itu **ilustrasi** — campaign nyata di project = modul **Promotion** temanmu (`product.promotions`, engine buy-X-get-Y), BUKAN diskon persen.
- Sumber data = `App\Models\Promotion::activeNow()` — SAMA yang dipakai katalog agen (`AgentOrderController` memakai `Promotion::activeNow()->pluck(...)` untuk menandai produk promo). Konsisten dengan "Item Promo" katalog.
- Slice 2 sudah menyediakan container `#posCampaignStrip` (placeholder) di panel kiri.

## Langkah 1 — Controller: kirim campaign aktif ke view

Di `app/Http/Controllers/Agent/AgentPosController::index()`, ambil promotion aktif dan kirim ke view. Cek dulu field Promotion yang tersedia untuk label (grep `app/Models/Promotion.php`: mis. `name`, relasi `buyProduct()`/`getProduct()`, `buy_min_qty`, `get_qty`). Contoh:

```php
$campaigns = \App\Models\Promotion::activeNow()
    ->with(['buyProduct:id,name'])          // sesuaikan nama relasi bila beda
    ->orderByDesc('priority')
    ->limit(6)
    ->get()
    ->map(fn ($p) => [
        'name'  => $p->name,
        'label' => trim(($p->buy_min_qty ? 'Beli '.$p->buy_min_qty : '').
                        ($p->get_qty ? ' gratis '.$p->get_qty : '')),
        'product' => $p->buyProduct?->name,
    ]);
// kirim: 'campaigns' => $campaigns
```
> Sesuaikan field/relasi dengan yang benar-benar ada di `Promotion` (grep dulu). Bila engine promo memakai istilah lain, pakai yang aktual. Verifikasi `php -l`.

## Langkah 2 — View: render kartu campaign

Di `resources/views/agent/pos/index.blade.php`, isi `#posCampaignStrip`:
```blade
@if (($campaigns ?? collect())->isNotEmpty())
    <div id="posCampaignStrip" class="row g-2 mt-2">
        @foreach ($campaigns as $c)
            <div class="col-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100 bg-label-primary">
                    <div class="card-body p-3">
                        <span class="badge bg-primary mb-1">CAMPAIGN</span>
                        <div class="fw-semibold small text-truncate">{{ $c['name'] }}</div>
                        @if (!empty($c['label']))<div class="text-muted small">{{ $c['label'] }}</div>@endif
                        @if (!empty($c['product']))<div class="text-muted small text-truncate">{{ $c['product'] }}</div>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
```
- **Kosong → strip tersembunyi** (kondisi `product.promotions` sekarang 0 aktif → tidak tampil; otomatis muncul saat admin menambah promotion aktif).
- Pakai token brand (`bg-label-primary`/`bg-primary`), bukan hex mockup. Ini kartu **informasi** (bukan tombol aksi) — tak mengubah cart.

Verifikasi: `php artisan view:cache && php artisan view:clear`.

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentPosController.php
php artisan view:cache && php artisan view:clear
```
Smoke manual: `/agen-order/pos` → bila ada promotion aktif, kartu campaign tampil di kiri-bawah; bila tidak ada (kondisi sekarang), strip tak muncul & layout tetap rapi. Alur cart/bayar tak berubah.

## Checklist

- [ ] `index()` mengirim `$campaigns` dari `Promotion::activeNow()` (field/relasi diverifikasi).
- [ ] Strip campaign render di kiri-bawah; kosong → tersembunyi.
- [ ] Kartu informasi saja (tak mengubah cart); token brand, bukan hex.
- [ ] view:cache bersih; alur POS lain tak berubah.
