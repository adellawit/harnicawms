# Cursor Handoff — POS Agen Slice 1: Backend & Routing

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-07-28-agent-pos-slice1-backend-cursor-handoff.md".
> Spec lengkap: `docs/superpowers/specs/2026-07-28-agent-pos-design.md`. Aturan permanen di `.cursorrules`.
> SCOPE Slice 1 = backend + routing POS agen. View boleh placeholder minimal dulu (Slice 2 yang bikin tampilan mockup). **JANGAN ubah admin POS** (`POSController`, `admin/transaction/pos.blade.php`) & **JANGAN ubah /shop customer.**

## Konteks (baca dulu pola-nya)

- **Acuan utama:** `app/Http/Controllers/Admin/POSController.php` (699 baris) + `app/Services/PosCheckoutService.php`. POS agen meniru pola ini tapi di-scope ke agen.
- **Reseller = Customer:** `App\Models\Partner\Reseller.customer_id` → `belongsTo(Customer)`. `Agent::resellers()` HasMany. Buyer POS agen disimpan sebagai `customer_id` = `reseller->customer_id`. **Tanpa migrasi baru.**
- **Konteks agen sudah ada:** lihat `app/Http/Controllers/Agent/AgentOrderController.php` — pakai `auth('customer')->user()`, `$this->context()` (`ShopContextService`), `$ctx->branchId()`. Ambil branch/price-list dengan cara yang SAMA agar produk & harga terbaca konsisten dengan katalog agen.
- **Gudang jual agen:** `WmsContext::defaultAgentWarehouse($agent->id)` (fallback `$agent->defaultWarehouse`). Helper sudah ada di `app/Support/WmsContext.php`.
- **`PosCheckoutService` sudah sadar-agen:** `completePaidOrder()` → `resolveAgentDestinationWarehouse()` mengembalikan `null` bila buyer reseller/walk-in (penjualan outbound murni, tanpa stock-in). Buyer POS agen = reseller ⇒ perilaku ini sudah benar. **Satu-satunya celah**: sumber gudang di `createSalesOrder()` masih gudang default cabang, harus bisa di-override ke gudang agen (Langkah 1).

## Langkah 1 — `PosCheckoutService::createSalesOrder()`: parameter gudang opsional (aditif, backward-compatible)

Di `app/Services/PosCheckoutService.php`, method `createSalesOrder()` (sekarang berakhir `string $orderType = 'pos'`). **Tambah parameter opsional di akhir**:

```php
public function createSalesOrder(
    Request $request,
    array $totals,
    string $salesNumber,
    string $branchId,
    ?string $companyId,
    ?string $userId,
    string $status,
    string $paymentStatus,
    string $orderType = 'pos',
    ?string $warehouseId = null,   // <-- BARU: override gudang sumber (POS agen)
): SalesOrder {
    // ...
    // Baris lama:
    //   $warehouseId = optional(WmsContext::defaultWarehouse($branchId))->id;
    // GANTI jadi fallback bila argumen null:
    $warehouseId = $warehouseId ?: optional(WmsContext::defaultWarehouse($branchId))->id;
    // sisa method TIDAK berubah (source_warehouse_id item sudah memakai $warehouseId).
```

**PENTING:** admin `POSController` memanggil `createSalesOrder(...)` TANPA argumen ke-10 → `$warehouseId` = null → perilaku lama utuh. Jangan sentuh pemanggilan di admin POS. Verifikasi `php -l app/Services/PosCheckoutService.php`.

## Langkah 2 — Routes

Di `routes/agent.php`, DI DALAM grup `['auth:customer','agent']` (grup `agent-order.`), tambah (import controller di atas file):
```php
use App\Http\Controllers\Agent\AgentPosController;
// ...
Route::get('/pos', [AgentPosController::class, 'index'])->name('pos');
Route::get('/pos/product-variants', [AgentPosController::class, 'getProductVariants'])->name('pos.product-variants');
Route::post('/pos/preview-promo', [AgentPosController::class, 'previewPromo'])->name('pos.preview-promo');
Route::post('/pos/payment', [AgentPosController::class, 'processPayment'])->name('pos.payment');
Route::get('/pos/payment/{orderId}/status', [AgentPosController::class, 'paymentStatus'])->name('pos.payment.status');
Route::get('/pos/payment/return', [AgentPosController::class, 'paymentReturn'])->name('pos.payment.return');
```
Verifikasi: `php artisan route:list --name=agent-order.pos`.

## Langkah 3 — `AgentPosController`

Buat `app/Http/Controllers/Agent/AgentPosController.php`. **Salin struktur method dari `Admin/POSController.php`** lalu ubah scoping berikut:

**Constructor & helper konteks** (mirip `AgentOrderController`):
```php
public function __construct(
    protected PosCheckoutService $checkout,
    protected ProductSearchService $productSearch,
    protected XenditService $xendit,
    protected PaymentSyncService $paymentSync,
) {}

protected function agent(): \App\Models\Partner\Agent
{
    // customer login yang lolos middleware 'agent'
    $agent = auth('customer')->user()?->agent;
    abort_if(! $agent, 403);
    return $agent;
}

protected function branchId(): ?string
{
    // Samakan dengan AgentOrderController: pakai ShopContextService.
    return (new \App\Services\Shop\ShopContextService(auth('customer')->user()))->branchId();
}

protected function agentWarehouseId(): ?string
{
    $agent = $this->agent();
    return optional(\App\Support\WmsContext::defaultAgentWarehouse($agent->id))->id
        ?: $agent->default_warehouse_id;
}
```
> Cek nama/method `ShopContextService::branchId()` dari pemakaian di `AgentOrderController` (grep) sebelum pakai; sesuaikan bila beda.

**`index(Request $request)`** — meniru `POSController::indexView()` dengan perubahan:
- `$branchId = $this->branchId()`. Produk, `priceLists` (default `REGULER`), `productTypes`, `methodPayments`, konfig Xendit → sama seperti admin (branch di-scope `$branchId`).
- **Buyer list = reseller agen**, BUKAN semua customer:
  ```php
  $resellers = $this->agent()->resellers()
      ->with('customer:id,code,name')
      ->whereNotNull('customer_id')          // hanya reseller yg punya Customer
      ->where('status', 'active')
      ->orderBy('name')
      ->get();
  // untuk dropdown: value = $r->customer_id, label = $r->name (fallback $r->customer?->name)
  ```
  (Tidak perlu `buildPosCustomerSelectGroups`/agent grouping — cukup daftar reseller agen ini.)
- **Tanpa pajak & membership tak wajib:** kirim `'taxRate' => 0`. Boleh tetap kirим konfig membership seperti admin (opsional; bila menyulitkan, set 0).
- `return view('agent.pos.index', [...])` (Slice 2 yang mengisi tampilan; untuk Slice 1 boleh view placeholder minimal yang me-render data agar tak error).

**`getProductVariants(Request $request)`** — SAMA seperti admin, tapi `$branchId = $this->branchId()`. Salin isi method admin (validasi product_id/price_list_id, `productSearch->mapVariantForPos`, dst).

**`previewPromo(Request $request)`** — SAMA seperti admin, tapi `$branchId = $this->branchId()`, `$orderWarehouseId = $this->agentWarehouseId()`. Sisanya identik.

**`processPayment(Request $request)`** — meniru admin, dengan perbedaan:
- `$branchId = $this->branchId()`; `$companyId` = company agen (cek cara `AgentOrderController` mengambilnya; mis. dari context/branch); `$userId = null` (agen bukan user web) ATAU simpan id customer agen bila kolom mendukung — **default `null` aman** (`created_by` nullable).
- Set konteks request sebelum totals:
  ```php
  $request->merge([
      'branch_id'   => $branchId,
      'company_id'  => $companyId,
      'tax_rate'    => 0,
      'tax_enabled' => false,
  ]);
  ```
- Validasi field sama seperti admin (`items.*`, `payment_method_id`, `customer_id` nullable uuid, `amount_paid`, dll). `customer_id` di sini = `reseller->customer_id` yang dipilih.
- Cash → `processCashPayment`; Xendit → `processXenditPayment` (salin dari admin). **Pada setiap pemanggilan `$this->checkout->createSalesOrder(...)`, tambahkan 2 argumen terakhir:** `'agent-pos'` (orderType) dan `$this->agentWarehouseId()` (warehouseId). Contoh:
  ```php
  $order = $this->checkout->createSalesOrder(
      $request, $totals, $salesNumber, $branchId, $companyId, $userId,
      'pending', 'unpaid', 'agent-pos', $this->agentWarehouseId(),
  );
  ```
- `generateSalesNumber($branchId)` — salin dari admin (prefix TRX per cabang). Boleh dipakai apa adanya.
- URL redirect Xendit → pakai route baru `agent-order.pos.payment.return` (bukan `transaction.pos.payment.return`).

**`paymentStatus` / `paymentReturn`** — salin dari admin; ganti scoping branch ke `$this->branchId()` dan route redirect `paymentReturn` ke `route('agent-order.pos', [...])`.

Verifikasi: `php -l app/Http/Controllers/Agent/AgentPosController.php`.

## Langkah 4 — View placeholder minimal (sementara)

Buat `resources/views/agent/pos/index.blade.php` minimal (Slice 2 mengganti dengan layout mockup). Cukup extends layout apa pun yang ada + render jumlah produk/reseller & sebuah form uji agar endpoint bisa dites manual. Tak perlu rapi — fokus Slice 1 adalah backend benar.

Verifikasi: `php artisan view:cache && php artisan view:clear`.

## Verifikasi akhir

```bash
php -l app/Services/PosCheckoutService.php
php -l app/Http/Controllers/Agent/AgentPosController.php
php artisan route:list --name=agent-order.pos
php artisan view:cache && php artisan view:clear
```
Smoke manual (login agen di `/agen-order/login`, buka `/agen-order/pos`):
- Data produk & daftar reseller agen tampil.
- Bayar Tunai untuk 1 reseller → order tersimpan `order_type='agent-pos'`, `customer_id` = customer reseller, status completed, `payment_status=paid`.
- **Stok berkurang di GUDANG AGEN** (bukan gudang cabang lain); tidak ada stock-in transfer (karena buyer reseller).
- Cek via tinker bila perlu: `DB::table('transaction.sales_orders')->where('order_type','agent-pos')->latest()->first()`.
- **Regresi:** admin POS `/pos` (login web) tetap normal (bayar Tunai/Xendit, stok berkurang di gudang cabang seperti biasa). `createSalesOrder` tanpa argumen baru = perilaku lama.

## Checklist

- [ ] `createSalesOrder()` dapat param opsional `?string $warehouseId = null` (fallback ke `WmsContext::defaultWarehouse`); admin POS tak diubah & tetap jalan.
- [ ] Routes `agent-order.pos*` terdaftar (guard customer + agent).
- [ ] `AgentPosController`: index (buyer=reseller agen, tax 0), getProductVariants, previewPromo, processPayment (cash+Xendit), paymentStatus, paymentReturn — semua di-scope `$this->branchId()` & gudang agen.
- [ ] `createSalesOrder` dipanggil dengan `'agent-pos'` + `agentWarehouseId()`.
- [ ] Smoke: order agent-pos completed, stok berkurang di gudang agen; admin POS & /shop tak berubah.
