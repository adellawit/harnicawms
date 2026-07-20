# Cursor Handoff — Training Academy Progress Toggle

> Ini adalah konten siap **copy-paste ke `.cursorrules`** (atau langsung ke prompt Cursor Composer) untuk mengimplementasikan fitur di `docs/superpowers/specs/2026-07-17-training-academy-progress-toggle-design.md`. Salin blok di bawah garis `--- MULAI .cursorrules ---` sampai `--- SELESAI .cursorrules ---`.

---

## Catatan sebelum ditempel

- Kalau file `.cursorrules` di repo sudah berisi aturan lain, JANGAN timpa — tempel blok ini sebagai tambahan di bagian bawah file yang sudah ada.
- Isi ini task-specific (untuk satu fitur). Setelah fitur ini selesai diimplementasikan, sebaiknya bagian "TASK" dihapus dari `.cursorrules` dan disisakan bagian "KONTEKS PROJECT" saja sebagai aturan permanen, supaya `.cursorrules` tidak menumpuk instruksi tugas lama.

---

--- MULAI .cursorrules ---

# KONTEKS PROJECT (berlaku permanen)

- Laravel 12, PostgreSQL dengan tabel schema-qualified (`training.*`, `marketing.*`, `product.*`, dst). Primary key UUID v7 via `->default(DB::raw('public.uuid_generate_v7()'))`.
- TIDAK ADA automated test suite di project ini (kebijakan yang disengaja). JANGAN menulis PHPUnit/Pest. Verifikasi cukup lewat: `php -l <file>`, `php artisan route:list`, `php artisan view:cache` (untuk cek error compile Blade), dan `php artisan tinker` untuk smoke test runtime.
- Migration folder per-schema (mis. `database/migrations/training/`) harus terdaftar di TIGA tempat: `app/Providers/AppServiceProvider.php` (`loadMigrationsFrom`), `app/Console/Commands/MigrateAllCommand.php` (`$migrationPaths` dan `$customSchemas`). Folder `database/migrations/training` **sudah terdaftar** — jangan didaftarkan ulang, cukup diverifikasi.
- Sistem permission: middleware `permission:<Nama Menu>,<is_create|is_read|is_update|is_delete>`, dicek lewat model `Menu` + `IamAccess` + `IamHasAccess`. Wiring menu+permission baru HARUS lewat seeder aditif (`Menu::updateOrCreate`, `IamAccess::firstOrCreate`, `IamHasAccess::updateOrCreate`) — JANGAN PERNAH memanggil `MenuSeeder`/`IamHasAccessSeeder` (keduanya `TRUNCATE ... CASCADE` seluruh menu di sistem).
- Role ID (pakai persis, jangan diubah): Administrator = `08d263b7-2c3b-43f0-a49b-b80d9d4b7685`, Marketing = `c1a2b3d4-e5f6-4a01-8b02-000000000001`, Agent = `2ac6f6a1-7b8c-4d9e-9f10-111213141516`.
- Views pakai Blade + komponen `<x-app-layout>`, `<x-page-header>`, `<x-alert>` yang sudah ada — ikuti pola file sejenis yang sudah ada, jangan membuat struktur baru.
- Model project TIDAK memakai namespace bertingkat untuk model umum (`App\Models\Menu`, `App\Models\IamAccess`, dst. — flat), tapi model per-fitur ADA di subfolder (`App\Models\Training\Course`, dst).

---

# TASK — Toggle Persentase Pembelajaran Training Academy

Baca spec lengkap di `docs/superpowers/specs/2026-07-17-training-academy-progress-toggle-design.md` untuk konteks bisnis. Implementasikan PERSIS 5 langkah berikut, berurutan, verifikasi tiap langkah sebelum lanjut.

## Langkah 1 — Migration `training.academy_settings`

Buat file `database/migrations/training/2026_07_17_000001_create_academy_settings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training.academy_settings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->boolean('show_progress_percentage')->default(true);
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training.academy_settings');
    }
};
```

Jalankan: `php artisan migrate --path=database/migrations/training/2026_07_17_000001_create_academy_settings_table.php --force`. Verifikasi tabel ada: `php artisan tinker --execute="echo DB::connection('pgsql')->getSchemaBuilder()->hasTable('training.academy_settings') ? 'Y' : 'N';"`.

## Langkah 2 — Model `AcademySetting`

Buat file `app/Models/Training/AcademySetting.php`:

```php
<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AcademySetting extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'training.academy_settings';

    protected $fillable = ['show_progress_percentage', 'updated_by'];

    protected $casts = [
        'show_progress_percentage' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['show_progress_percentage' => true]);
    }
}
```

Verifikasi lint: `php -l app/Models/Training/AcademySetting.php`. Verifikasi runtime: `php artisan tinker --execute="echo App\Models\Training\AcademySetting::current()->show_progress_percentage ? 'true' : 'false';"` — harus print `true` pada baris pertama (baris dibuat otomatis).

## Langkah 3 — Controller + Route halaman "Pengaturan Academy"

Buat file `app/Http/Controllers/Admin/Training/AcademySettingController.php`:

```php
<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\AcademySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AcademySettingController extends Controller
{
    public function edit(): View
    {
        $setting = AcademySetting::current();

        return view('admin.training.settings.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'show_progress_percentage' => 'required|boolean',
        ]);

        $setting = AcademySetting::current();
        $setting->update([
            'show_progress_percentage' => $validated['show_progress_percentage'],
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('training.settings.edit')
            ->with('success', 'Pengaturan berhasil disimpan.');
    }
}
```

Di `routes/training.php`, tambahkan DI DALAM grup `Route::prefix('training')->name('training.')->group(function () { ... })` yang sudah ada (setelah baris `Route::get('/reports', ...)`):

```php
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\Training\AcademySettingController::class, 'edit'])->name('edit')->middleware('permission:Training Academy,is_update');
            Route::post('/', [\App\Http\Controllers\Admin\Training\AcademySettingController::class, 'update'])->name('update')->middleware('permission:Training Academy,is_update');
        });
```

Buat view `resources/views/admin/training/settings/edit.blade.php`:

```blade
<x-app-layout>
    @section('title', 'Pengaturan Academy | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => 'Pengaturan', 'active' => true],
        ]" />

        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Pengaturan Academy</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('training.settings.update') }}">
                    @csrf
                    <div class="form-check form-switch mb-2">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            id="show_progress_percentage"
                            name="show_progress_percentage"
                            value="1"
                            @checked(old('show_progress_percentage', $setting->show_progress_percentage))
                        >
                        <label class="form-check-label" for="show_progress_percentage">
                            Tampilkan persentase pembelajaran ke Agent
                        </label>
                    </div>
                    <p class="text-muted small mb-4">
                        Saat dimatikan, progress bar dan info persentase pembelajaran (termasuk jumlah materi
                        selesai dan estimasi waktu tersisa) tidak akan tampil sama sekali ke Agent di halaman
                        Academy — bukan disembunyikan sebagian.
                    </p>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

**PENTING:** checkbox HTML yang tidak dicentang TIDAK mengirim value apa pun ke server (bukan `false`) — validasi `'show_progress_percentage' => 'required|boolean'` akan GAGAL saat user mematikan toggle. Tambahkan hidden input fallback SEBELUM checkbox agar `0` terkirim saat unchecked:

```blade
<input type="hidden" name="show_progress_percentage" value="0">
```
(letakkan tepat sebelum `<input type="checkbox" ...>` di dalam form, browser akan mengirim value checkbox yang menimpa hidden input kalau dicentang).

Verifikasi: `php -l app/Http/Controllers/Admin/Training/AcademySettingController.php`, `php artisan route:list --name=training.settings`, `php artisan view:cache`.

## Langkah 4 — Reaktivasi menu + seeder

Ganti ISI FILE `database/seeders/TrainingAccessSeeder.php` (timpa seluruhnya) dengan:

```php
<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

class TrainingAccessSeeder extends Seeder
{
    private const ADMINISTRATOR_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';
    private const MARKETING_ROLE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000001';
    private const AGENT_ROLE_ID = '2ac6f6a1-7b8c-4d9e-9f10-111213141516';

    private const MENU_MANAGE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000010';
    private const MENU_LEARN_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000011';
    private const MENU_SETTINGS_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000012';

    public function run(): void
    {
        // 1) Ensure Marketing role exists (safe if RoleSeeder already ran).
        Role::updateOrCreate(
            ['id' => self::MARKETING_ROLE_ID],
            ['name' => 'Marketing']
        );

        // 2) Restore the two menus (additive; not part of the truncating MenuSeeder).
        Menu::withTrashed()->where('id', self::MENU_MANAGE_ID)->restore();
        Menu::updateOrCreate(['id' => self::MENU_MANAGE_ID], [
            'parent_id' => null,
            'name' => 'Training Academy',
            'code' => 'training-academy',
            'text_sidebar' => 'Training Academy',
            'icon' => 'ti ti-school',
            'has_page' => true,
            'url_path' => 'training/courses',
            'route_name' => 'training.courses.index',
            'slug' => 'training-academy',
            'level_sidebar' => 1,
            'order_number' => 900,
            'is_label' => false,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
            'has_custom1' => false, 'has_custom2' => false, 'has_custom3' => false,
            'has_custom4' => false, 'has_custom5' => false,
        ]);

        Menu::withTrashed()->where('id', self::MENU_LEARN_ID)->restore();
        Menu::updateOrCreate(['id' => self::MENU_LEARN_ID], [
            'parent_id' => null,
            'name' => 'Academy',
            'code' => 'academy',
            'text_sidebar' => 'Academy',
            'icon' => 'ti ti-book',
            'has_page' => true,
            'url_path' => 'academy',
            'route_name' => 'academy.dashboard',
            'slug' => 'academy',
            'level_sidebar' => 1,
            'order_number' => 901,
            'is_label' => false,
            'has_create' => false,
            'has_update' => false,
            'has_read' => true,
            'has_delete' => false,
            'has_custom1' => false, 'has_custom2' => false, 'has_custom3' => false,
            'has_custom4' => false, 'has_custom5' => false,
        ]);

        // 3) NEW: "Pengaturan Academy" menu.
        Menu::updateOrCreate(['id' => self::MENU_SETTINGS_ID], [
            'parent_id' => null,
            'name' => 'Pengaturan Academy',
            'code' => 'training-academy-settings',
            'text_sidebar' => 'Pengaturan Academy',
            'icon' => 'ti ti-settings',
            'has_page' => true,
            'url_path' => 'training/settings',
            'route_name' => 'training.settings.edit',
            'slug' => 'training-academy-settings',
            'level_sidebar' => 1,
            'order_number' => 902,
            'is_label' => false,
            'has_create' => false,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => false,
            'has_custom1' => false, 'has_custom2' => false, 'has_custom3' => false,
            'has_custom4' => false, 'has_custom5' => false,
        ]);

        // 4) Grant management (CRUD) to Administrator + Marketing on "Training Academy".
        foreach ([self::ADMINISTRATOR_ROLE_ID, self::MARKETING_ROLE_ID] as $roleId) {
            $this->grant($roleId, self::MENU_MANAGE_ID, create: true, read: true, update: true, delete: true);
            $this->grant($roleId, self::MENU_SETTINGS_ID, create: false, read: true, update: true, delete: false);
        }

        // 5) Grant read to Agent on "Academy".
        $this->grant(self::AGENT_ROLE_ID, self::MENU_LEARN_ID, create: false, read: true, update: false, delete: false);
    }

    private function grant(string $roleId, string $menuId, bool $create, bool $read, bool $update, bool $delete): void
    {
        $iamAccess = IamAccess::firstOrCreate(
            ['role_id' => $roleId],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'is_notification' => false]
        );

        IamHasAccess::updateOrCreate(
            ['iam_access_id' => $iamAccess->id, 'sidebar_menu_id' => $menuId],
            [
                'is_create' => $create,
                'is_read' => $read,
                'is_update' => $update,
                'is_delete' => $delete,
                'is_custom_1' => false, 'is_custom_2' => false, 'is_custom_3' => false,
                'is_custom_4' => false, 'is_custom_5' => false,
            ]
        );
    }
}
```

Jalankan: `php artisan db:seed --class=TrainingAccessSeeder --force`, lalu jalankan LAGI untuk pastikan idempotent (tidak error saat dijalankan 2x). Verifikasi: `php artisan tinker --execute="echo App\Models\Menu::whereIn('id',['c1a2b3d4-e5f6-4a01-8b02-000000000010','c1a2b3d4-e5f6-4a01-8b02-000000000011','c1a2b3d4-e5f6-4a01-8b02-000000000012'])->count();"` — harus `3`.

## Langkah 5 — Tampilan Agent kondisional

Di `app/Http/Controllers/Academy/AcademyController.php`:

- Tambahkan `use App\Models\Training\AcademySetting;` di bagian `use` statement paling atas.
- Di method `dashboard()`, SEBELUM baris `return view('academy.dashboard', ...)`, tambahkan:
  ```php
  $showProgress = AcademySetting::current()->show_progress_percentage;
  ```
  Lalu ubah baris return-nya menjadi:
  ```php
  return view('academy.dashboard', compact('courses', 'progressByCourse', 'stats', 'continue', 'showProgress'));
  ```
- Di method `course(string $courseId)`, SEBELUM baris `return view('academy.course', ...)`, tambahkan:
  ```php
  $showProgress = AcademySetting::current()->show_progress_percentage;
  ```
  Lalu ubah baris return-nya menjadi:
  ```php
  return view('academy.course', compact('course', 'progress', 'completedIds', 'showProgress'));
  ```

Di `resources/views/academy/dashboard.blade.php`, ada 3 blok yang harus dibungkus `@if($showProgress) ... @endif`:

**Blok 1** — cari baris ini persis (progress bar statistik "Modul Selesai", TANPA teks persen):
```blade
<div class="progress" style="height:6px"><div class="progress-bar" role="progressbar"
    style="width: {{ $stats['modules_total'] ? round($stats['modules_completed'] / $stats['modules_total'] * 100) : 0 }}%"></div></div>
```
Bungkus jadi:
```blade
@if($showProgress)
<div class="progress" style="height:6px"><div class="progress-bar" role="progressbar"
    style="width: {{ $stats['modules_total'] ? round($stats['modules_completed'] / $stats['modules_total'] * 100) : 0 }}%"></div></div>
@endif
```

**Blok 2** — cari 2 baris ini persis (di dalam kartu "Sedang Dipelajari"):
```blade
<div class="progress" style="height:8px; max-width:600px"><div class="progress-bar bg-success" style="width: {{ $cp['percent'] }}%"></div></div>
<small class="text-muted">{{ $cp['percent'] }}% selesai @if($cp['has_minutes']) · {{ $cp['minutes_remaining'] }} menit tersisa @endif</small>
```
Bungkus jadi:
```blade
@if($showProgress)
<div class="progress" style="height:8px; max-width:600px"><div class="progress-bar bg-success" style="width: {{ $cp['percent'] }}%"></div></div>
<small class="text-muted">{{ $cp['percent'] }}% selesai @if($cp['has_minutes']) · {{ $cp['minutes_remaining'] }} menit tersisa @endif</small>
@endif
```

**Blok 3** — cari 2 baris ini persis (di dalam kartu tiap course, loop `@forelse ($courses as $course)`):
```blade
<div class="progress mb-2" style="height:6px"><div class="progress-bar bg-success" style="width: {{ $p['percent'] }}%"></div></div>
<small class="text-muted">{{ $p['percent'] }}% selesai</small>
```
Bungkus jadi:
```blade
@if($showProgress)
<div class="progress mb-2" style="height:6px"><div class="progress-bar bg-success" style="width: {{ $p['percent'] }}%"></div></div>
<small class="text-muted">{{ $p['percent'] }}% selesai</small>
@endif
```

**JANGAN sentuh** baris `card-footer` setelahnya (`$p['percent'] === 0 ? 'Mulai Kursus' : ...` dan class tombol `btn-primary`/`btn-outline-secondary`/`btn-success`) — itu logika STATUS tombol (bukan tampilan persen), harus tetap jalan meski toggle mati.

Di `resources/views/academy/course.blade.php`, ada 1 blok (2 baris) yang harus dibungkus:
```blade
<div class="progress mb-2" style="height:8px; max-width:600px"><div class="progress-bar bg-success" style="width: {{ $progress['percent'] }}%"></div></div>
<small class="text-muted">{{ $progress['completed_count'] }}/{{ $progress['total_materials'] }} materi · {{ $progress['percent'] }}% selesai
    @if($progress['has_minutes']) · {{ $progress['minutes_remaining'] }} menit tersisa @endif</small>
```
Bungkus jadi:
```blade
@if($showProgress)
<div class="progress mb-2" style="height:8px; max-width:600px"><div class="progress-bar bg-success" style="width: {{ $progress['percent'] }}%"></div></div>
<small class="text-muted">{{ $progress['completed_count'] }}/{{ $progress['total_materials'] }} materi · {{ $progress['percent'] }}% selesai
    @if($progress['has_minutes']) · {{ $progress['minutes_remaining'] }} menit tersisa @endif</small>
@endif
```

Verifikasi akhir: `php -l app/Http/Controllers/Academy/AcademyController.php`, `php artisan view:cache` (harus sukses tanpa error compile), lalu `php artisan tinker` untuk cek toggle bekerja:
```php
App\Models\Training\AcademySetting::current()->update(['show_progress_percentage' => false]);
// buka academy.dashboard / academy.courses.show di browser, pastikan SEMUA progress bar + teks persen hilang total
App\Models\Training\AcademySetting::current()->update(['show_progress_percentage' => true]);
// buka lagi, pastikan tampil seperti semula
```

## Checklist akhir

- [ ] Migration jalan, tabel `training.academy_settings` ada.
- [ ] `AcademySetting::current()` bekerja (auto-create baris default).
- [ ] Halaman `/training/settings` bisa diakses Administrator/Marketing, toggle tersimpan, checkbox-unchecked tetap terkirim `0` (bukan validation error).
- [ ] Seeder `TrainingAccessSeeder` idempotent (jalan 2x tanpa error), 3 menu (010/011/012) muncul di sidebar sesuai role.
- [ ] Toggle OFF → 4 blok progress (dashboard×3, course-detail×1) hilang TOTAL dari HTML, tombol "Mulai Kursus/Lanjutkan/Ulangi" tetap berfungsi benar.
- [ ] Toggle ON → tampilan kembali seperti semula.
- [ ] `php artisan route:list` menampilkan route `training.settings.edit`/`training.settings.update` dengan middleware `permission:Training Academy,is_update`.

--- SELESAI .cursorrules ---
