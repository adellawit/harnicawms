# Cursor Handoff — Poles Riwayat Pesanan Agen

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-23-agent-order-history-polish-cursor-handoff.md".
> Aturan permanen ada di `.cursorrules`. PRD: `docs/superpowers/specs/2026-07-23-agent-order-history-polish-design.md`.
> CATATAN: ini slice TERPISAH dari catalog polish. Hanya sentuh method `orders()` + view `agent/order/orders/index.blade.php`. JANGAN ubah `index()` (katalog), layout, atau file slice lain.

## Tujuan

Poles halaman riwayat pesanan agen: header + tombol "Order baru", chip filter (Semua/Pending/Selesai/Belum bayar/Dibatalkan), dan tiap baris ditambah thumbnail produk + jumlah item + badge metode bayar. Kerjakan berurutan, verifikasi tiap langkah.

## Langkah 0 — Verifikasi konteks

Baca `app/Http/Controllers/Agent/AgentOrderController.php` method `orders()` dan `resources/views/agent/order/orders/index.blade.php`. Konfirmasi relasi:
```bash
php artisan tinker --execute="
echo 'SalesOrder::items: '.(method_exists(App\Models\SalesOrder::class,'items')?'Y':'N').PHP_EOL;
echo 'SalesOrder::methodPayment: '.(method_exists(App\Models\SalesOrder::class,'methodPayment')?'Y':'N').PHP_EOL;
echo 'SalesOrderItem::product: '.(method_exists(App\Models\SalesOrderItem::class,'product')?'Y':'N').PHP_EOL;
"
```

## Langkah 1 — Controller `orders()`

Ganti isi method `orders()` menjadi (pertahankan `self::ORDER_TYPE` & guard kepemilikan via where):

```php
public function orders(Request $request): View
{
    $customer = auth('customer')->user();

    $filterMap = [
        'pending'   => ['status', 'pending'],
        'completed' => ['status', 'completed'],
        'unpaid'    => ['payment_status', 'unpaid'],
        'cancelled' => ['status', 'cancelled'],
    ];
    $activeFilter = $request->get('filter');
    $activeFilter = array_key_exists($activeFilter, $filterMap) ? $activeFilter : 'all';

    $query = SalesOrder::query()
        ->where('order_type', self::ORDER_TYPE)
        ->where('customer_id', $customer->id)
        ->withCount('items')
        ->with(['items' => fn ($q) => $q->with('product')->limit(1), 'methodPayment', 'payments.methodPayment'])
        ->orderByDesc('created_at');

    if ($activeFilter !== 'all') {
        [$col, $val] = $filterMap[$activeFilter];
        $query->where($col, $val);
    }

    $orders = $query->paginate(15);

    return view('agent.order.orders.index', [
        'customer' => $customer,
        'orders' => $orders,
        'activeFilter' => $activeFilter,
    ]);
}
```
Catatan: `with(['items' => fn ($q) => ...->limit(1)])` memuat 1 item untuk thumbnail; `withCount('items')` memberi `items_count` untuk jumlah. Bila `limit(1)` pada eager-load menyulitkan di PostgreSQL, muat semua item saja (`with('items.product')`) dan ambil `->items->first()` + `->items->count()` di view. Pastikan `use Illuminate\Http\Request;` ada (sudah dipakai method lain).

Verifikasi: `php -l app/Http/Controllers/Agent/AgentOrderController.php`.

## Langkah 2 — View `orders/index.blade.php`

Pertahankan struktur & kelas yang ada; tambahkan header, chip, dan detail baris.

**(a) Header** (ganti `<header>` yang ada):
```blade
<header class="shop-page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <div class="small text-muted text-uppercase">Portal Agen · Web Order</div>
        <h1 class="shop-page-title mb-0">Riwayat Pesanan</h1>
        <p class="text-muted small mb-0">Pantau status order ke distributor dan buka detail setiap transaksi.</p>
    </div>
    <a href="{{ route('agent-order.index') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i>Order baru</a>
</header>
```

**(b) Chip filter** (sebelum card daftar):
```blade
@php
    $filters = ['all' => 'Semua', 'pending' => 'Pending', 'completed' => 'Selesai', 'unpaid' => 'Belum bayar', 'cancelled' => 'Dibatalkan'];
@endphp
<div class="shop-chips d-flex flex-wrap gap-2 mb-3">
    @foreach ($filters as $key => $label)
        <a href="{{ route('agent-order.orders', $key === 'all' ? [] : ['filter' => $key]) }}"
           class="btn btn-sm {{ $activeFilter === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
    @endforeach
</div>
```

**(c) Tiap baris** — di dalam `@forelse ($orders as $order)`, di bagian `shop-order-row-main`, tambahkan thumbnail + jumlah item + badge metode bayar. Contoh penyesuaian baris (sisipkan thumbnail di kiri, dan tambah info item + metode bayar di meta):
```blade
@php
    $firstItem = $order->items->first();
    $thumb = $firstItem?->product?->image;
    $itemCount = $order->items_count ?? $order->items->count();
    $payMethod = $order->methodPayment?->name ?? $order->payments->first()?->methodPayment?->name;
@endphp
<a href="{{ route('agent-order.orders.show', $order->id) }}" class="shop-order-row-main text-decoration-none text-body d-flex gap-3 align-items-center">
    <span class="shop-order-thumb flex-shrink-0">
        @if ($thumb)
            <img src="{{ $thumb }}" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'ti ti-package'}))">
        @else
            <span class="ti ti-package"></span>
        @endif
    </span>
    <span class="flex-grow-1 min-w-0">
        <div class="shop-order-row-top">
            <span class="shop-order-number">{{ $order->sales_number }}</span>
            <span class="shop-order-total">Rp {{ number_format($order->total, 0, ',', '.') }}</span>
        </div>
        <div class="shop-order-row-meta">
            <time class="shop-order-date text-muted">{{ $order->sales_date?->format('d M Y, H:i') ?? $order->created_at->format('d M Y, H:i') }} · {{ $itemCount }} item</time>
            <div class="shop-order-badges">
                <span class="badge bg-label-{{ $statusBadge }}">{{ strtoupper($order->status) }}</span>
                <span class="badge bg-label-{{ $payBadge }}">{{ strtoupper($order->payment_status) }}</span>
                @if ($payMethod)<span class="badge bg-label-secondary"><i class="ti ti-credit-card me-1"></i>{{ $payMethod }}</span>@endif
            </div>
        </div>
    </span>
</a>
```
(Blok `@php $payBadge/$statusBadge @endphp` yang sudah ada tetap dipakai. Sesuaikan agar tidak menduplikasi elemen lama — ganti isi `shop-order-row-main` lama dengan versi di atas.)

**(d) Empty state** kontekstual: bila `$activeFilter !== 'all'` tampilkan "Belum ada pesanan pada filter ini." (tetap tampilkan chip di atas). Bila `all` kosong, teks lama "Belum ada pesanan." + tombol Mulai order.

**(e) CSS thumbnail**: tambah `.shop-order-thumb` di `public/assets/css/shop.css` (mis. `width:44px;height:44px;border-radius:8px;object-fit:cover;` + gaya untuk `img` & ikon fallback). Bila menyentuh shop.css yang juga dipakai slice lain, tambahkan blok CSS BARU (jangan ubah aturan lama).

Verifikasi: `php artisan view:cache` sukses, lalu `php artisan view:clear`.

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Agent/AgentOrderController.php
php artisan route:list --name=agent-order.orders
php artisan view:cache && php artisan view:clear
```
Smoke manual (login customer-agent yang punya beberapa order):
- `/agen-order/orders` → header + tombol "Order baru" + 5 chip + baris dengan thumbnail, "N item", badge status/bayar/metode.
- Klik chip Pending/Selesai/Belum bayar/Dibatalkan → daftar terfilter benar; Semua → semua; `?filter=ngawur` → semua.
- Ganti halaman (paginasi) → filter tetap.
- Detail order (`orders/{id}`), checkout, katalog, `/shop` tak berubah.

## Checklist

- [ ] `orders(Request)` : filter `?filter=` termap benar + invalid diabaikan; eager-load items.product/methodPayment; `withQueryString` paginasi.
- [ ] Header + tombol "Order baru".
- [ ] 5 chip filter single-select, aktif ditandai dari `$activeFilter`.
- [ ] Baris: thumbnail (fallback ikon), jumlah item, badge metode bayar (hidden bila null); badge status/bayar lama tetap.
- [ ] Empty state kontekstual per filter.
- [ ] CSS thumbnail ditambah (blok baru, tak ubah aturan lama).
- [ ] view:cache bersih; detail order/checkout/katalog/`/shop` tak berubah.
