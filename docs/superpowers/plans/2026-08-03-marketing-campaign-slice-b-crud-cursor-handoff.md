# Cursor Handoff — Marketing Campaign Slice B: CRUD Admin

> Tunjuk ke Cursor: "Kerjakan docs/superpowers/plans/2026-08-03-marketing-campaign-slice-b-crud-cursor-handoff.md".
> Spec: `docs/superpowers/specs/2026-08-03-marketing-campaign-design.md`. Aturan permanen di `.cursorrules`.
> SCOPE Slice B = CRUD admin **Marketing Campaign** (entitas + tautan Promotion + flag reaktivasi + banner upload + periode aktif) + route + permission + menu. TIDAK menyentuh POS agen (Slice C) & TIDAK mengubah engine Promotion. Integrasi POS & reaktivasi = Slice C.

## Acuan pola (TIRU ini)

- Model/CRUD contoh: `app/Models/Promotion.php`, `App\Http\Controllers\Admin\PromotionController`, route `promotions.*` di `routes/distribution.php`, view `resources/views/admin/promotions/{index,create,edit,_form}.blade.php`.
- Upload file contoh: `App\Http\Controllers\Admin\Marketing\AssetController::store()` → `$request->file('file')->store('marketing/assets','public')`, hapus lama via `Storage::disk('public')->delete(...)`.
- Auto-code contoh: `Promotion::generateCode()` (prefix `PRM-Ym-0001`).

## Langkah 1 — Migrasi (2 tabel, schema `marketing`)

Buat migrasi (letakkan sesuai konvensi folder migrasi project — cek apakah ada subфолder per-domain seperti `database/migrations/`). Ikuti gaya UUID v7 + schema-qualified yang dipakai project (lihat migrasi lain, mis. `public.uuid_generate_v7()`).

**`marketing.campaigns`:**
```php
Schema::create('marketing.campaigns', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
    $table->uuid('company_id')->nullable();
    $table->string('code')->unique();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('banner_path')->nullable();
    $table->uuid('promotion_id')->nullable();            // FK → product.promotions (mekanik v1)
    $table->boolean('reactivates_reseller')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->integer('priority')->default(0);
    $table->string('status', 20)->default('active');     // draft/active/ended — opsional; boleh andalkan is_active + window
    $table->uuid('created_by')->nullable();
    $table->uuid('updated_by')->nullable();
    $table->uuid('deleted_by')->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->foreign('promotion_id')->references('id')->on('product.promotions')->nullOnDelete();
    $table->index(['is_active', 'starts_at', 'ends_at']);
});
```

**`marketing.campaign_participants`:**
```php
Schema::create('marketing.campaign_participants', function (Blueprint $table) {
    $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
    $table->uuid('campaign_id');
    $table->uuid('reseller_id');
    $table->uuid('sales_order_id')->nullable();
    $table->timestamp('joined_at')->nullable();
    $table->uuid('created_by')->nullable();
    $table->timestamps();
    $table->foreign('campaign_id')->references('id')->on('marketing.campaigns')->cascadeOnDelete();
    $table->foreign('reseller_id')->references('id')->on('partner.resellers')->cascadeOnDelete();
    $table->index(['campaign_id', 'reseller_id']);
});
```
> Cek konvensi FK/schema project (mis. apakah `product.promotions`, `partner.resellers`, `transaction.sales_orders` — samakan nama schema). Verifikasi `php artisan migrate`.

## Langkah 2 — Model

**`app/Models/Marketing/Campaign.php`** (`$table = 'marketing.campaigns'`, HasUuids/keyType string, SoftDeletes):
- `$fillable`: company_id, code, name, description, banner_path, promotion_id, reactivates_reseller, is_active, starts_at, ends_at, priority, status, created_by, updated_by, deleted_by.
- `$casts`: is_active bool, reactivates_reseller bool, starts_at/ends_at datetime, priority int.
- Relasi `promotion(): BelongsTo` → `App\Models\Promotion` (`promotion_id`).
- Relasi `participants(): HasMany` → `CampaignParticipant`.
- Accessor `getBannerUrlAttribute()`: `banner_path ? Storage::disk('public')->url($this->banner_path) : null`.
- `scopeActiveNow($q)`: `where('is_active', true)->where(fn($w)=>$w->whereNull('starts_at')->orWhere('starts_at','<=',now()))->where(fn($w)=>$w->whereNull('ends_at')->orWhere('ends_at','>=',now()))`.
- `static generateCode(?string $companyId=null)`: tiru `Promotion::generateCode()` tapi prefix `CMP-Ym-` (unik per company).

**`app/Models/Marketing/CampaignParticipant.php`** (`$table='marketing.campaign_participants'`): fillable campaign_id, reseller_id, sales_order_id, joined_at, created_by; relasi `campaign()`, `reseller()`.

## Langkah 3 — Route + Permission

Daftarkan CRUD (tiru blok `promotions.*` di `routes/distribution.php`, atau tempatkan di `routes/marketing.php` bila lebih cocok — konsisten dengan lokasi controller). Nama route `marketing.campaigns.*`, permission baru **"Marketing Campaign"** (is_read/create/update/delete):
```php
Route::prefix('campaigns')->name('campaigns.')->group(function () {
    Route::get('/', [CampaignController::class, 'index'])->name('index')->middleware('permission:Marketing Campaign,is_read');
    Route::get('/create', [CampaignController::class, 'create'])->name('create')->middleware('permission:Marketing Campaign,is_create');
    Route::post('/', [CampaignController::class, 'store'])->name('store')->middleware('permission:Marketing Campaign,is_create');
    Route::get('/{id}/edit', [CampaignController::class, 'edit'])->name('edit')->middleware('permission:Marketing Campaign,is_update');
    Route::put('/{id}', [CampaignController::class, 'update'])->name('update')->middleware('permission:Marketing Campaign,is_update');
    Route::delete('/{id}', [CampaignController::class, 'destroy'])->name('destroy')->middleware('permission:Marketing Campaign,is_delete');
});
```
> Bila di `routes/marketing.php` (prefix `marketing.`), nama akhirnya `marketing.campaigns.*`. Tambahkan permission "Marketing Campaign" lewat seeder permission yang dipakai project (cek `RoleSeeder`/permission seeder) agar admin bisa akses. Verifikasi `php artisan route:list --name=marketing.campaigns`.

## Langkah 4 — FormRequest + Controller

**`app/Http/Requests/Marketing/CampaignRequest.php`**:
```php
'name' => ['required','string','max:255'],
'description' => ['nullable','string'],
'promotion_id' => ['nullable','uuid','exists:product.promotions,id'],
'reactivates_reseller' => ['nullable','boolean'],
'is_active' => ['nullable','boolean'],
'starts_at' => ['nullable','date'],
'ends_at' => ['nullable','date','after_or_equal:starts_at'],
'priority' => ['nullable','integer','min:0'],
'banner' => ['nullable','image','max:4096'],
```

**`app/Http/Controllers/Admin/Marketing/CampaignController.php`** (tiru `PromotionController`):
- `index()`: `Campaign::with('promotion')->latest()->paginate(...)` → view.
- `create()`: kirim daftar Promotion aktif untuk dropdown (`Promotion::activeNow()->get(['id','name','code'])`) + `previewCode`.
- `store(CampaignRequest)`: `$data = $request->validated()`; `$data['code']=Campaign::generateCode($companyId)`; `$data['company_id']=$companyId`; `$data['reactivates_reseller']=$request->boolean('reactivates_reseller')`; `$data['is_active']=$request->boolean('is_active', true)`; handle banner upload (`$request->file('banner')?->store('marketing/campaigns','public')` → `banner_path`); `created_by`/`updated_by`; `Campaign::create($data)`.
- `edit($id)`: kirim campaign + daftar Promotion.
- `update(CampaignRequest,$id)`: sama; bila ada banner baru, hapus lama lalu simpan baru.
- `destroy($id)`: soft delete (`deleted_by` + delete). Boleh hapus banner file atau biarkan.
> `$companyId` — ikuti cara `PromotionController` mengambil company (mis. `optional(WmsContext::distributor())->id` atau company user). Samakan. Verifikasi `php -l`.

## Langkah 5 — Views (tiru `admin/promotions/*`)

Buat `resources/views/admin/marketing/campaigns/{index,create,edit,_form}.blade.php`:
- **index**: tabel (Code, Name, Promotion tertaut, Periode, Reaktivasi?, Aktif?, aksi Edit/Hapus). Tombol "Tambah Campaign".
- **_form**: field name, description, dropdown **Promotion** (opsional), input `starts_at`/`ends_at` (datetime), `priority`, toggle `is_active`, toggle **`reactivates_reseller`** ("Reaktivasi reseller yang ikut"), upload **banner** (`<input type="file" name="banner">`, form `enctype="multipart/form-data"`), preview banner lama saat edit.
- **create/edit**: bungkus `_form`. Pakai token warna brand & komponen UI yang sama dengan halaman Promotion/Asset (konsisten). Cek `/design-system` bila perlu.

Verifikasi `php artisan view:cache && view:clear`.

## Langkah 6 — Menu "Marketing Campaign" (baru)

Tambah menu sidebar **baru** "Marketing Campaign" → `marketing.campaigns.index` (setelah route ada), lewat migrasi/seeder menu idempotent (tiru cara MenuSeeder / record menu lain). Ini menu BARU (bukan yang di-relabel di Slice A). Icon mis. `ti ti-speakerphone`. Pastikan permission "Marketing Campaign" ter-assign ke role admin agar menu tampil.

## Verifikasi akhir

```bash
php -l app/Http/Controllers/Admin/Marketing/CampaignController.php
php artisan migrate
php artisan route:list --name=marketing.campaigns
php artisan view:cache && php artisan view:clear
```
Smoke manual (login admin):
- Menu "Marketing Campaign" tampil → index kosong awalnya.
- Tambah campaign: isi nama, pilih Promotion, set periode, aktifkan flag reaktivasi, upload banner → tersimpan, `code` auto `CMP-...`, tampil di index dengan banner & promotion tertaut.
- Edit (ganti banner → banner lama terhapus), hapus (soft delete).
- Promotion & Marketing Assets tak berubah; tak ada regresi menu lain.

## Checklist

- [ ] Migrasi `marketing.campaigns` + `marketing.campaign_participants` jalan (FK ke promotions/resellers benar).
- [ ] Model `Campaign` (relasi promotion/participants, `activeNow()`, `generateCode()` CMP-, banner url) + `CampaignParticipant`.
- [ ] Route `marketing.campaigns.*` + permission "Marketing Campaign" (ter-assign admin).
- [ ] `CampaignRequest` + `CampaignController` CRUD (banner upload + hapus lama, boolean flags, auto code).
- [ ] Views index/create/edit/_form (dropdown Promotion, toggle reaktivasi, upload banner) — konsisten dgn Promotion/Asset.
- [ ] Menu baru "Marketing Campaign" → index (idempotent).
- [ ] migrate/route/view bersih; Promotion & Assets tak berubah.
