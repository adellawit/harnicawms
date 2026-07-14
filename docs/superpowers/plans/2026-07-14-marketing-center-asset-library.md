# Marketing Center — Asset Library (Sub-Project 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin/marketing-managed media asset library (categories + assets: image/video-link/pdf/wa-text) in a new `marketing` schema, and wire Training Academy so course materials and thumbnails can reference library assets.

**Architecture:** New PostgreSQL `marketing` schema (2 tables) following the exact patterns of the existing `training` schema. Server-rendered Blade in the admin `web` app, gated by the existing menu/permission system (new resource `Marketing Center` for Administrator + Marketing). Training Academy integration is by nullable FK reference (`course_materials.marketing_asset_id`, `courses.thumbnail_asset_id`) with delete-protection and a content-resolution layer on the Training models.

**Tech Stack:** Laravel 12, Blade + Bootstrap (Sneat theme, `ti ti-*` icons), PostgreSQL (schema-qualified tables, UUID v7 PK), jQuery for light interactivity.

## Global Constraints

- **No automated test suite exists.** Verification is `php -l` lint + `php artisan route:list` + targeted runtime `tinker` checks. Do NOT scaffold PHPUnit/Pest. Do NOT run `migrate:fresh`, `db:wipe`, or destructive DB commands — the configured DB (`DB_HOST=192.168.10.250`) is shared/live. `php artisan migrate` (additive) is allowed. Re-running idempotent seeders with `--class=` is allowed. **The two truncating seeders `MenuSeeder` and `IamHasAccessSeeder` must NEVER be run** — training/marketing menus live in additive seeders.
- **DB reachability:** the shared DB is only reachable from the controlling session's shell (subagents are network-sandboxed). Implementer subagents write code + lint + commit; the controller runs all `migrate`/`db:seed`/`tinker`/`route:list` steps.
- **PHP binary:** no system `php` on PATH. Use Herd's: `$PHP` = `"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php"` (keep the quotes — the path has a space).
- **All `marketing` tables:** UUID v7 PK (`->default(DB::raw('public.uuid_generate_v7()'))`), `$connection = 'pgsql'`, `company_id` uuid nullable, audit columns `created_by/updated_by/deleted_by`, `timestamps()`, `softDeletes()`.
- **Register the `marketing` migration folder in THREE places** (learned from a Training Academy Critical bug): `app/Providers/AppServiceProvider.php` `loadMigrationsFrom([...])`, `app/Console/Commands/MigrateAllCommand.php` `$migrationPaths`, and `$customSchemas`.
- **Asset types** exactly `image | video | pdf | text`. `image`/`pdf` → `file_path` (public disk); `video` → `link_url`; `text` → `body_text`.
- **Scope booleans:** `usable_in_marketing`, `usable_in_training` (at least one true). `text` must NOT be `usable_in_training`. `can_be_thumbnail` only meaningful for `image`.
- **Status:** assets `draft | active`; only `active` (and not soft-deleted) appear in the Training picker.
- **File uploads** on the `public` disk: `->store('marketing/assets', 'public')`.
- **FK targets:** company → `master_data.business_units`; training FKs → `training.*`.
- **Permission roles:** `Marketing Center` resource granted to `Administrator` (id `08d263b7-2c3b-43f0-a49b-b80d9d4b7685`) and `Marketing` (id `c1a2b3d4-e5f6-4a01-8b02-000000000001`). Super Admin (id `147c8a8e-52dc-4a79-a8ce-acb612b6e484`, iam_access `87d14961-0c14-474f-a6fa-b1130b521d39`) auto-bypasses.
- **Branch:** create `feature/marketing-center` off current `feature/training-academy` HEAD (the integration modifies Training Academy code, so it depends on it). Commit after each task.
- **Design system:** reuse `<x-app-layout>`, `<x-page-header>`, `<x-alert>`; reference `http://localhost:8000/design-system`.

---

## File Structure

**Migrations** (`database/migrations/marketing/`)
- `2026_07_14_000001_create_marketing_schema_tables.php` — schema + `asset_categories` + `assets`.
- `2026_07_14_000002_add_marketing_asset_refs_to_training.php` — adds `course_materials.marketing_asset_id` + `courses.thumbnail_asset_id`.

**Models** (`app/Models/Marketing/`)
- `Category.php`, `Asset.php`

**Modified models** (`app/Models/Training/`)
- `CourseMaterial.php` — add `marketingAsset()` relation + asset-aware content accessors.
- `Course.php` — add `thumbnailAsset()` relation + asset-aware thumbnail accessor.

**Controllers** (`app/Http/Controllers/Admin/Marketing/`)
- `CategoryController.php`, `AssetController.php`

**Requests** (`app/Http/Requests/Marketing/`)
- `CategoryRequest.php`, `AssetRequest.php`

**Routes**
- `routes/marketing.php` — `require`d from `routes/web.php`.

**Views** (`resources/views/admin/marketing/`)
- `categories/index.blade.php`, `assets/index.blade.php`, `assets/_form.blade.php`, `assets/create.blade.php`, `assets/edit.blade.php`, `assets/_picker.blade.php` (modal partial reused by Training content builder)

**Seeder**
- `database/seeders/MarketingAccessSeeder.php` (+ register in `DatabaseSeeder.php`)

**Modified Training files (integration)**
- `app/Http/Controllers/Admin/Training/CourseContentController.php` — accept `marketing_asset_id` in material store/update.
- `app/Http/Controllers/Admin/Training/CourseController.php` — accept `thumbnail_asset_id` in course store/update.
- `app/Http/Requests/Training/MaterialRequest.php` — allow asset-backed materials.
- `app/Http/Requests/Training/CourseRequest.php` — allow `thumbnail_asset_id`.
- `resources/views/admin/training/courses/content.blade.php` — "Pilih dari Pustaka" option in material modal.
- `resources/views/admin/training/courses/_form.blade.php` — thumbnail asset picker.
- `resources/views/academy/material.blade.php`, `academy/course.blade.php`, `academy/dashboard.blade.php` — render asset-backed content/thumbnails.

---

### Task 1: Marketing schema + tables + folder registration

**Files:**
- Create: `database/migrations/marketing/2026_07_14_000001_create_marketing_schema_tables.php`
- Modify: `app/Providers/AppServiceProvider.php` (add `database_path('migrations/marketing')` to the `loadMigrationsFrom([...])` array)
- Modify: `app/Console/Commands/MigrateAllCommand.php` (add `'database/migrations/marketing'` to `$migrationPaths` and `'marketing'` to `$customSchemas`)

**Interfaces:**
- Produces tables `marketing.asset_categories`, `marketing.assets`.

- [ ] **Step 1: Write the migration**

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
        DB::statement('CREATE SCHEMA IF NOT EXISTS marketing');

        Schema::create('marketing.asset_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->string('name', 150);
            $table->string('color', 7)->nullable();
            $table->string('icon', 60)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
        });

        Schema::create('marketing.assets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->uuid('category_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('type', 20);              // image | video | pdf | text
            $table->string('file_path')->nullable(); // image | pdf
            $table->string('link_url', 500)->nullable(); // video
            $table->text('body_text')->nullable();   // text (WA)
            $table->boolean('usable_in_marketing')->default(true);
            $table->boolean('usable_in_training')->default(false);
            $table->boolean('can_be_thumbnail')->default(false);
            $table->string('status', 20)->default('draft'); // draft | active
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index('category_id');
            $table->index('type');
            $table->index('status');
        });

        Schema::table('marketing.assets', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('marketing.asset_categories')->onDelete('restrict');
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing.assets');
        Schema::dropIfExists('marketing.asset_categories');
    }
};
```

- [ ] **Step 2: Register the folder in `AppServiceProvider`**

In `app/Providers/AppServiceProvider.php`, inside the `$this->loadMigrationsFrom([...])` array (which currently ends with `database_path('migrations/training'),`), append:

```php
            database_path('migrations/marketing'),
```

- [ ] **Step 3: Register in `MigrateAllCommand`**

In `app/Console/Commands/MigrateAllCommand.php`, add to `$migrationPaths` (after `'database/migrations/training',` — if training isn't present yet in this array on your branch, add it right after `'database/migrations/partner',`):

```php
        'database/migrations/marketing',
```

And add to `$customSchemas` (after `'training',` / `'partner',`):

```php
        'marketing',
```

- [ ] **Step 4: Lint (controller runs migrate)**

Run: `$PHP -l database/migrations/marketing/2026_07_14_000001_create_marketing_schema_tables.php && $PHP -l app/Providers/AppServiceProvider.php && $PHP -l app/Console/Commands/MigrateAllCommand.php`
Expected: `No syntax errors detected` three times.

- [ ] **Step 5: [CONTROLLER] Run migration + verify**

Run: `$PHP artisan migrate --path=database/migrations/marketing --force`
Then: `$PHP artisan tinker --execute="echo implode(',', array_column(DB::select(\"select tablename from pg_tables where schemaname='marketing' order by tablename\"), 'tablename'));"`
Expected: `asset_categories,assets`

- [ ] **Step 6: Commit**

```bash
git add database/migrations/marketing/2026_07_14_000001_create_marketing_schema_tables.php app/Providers/AppServiceProvider.php app/Console/Commands/MigrateAllCommand.php
git commit -m "feat(marketing): create marketing schema, asset tables, register migration folder"
```

---

### Task 2: Training integration migration (FK columns)

**Files:**
- Create: `database/migrations/marketing/2026_07_14_000002_add_marketing_asset_refs_to_training.php`

**Interfaces:**
- Consumes: `marketing.assets` (Task 1), existing `training.course_materials` + `training.courses`.
- Produces: `training.course_materials.marketing_asset_id`, `training.courses.thumbnail_asset_id` (both nullable FK → `marketing.assets`, onDelete restrict).

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training.course_materials', function (Blueprint $table) {
            $table->uuid('marketing_asset_id')->nullable()->after('youtube_url');
            $table->index('marketing_asset_id');
            $table->foreign('marketing_asset_id')->references('id')->on('marketing.assets')->onDelete('restrict');
        });

        Schema::table('training.courses', function (Blueprint $table) {
            $table->uuid('thumbnail_asset_id')->nullable()->after('thumbnail_path');
            $table->index('thumbnail_asset_id');
            $table->foreign('thumbnail_asset_id')->references('id')->on('marketing.assets')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('training.course_materials', function (Blueprint $table) {
            $table->dropForeign(['marketing_asset_id']);
            $table->dropColumn('marketing_asset_id');
        });
        Schema::table('training.courses', function (Blueprint $table) {
            $table->dropForeign(['thumbnail_asset_id']);
            $table->dropColumn('thumbnail_asset_id');
        });
    }
};
```

- [ ] **Step 2: Lint**

Run: `$PHP -l database/migrations/marketing/2026_07_14_000002_add_marketing_asset_refs_to_training.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: [CONTROLLER] Run migration + verify columns**

Run: `$PHP artisan migrate --path=database/migrations/marketing --force`
Then: `$PHP artisan tinker --execute="echo (Schema::hasColumn('training.course_materials','marketing_asset_id')?'mat_ok ':'mat_MISSING ').(Schema::hasColumn('training.courses','thumbnail_asset_id')?'course_ok':'course_MISSING');"`
Expected: `mat_ok course_ok`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/marketing/2026_07_14_000002_add_marketing_asset_refs_to_training.php
git commit -m "feat(marketing): add asset reference columns to training tables"
```

---

### Task 3: Models (Marketing + Training integration accessors)

**Files:**
- Create: `app/Models/Marketing/Category.php`, `app/Models/Marketing/Asset.php`
- Modify: `app/Models/Training/CourseMaterial.php`, `app/Models/Training/Course.php`

**Interfaces:**
- Produces:
  - `Marketing\Category` — fields `name,color,icon,sort_order,is_active`; relation `assets()`.
  - `Marketing\Asset` — fields `category_id,title,description,type,file_path,link_url,body_text,usable_in_marketing,usable_in_training,can_be_thumbnail,status,sort_order`; relation `category()`; scopes `active()`, `usableInTraining()`; accessors `file_url`, `video_embed_id`.
  - `Training\CourseMaterial` — relation `marketingAsset()`; accessors `is_asset_backed`, `effective_type` (`image|pdf|video`), `effective_file_url`, `effective_video_url`, `effective_video_embed_id`.
  - `Training\Course` — relation `thumbnailAsset()`; accessor `thumbnail_url` now asset-aware.

- [ ] **Step 1: Create `app/Models/Marketing/Category.php`**

```php
<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'marketing.asset_categories';

    protected $fillable = [
        'company_id', 'name', 'color', 'icon', 'sort_order', 'is_active',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id', 'id');
    }
}
```

- [ ] **Step 2: Create `app/Models/Marketing/Asset.php`**

```php
<?php

namespace App\Models\Marketing;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Asset extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'marketing.assets';

    protected $fillable = [
        'company_id', 'category_id', 'title', 'description', 'type',
        'file_path', 'link_url', 'body_text',
        'usable_in_marketing', 'usable_in_training', 'can_be_thumbnail',
        'status', 'sort_order', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'usable_in_marketing' => 'boolean',
        'usable_in_training' => 'boolean',
        'can_be_thumbnail' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeUsableInTraining(Builder $query): Builder
    {
        return $query->where('usable_in_training', true);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }

    /** YouTube embed id for a video link, or null if the link isn't YouTube. */
    public function getVideoEmbedIdAttribute(): ?string
    {
        return $this->link_url ? YouTube::embedId($this->link_url) : null;
    }
}
```

- [ ] **Step 3: Modify `app/Models/Training/CourseMaterial.php`**

Add `'marketing_asset_id'` to `$fillable` (after `'youtube_url',`). Add the import `use App\Models\Marketing\Asset;` near the other `use` lines. Then add these members to the class (after the existing `getYoutubeEmbedIdAttribute()`):

```php
    public function marketingAsset(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Marketing\Asset::class, 'marketing_asset_id', 'id');
    }

    public function getIsAssetBackedAttribute(): bool
    {
        return $this->marketing_asset_id !== null;
    }

    /** Normalised rendering type: image | pdf | video (local 'youtube' maps to 'video'). */
    public function getEffectiveTypeAttribute(): string
    {
        if ($this->is_asset_backed) {
            return $this->marketingAsset->type; // image | pdf | video
        }
        return $this->type === 'youtube' ? 'video' : $this->type; // image | pdf | video
    }

    public function getEffectiveFileUrlAttribute(): ?string
    {
        return $this->is_asset_backed ? $this->marketingAsset->file_url : $this->file_url;
    }

    public function getEffectiveVideoUrlAttribute(): ?string
    {
        return $this->is_asset_backed ? $this->marketingAsset->link_url : $this->youtube_url;
    }

    public function getEffectiveVideoEmbedIdAttribute(): ?string
    {
        $url = $this->effective_video_url;
        return $url ? \App\Support\YouTube::embedId($url) : null;
    }
```

> Note: the existing `getFileUrlAttribute()` / `getYoutubeEmbedIdAttribute()` stay for backward compatibility. The `effective_*` accessors are what the viewer will switch to.

- [ ] **Step 4: Modify `app/Models/Training/Course.php`**

Add `'thumbnail_asset_id'` to `$fillable` (after `'thumbnail_path',`). Add the `thumbnailAsset()` relation and replace the `getThumbnailUrlAttribute()` body to prefer the asset:

```php
    public function thumbnailAsset(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Marketing\Asset::class, 'thumbnail_asset_id', 'id');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_asset_id && $this->thumbnailAsset) {
            return $this->thumbnailAsset->file_url;
        }
        return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
    }
```

(`BelongsTo` is already imported in `Course.php`; `Storage` is already imported.)

- [ ] **Step 5: Lint**

Run: `$PHP -l app/Models/Marketing/Category.php && $PHP -l app/Models/Marketing/Asset.php && $PHP -l app/Models/Training/CourseMaterial.php && $PHP -l app/Models/Training/Course.php`
Expected: `No syntax errors detected` four times.

- [ ] **Step 6: [CONTROLLER] Runtime smoke (models resolve, accessors don't crash)**

Run:
```
$PHP artisan tinker --execute="
\$a = new App\Models\Marketing\Asset(['type'=>'video','link_url'=>'https://youtu.be/dQw4w9WgXcQ']);
echo 'embed='.\$a->video_embed_id.'\n';
\$m = new App\Models\Training\CourseMaterial(['type'=>'youtube','youtube_url'=>'https://youtu.be/dQw4w9WgXcQ']);
echo 'eff_type='.\$m->effective_type.', eff_embed='.\$m->effective_video_embed_id.\"\n\";
"
```
Expected: `embed=dQw4w9WgXcQ` and `eff_type=video, eff_embed=dQw4w9WgXcQ`.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Marketing/ app/Models/Training/CourseMaterial.php app/Models/Training/Course.php
git commit -m "feat(marketing): asset models + training content-resolution accessors"
```

---

### Task 4: Marketing Center menu & permission (safe additive seeding)

> Same rationale as Training: `MenuSeeder`/`IamHasAccessSeeder` truncate, so the menu + grants live in a NEW idempotent seeder.

**Files:**
- Create: `database/seeders/MarketingAccessSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (register after `TrainingAccessSeeder::class`)

**Interfaces:**
- Produces: menu `name = 'Marketing Center'` (route `marketing.assets.index`); grants CRUD to Administrator + Marketing.

- [ ] **Step 1: Create `database/seeders/MarketingAccessSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\IamAccess;
use App\Models\IamHasAccess;
use App\Models\Menu;
use Illuminate\Database\Seeder;

class MarketingAccessSeeder extends Seeder
{
    private const ADMINISTRATOR_ROLE_ID = '08d263b7-2c3b-43f0-a49b-b80d9d4b7685';
    private const MARKETING_ROLE_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000001';

    private const MENU_ID = 'c1a2b3d4-e5f6-4a01-8b02-000000000020';

    public function run(): void
    {
        Menu::updateOrCreate(['id' => self::MENU_ID], [
            'parent_id' => null,
            'name' => 'Marketing Center',
            'code' => 'marketing-center',
            'text_sidebar' => 'Marketing Center',
            'icon' => 'ti ti-speakerphone',
            'has_page' => true,
            'url_path' => 'marketing/assets',
            'route_name' => 'marketing.assets.index',
            'slug' => 'marketing-center',
            'level_sidebar' => 1,
            'order_number' => 902,
            'is_label' => false,
            'has_create' => true,
            'has_update' => true,
            'has_read' => true,
            'has_delete' => true,
            'has_custom1' => false, 'has_custom2' => false, 'has_custom3' => false,
            'has_custom4' => false, 'has_custom5' => false,
        ]);

        foreach ([self::ADMINISTRATOR_ROLE_ID, self::MARKETING_ROLE_ID] as $roleId) {
            $this->grant($roleId, self::MENU_ID);
        }
    }

    private function grant(string $roleId, string $menuId): void
    {
        $iamAccess = IamAccess::firstOrCreate(
            ['role_id' => $roleId],
            ['id' => (string) \Illuminate\Support\Str::uuid(), 'is_notification' => false]
        );

        IamHasAccess::updateOrCreate(
            ['iam_access_id' => $iamAccess->id, 'sidebar_menu_id' => $menuId],
            [
                'is_create' => true, 'is_read' => true, 'is_update' => true, 'is_delete' => true,
                'is_custom_1' => false, 'is_custom_2' => false, 'is_custom_3' => false,
                'is_custom_4' => false, 'is_custom_5' => false,
            ]
        );
    }
}
```

- [ ] **Step 2: Register in `DatabaseSeeder`**

In `database/seeders/DatabaseSeeder.php`, add after `TrainingAccessSeeder::class,`:

```php
            MarketingAccessSeeder::class,
```

- [ ] **Step 3: Lint**

Run: `$PHP -l database/seeders/MarketingAccessSeeder.php && $PHP -l database/seeders/DatabaseSeeder.php`
Expected: `No syntax errors detected` twice.

- [ ] **Step 4: [CONTROLLER] Run the seeder + verify**

Run: `$PHP artisan db:seed --class=MarketingAccessSeeder`
Then: `$PHP artisan tinker --execute="echo App\Models\Menu::where('name','Marketing Center')->count().' menu; '.App\Models\IamHasAccess::where('sidebar_menu_id','c1a2b3d4-e5f6-4a01-8b02-000000000020')->count().' grants';"`
Expected: `1 menu; 2 grants`

- [ ] **Step 5: Commit**

```bash
git add database/seeders/MarketingAccessSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(marketing): Marketing Center menu + permissions (additive seeder)"
```

---

### Task 5: Routes wiring + Category CRUD

**Files:**
- Create: `routes/marketing.php`
- Modify: `routes/web.php` (add `require __DIR__ . '/marketing.php';` next to the other requires near the bottom)
- Create: `app/Http/Controllers/Admin/Marketing/CategoryController.php`
- Create: `app/Http/Requests/Marketing/CategoryRequest.php`
- Create: `resources/views/admin/marketing/categories/index.blade.php`

**Interfaces:**
- Consumes: `Marketing\Category` (Task 3), permission `Marketing Center` (Task 4).
- Produces: route names `marketing.categories.index|store|update|destroy`; the `routes/marketing.php` file later tasks append to.

- [ ] **Step 1: Create `app/Http/Requests/Marketing/CategoryRequest.php`**

```php
<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
```

- [ ] **Step 2: Create `app/Http/Controllers/Admin/Marketing/CategoryController.php`**

```php
<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\CategoryRequest;
use App\Models\Marketing\Category;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('assets')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.marketing.categories.index', compact('categories'));
    }

    public function store(CategoryRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = Auth::id();

        Category::create($data);

        return redirect()->route('marketing.categories.index')->with('success', 'Kategori ditambahkan.');
    }

    public function update(CategoryRequest $request, string $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_by'] = Auth::id();

        $category->update($data);

        return redirect()->route('marketing.categories.index')->with('success', 'Kategori diperbarui.');
    }

    public function destroy(string $id)
    {
        $category = Category::withCount('assets')->findOrFail($id);
        if ($category->assets_count > 0) {
            return redirect()->route('marketing.categories.index')
                ->with('error', 'Kategori dipakai oleh aset dan tidak bisa dihapus.');
        }
        $category->deleted_by = Auth::id();
        $category->save();
        $category->delete();

        return redirect()->route('marketing.categories.index')->with('success', 'Kategori dihapus.');
    }
}
```

- [ ] **Step 3: Create `routes/marketing.php`**

```php
<?php

use App\Http\Controllers\Admin\Marketing\CategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing Center — admin/marketing only (guard web)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->prefix('marketing')->name('marketing.')->group(function () {
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index')->middleware('permission:Marketing Center,is_read');
        Route::post('/', [CategoryController::class, 'store'])->name('store')->middleware('permission:Marketing Center,is_create');
        Route::put('/{id}', [CategoryController::class, 'update'])->name('update')->middleware('permission:Marketing Center,is_update');
        Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('destroy')->middleware('permission:Marketing Center,is_delete');
    });
});
```

- [ ] **Step 4: Wire into `routes/web.php`**

Add near the other route-file requires at the bottom of `routes/web.php`:

```php
require __DIR__ . '/marketing.php';
```

- [ ] **Step 5: Create `resources/views/admin/marketing/categories/index.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Kategori Aset | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Marketing Center', 'url' => route('marketing.assets.index')],
            ['label' => 'Kategori', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Kategori Aset</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#catModal" onclick="fillCatForm({})"><i class="ti ti-plus me-1"></i> Tambah Kategori</button>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Urutan</th><th>Nama</th><th>Warna</th><th>Ikon</th><th>Aset</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($categories as $cat)
                            <tr>
                                <td>{{ $cat->sort_order }}</td>
                                <td>{{ $cat->name }}</td>
                                <td><span class="badge" style="background: {{ $cat->color ?: '#e7e7e7' }}">{{ $cat->color ?: '-' }}</span></td>
                                <td>@if($cat->icon)<i class="ti {{ $cat->icon }}"></i> <code>{{ $cat->icon }}</code>@else - @endif</td>
                                <td>{{ $cat->assets_count }}</td>
                                <td><span class="badge bg-label-{{ $cat->is_active ? 'success' : 'secondary' }}">{{ $cat->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#catModal" onclick='fillCatForm(@json($cat))'>Edit</button>
                                    <form action="{{ route('marketing.categories.destroy', $cat->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">Belum ada kategori.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="catModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" id="catForm" method="POST" action="{{ route('marketing.categories.store') }}">
                @csrf
                <input type="hidden" name="_method" id="catMethod" value="POST">
                <div class="modal-header"><h5 class="modal-title" id="catModalTitle">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="catName" class="form-control" required></div>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Warna</label>
                            <input type="color" name="color" id="catColor" class="form-control form-control-color" value="#5C9E84"></div>
                        <div class="col-6"><label class="form-label">Ikon (Tabler)</label>
                            <input type="text" name="icon" id="catIcon" class="form-control" placeholder="ti-photo"></div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-6"><label class="form-label">Urutan</label>
                            <input type="number" name="sort_order" id="catSort" class="form-control" value="0" min="0"></div>
                        <div class="col-6 d-flex align-items-end"><div class="form-check">
                            <input type="checkbox" name="is_active" id="catActive" class="form-check-input" value="1" checked>
                            <label class="form-check-label" for="catActive">Aktif</label></div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @push('page-js')
    <script>
        const catStoreUrl = "{{ route('marketing.categories.store') }}";
        const catUpdateBase = "{{ url('marketing/categories') }}";
        function fillCatForm(c) {
            const isEdit = !!c.id;
            document.getElementById('catModalTitle').textContent = isEdit ? 'Edit Kategori' : 'Tambah Kategori';
            document.getElementById('catForm').action = isEdit ? (catUpdateBase + '/' + c.id) : catStoreUrl;
            document.getElementById('catMethod').value = isEdit ? 'PUT' : 'POST';
            document.getElementById('catName').value = c.name || '';
            document.getElementById('catColor').value = c.color || '#5C9E84';
            document.getElementById('catIcon').value = c.icon || '';
            document.getElementById('catSort').value = c.sort_order ?? 0;
            document.getElementById('catActive').checked = c.id ? !!c.is_active : true;
        }
    </script>
    @endpush
</x-app-layout>
```

> The breadcrumb links to `marketing.assets.index` (Task 6). Implement Task 6 before browser-verifying this page, or verify the CRUD flow after Task 6.

- [ ] **Step 6: Lint**

Run: `$PHP -l app/Http/Requests/Marketing/CategoryRequest.php && $PHP -l app/Http/Controllers/Admin/Marketing/CategoryController.php && $PHP -l routes/marketing.php`
Expected: `No syntax errors detected` three times.

- [ ] **Step 7: [CONTROLLER] Verify routes**

Run: `$PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan route:list --name=marketing.categories`
Expected: lists `marketing.categories.index/store/update/destroy`.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Marketing/CategoryRequest.php app/Http/Controllers/Admin/Marketing/CategoryController.php routes/marketing.php routes/web.php resources/views/admin/marketing/categories/index.blade.php
git commit -m "feat(marketing): routes wiring and asset-category CRUD"
```

---

### Task 6: Asset CRUD (type-aware form + delete protection)

**Files:**
- Create: `app/Http/Requests/Marketing/AssetRequest.php`
- Create: `app/Http/Controllers/Admin/Marketing/AssetController.php`
- Modify: `routes/marketing.php` (append `assets` routes inside the `marketing.` group)
- Create: `resources/views/admin/marketing/assets/index.blade.php`, `_form.blade.php`, `create.blade.php`, `edit.blade.php`

**Interfaces:**
- Consumes: `Marketing\Asset`, `Marketing\Category`; for delete-protection `Training\CourseMaterial`, `Training\Course`.
- Produces: route names `marketing.assets.index|create|store|edit|update|destroy`.

- [ ] **Step 1: Create `app/Http/Requests/Marketing/AssetRequest.php`**

```php
<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $fileRequired = in_array($type, ['image', 'pdf'], true) && ! $this->route('id');

        return [
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'string', Rule::exists('pgsql.marketing.asset_categories', 'id')],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['image', 'video', 'pdf', 'text'])],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', $this->fileMimeRule($type)],
            'link_url' => [$type === 'video' ? 'required' : 'nullable', 'string', 'max:500', 'url'],
            'body_text' => [$type === 'text' ? 'required' : 'nullable', 'string'],
            'usable_in_marketing' => ['nullable', 'boolean'],
            'usable_in_training' => ['nullable', 'boolean'],
            'can_be_thumbnail' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'active'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function fileMimeRule(?string $type): string
    {
        return match ($type) {
            'image' => 'mimes:jpg,jpeg,png,webp',
            'pdf' => 'mimes:pdf',
            default => 'prohibited',
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $type = $this->input('type');
            $inMarketing = $this->boolean('usable_in_marketing');
            $inTraining = $this->boolean('usable_in_training');

            if (! $inMarketing && ! $inTraining) {
                $v->errors()->add('usable_in_marketing', 'Pilih minimal satu scope (Marketing atau Training).');
            }
            if ($type === 'text' && $inTraining) {
                $v->errors()->add('usable_in_training', 'Teks WA tidak bisa dipakai di Training.');
            }
            if ($this->boolean('can_be_thumbnail') && $type !== 'image') {
                $v->errors()->add('can_be_thumbnail', 'Hanya aset gambar yang bisa dijadikan thumbnail.');
            }
        });
    }
}
```

- [ ] **Step 2: Create `app/Http/Controllers/Admin/Marketing/AssetController.php`**

```php
<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\AssetRequest;
use App\Models\Marketing\Asset;
use App\Models\Marketing\Category;
use App\Models\Training\Course;
use App\Models\Training\CourseMaterial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function index()
    {
        $assets = Asset::with('category')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.marketing.assets.index', compact('assets'));
    }

    public function create()
    {
        return view('admin.marketing.assets.create', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(AssetRequest $request)
    {
        $data = $this->payload($request);
        $data['created_by'] = Auth::id();

        if (in_array($data['type'], ['image', 'pdf'], true) && $request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('marketing/assets', 'public');
        }

        Asset::create($data);

        return redirect()->route('marketing.assets.index')->with('success', 'Aset ditambahkan.');
    }

    public function edit(string $id)
    {
        $asset = Asset::findOrFail($id);

        return view('admin.marketing.assets.edit', [
            'asset' => $asset,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(AssetRequest $request, string $id)
    {
        $asset = Asset::findOrFail($id);
        $data = $this->payload($request);
        $data['updated_by'] = Auth::id();

        if (in_array($data['type'], ['image', 'pdf'], true) && $request->hasFile('file')) {
            if ($asset->file_path) {
                Storage::disk('public')->delete($asset->file_path);
            }
            $data['file_path'] = $request->file('file')->store('marketing/assets', 'public');
        }

        // If the type changed away from file-based, drop the stored file.
        if (! in_array($data['type'], ['image', 'pdf'], true) && $asset->file_path) {
            Storage::disk('public')->delete($asset->file_path);
            $data['file_path'] = null;
        }

        $asset->update($data);

        return redirect()->route('marketing.assets.index')->with('success', 'Aset diperbarui.');
    }

    public function destroy(string $id)
    {
        $asset = Asset::findOrFail($id);

        $usedByMaterials = CourseMaterial::where('marketing_asset_id', $asset->id)->count();
        $usedByThumbnails = Course::where('thumbnail_asset_id', $asset->id)->count();
        if ($usedByMaterials + $usedByThumbnails > 0) {
            return redirect()->route('marketing.assets.index')
                ->with('error', "Aset dipakai oleh {$usedByMaterials} materi & {$usedByThumbnails} thumbnail course, tidak bisa dihapus.");
        }

        if ($asset->file_path) {
            Storage::disk('public')->delete($asset->file_path);
        }
        $asset->deleted_by = Auth::id();
        $asset->save();
        $asset->delete();

        return redirect()->route('marketing.assets.index')->with('success', 'Aset dihapus.');
    }

    /** Build the shared attribute array, nulling fields that don't belong to the chosen type. */
    private function payload(AssetRequest $request): array
    {
        $type = $request->input('type');

        return [
            'title' => $request->string('title'),
            'category_id' => $request->input('category_id'),
            'description' => $request->input('description'),
            'type' => $type,
            'link_url' => $type === 'video' ? $request->input('link_url') : null,
            'body_text' => $type === 'text' ? $request->input('body_text') : null,
            'usable_in_marketing' => $request->boolean('usable_in_marketing'),
            'usable_in_training' => $type === 'text' ? false : $request->boolean('usable_in_training'),
            'can_be_thumbnail' => $type === 'image' ? $request->boolean('can_be_thumbnail') : false,
            'status' => $request->input('status'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
```

- [ ] **Step 3: Append asset routes to `routes/marketing.php`** (inside the `marketing.` group, after the categories group; add the `use` import at the top)

Add to the top `use` block:

```php
use App\Http\Controllers\Admin\Marketing\AssetController;
```

Add inside the group:

```php
    Route::prefix('assets')->name('assets.')->group(function () {
        Route::get('/', [AssetController::class, 'index'])->name('index')->middleware('permission:Marketing Center,is_read');
        Route::get('/create', [AssetController::class, 'create'])->name('create')->middleware('permission:Marketing Center,is_create');
        Route::post('/', [AssetController::class, 'store'])->name('store')->middleware('permission:Marketing Center,is_create');
        Route::get('/{id}/edit', [AssetController::class, 'edit'])->name('edit')->middleware('permission:Marketing Center,is_update');
        Route::put('/{id}', [AssetController::class, 'update'])->name('update')->middleware('permission:Marketing Center,is_update');
        Route::delete('/{id}', [AssetController::class, 'destroy'])->name('destroy')->middleware('permission:Marketing Center,is_delete');
    });
```

- [ ] **Step 4: Create `resources/views/admin/marketing/assets/_form.blade.php`**

```blade
@php($t = old('type', $asset->type ?? 'image'))
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Judul <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $asset->title ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Kategori <span class="text-danger">*</span></label>
        <select name="category_id" class="form-select" required>
            <option value="">— pilih —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $asset->category_id ?? '') === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $asset->description ?? '') }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">Tipe <span class="text-danger">*</span></label>
        <select name="type" id="assetType" class="form-select" onchange="toggleAssetFields()">
            <option value="image" @selected($t==='image')>Gambar</option>
            <option value="video" @selected($t==='video')>Video (link)</option>
            <option value="pdf" @selected($t==='pdf')>PDF</option>
            <option value="text" @selected($t==='text')>Teks WhatsApp</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="draft" @selected(old('status', $asset->status ?? 'draft')==='draft')>Draft</option>
            <option value="active" @selected(old('status', $asset->status ?? '')==='active')>Aktif</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Urutan</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $asset->sort_order ?? 0) }}" min="0">
    </div>

    <div class="col-12 asset-file" id="assetFileWrap">
        <label class="form-label">File</label>
        <input type="file" name="file" class="form-control">
        <small class="text-muted" id="assetFileHint"></small>
        @if (!empty($asset?->file_url))
            <div class="mt-2">
                @if(($asset->type ?? '')==='image')<img src="{{ $asset->file_url }}" style="max-height:90px" class="rounded">
                @else <a href="{{ $asset->file_url }}" target="_blank" class="btn btn-sm btn-outline-secondary">Lihat file</a>@endif
            </div>
        @endif
    </div>
    <div class="col-12 asset-link d-none" id="assetLinkWrap">
        <label class="form-label">URL Video (IG/TikTok/YouTube/dll)</label>
        <input type="text" name="link_url" class="form-control" value="{{ old('link_url', $asset->link_url ?? '') }}" placeholder="https://...">
    </div>
    <div class="col-12 asset-text d-none" id="assetTextWrap">
        <label class="form-label">Teks WhatsApp</label>
        <textarea name="body_text" class="form-control" rows="4">{{ old('body_text', $asset->body_text ?? '') }}</textarea>
    </div>

    <div class="col-12">
        <label class="form-label d-block">Scope pemakaian <span class="text-danger">*</span></label>
        <div class="form-check form-check-inline">
            <input type="checkbox" name="usable_in_marketing" id="scopeMarketing" class="form-check-input" value="1"
                @checked(old('usable_in_marketing', $asset->usable_in_marketing ?? true))>
            <label class="form-check-label" for="scopeMarketing">Marketing (reseller)</label>
        </div>
        <div class="form-check form-check-inline">
            <input type="checkbox" name="usable_in_training" id="scopeTraining" class="form-check-input" value="1"
                @checked(old('usable_in_training', $asset->usable_in_training ?? false))>
            <label class="form-check-label" for="scopeTraining">Training (course)</label>
        </div>
    </div>
    <div class="col-12 asset-thumb" id="assetThumbWrap">
        <div class="form-check">
            <input type="checkbox" name="can_be_thumbnail" id="canThumb" class="form-check-input" value="1"
                @checked(old('can_be_thumbnail', $asset->can_be_thumbnail ?? false))>
            <label class="form-check-label" for="canThumb">Boleh dipakai sebagai thumbnail course</label>
        </div>
    </div>
</div>

@push('page-js')
<script>
    function toggleAssetFields() {
        const t = document.getElementById('assetType').value;
        document.getElementById('assetFileWrap').classList.toggle('d-none', !(t === 'image' || t === 'pdf'));
        document.getElementById('assetLinkWrap').classList.toggle('d-none', t !== 'video');
        document.getElementById('assetTextWrap').classList.toggle('d-none', t !== 'text');
        document.getElementById('assetThumbWrap').classList.toggle('d-none', t !== 'image');
        document.getElementById('assetFileHint').textContent = t === 'image' ? 'Format .jpg/.png/.webp' : (t === 'pdf' ? 'Format .pdf' : '');
        // Teks WA tidak boleh Training-scoped.
        const train = document.getElementById('scopeTraining');
        if (t === 'text') { train.checked = false; train.disabled = true; } else { train.disabled = false; }
    }
    document.addEventListener('DOMContentLoaded', toggleAssetFields);
</script>
@endpush
```

- [ ] **Step 5: Create `resources/views/admin/marketing/assets/create.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Tambah Aset | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Marketing Center', 'url' => route('marketing.assets.index')],
            ['label' => 'Tambah Aset', 'active' => true],
        ]" />
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif
        <form method="POST" action="{{ route('marketing.assets.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="card mb-4"><div class="card-header"><h5 class="card-title mb-0">Detail Aset</h5></div>
                <div class="card-body">@include('admin.marketing.assets._form', ['asset' => null])</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan</button>
            <a href="{{ route('marketing.assets.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Create `resources/views/admin/marketing/assets/edit.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Edit Aset | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Marketing Center', 'url' => route('marketing.assets.index')],
            ['label' => 'Edit Aset', 'active' => true],
        ]" />
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif
        <form method="POST" action="{{ route('marketing.assets.update', $asset->id) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="card mb-4"><div class="card-header"><h5 class="card-title mb-0">Detail Aset</h5></div>
                <div class="card-body">@include('admin.marketing.assets._form', ['asset' => $asset])</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan</button>
            <a href="{{ route('marketing.assets.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Create `resources/views/admin/marketing/assets/index.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Marketing Center | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Marketing Center', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Materi Promosi (Pustaka Aset)</h5>
                <div>
                    <a href="{{ route('marketing.categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="ti ti-tags me-1"></i>Kategori</a>
                    <a href="{{ route('marketing.assets.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i>Tambah Aset</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Judul</th><th>Kategori</th><th>Tipe</th><th>Scope</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($assets as $asset)
                            <tr>
                                <td>{{ $asset->title }}</td>
                                <td>{{ $asset->category?->name ?? '-' }}</td>
                                <td><span class="badge bg-label-info">{{ $asset->type }}</span></td>
                                <td>
                                    @if($asset->usable_in_marketing)<span class="badge bg-label-primary">Marketing</span>@endif
                                    @if($asset->usable_in_training)<span class="badge bg-label-success">Training</span>@endif
                                    @if($asset->can_be_thumbnail)<span class="badge bg-label-secondary">Thumbnail</span>@endif
                                </td>
                                <td><span class="badge bg-label-{{ $asset->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($asset->status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('marketing.assets.edit', $asset->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <form action="{{ route('marketing.assets.destroy', $asset->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus aset ini?')">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">Belum ada aset.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $assets->links() }}</div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 8: Lint**

Run: `$PHP -l app/Http/Requests/Marketing/AssetRequest.php && $PHP -l app/Http/Controllers/Admin/Marketing/AssetController.php && $PHP -l routes/marketing.php`
Expected: `No syntax errors detected` three times.

- [ ] **Step 9: [CONTROLLER] Verify routes + runtime (create one asset of each type, delete-protection)**

Run: `$PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan route:list --name=marketing.assets`
Expected: lists index/create/store/edit/update/destroy.

Then a runtime smoke:
```
$PHP artisan tinker --execute="
\$cat = App\Models\Marketing\Category::firstOrCreate(['name'=>'__mktest__'],['is_active'=>true]);
\$a = App\Models\Marketing\Asset::create(['category_id'=>\$cat->id,'title'=>'V','type'=>'video','link_url'=>'https://youtu.be/dQw4w9WgXcQ','usable_in_training'=>true,'usable_in_marketing'=>false,'status'=>'active']);
echo 'embed='.\$a->video_embed_id.\"\n\";
\$a->forceDelete(); \$cat->forceDelete(); echo 'cleaned';
"
```
Expected: `embed=dQw4w9WgXcQ` then `cleaned`.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Requests/Marketing/AssetRequest.php app/Http/Controllers/Admin/Marketing/AssetController.php routes/marketing.php resources/views/admin/marketing/assets/
git commit -m "feat(marketing): asset CRUD with type-aware form and delete protection"
```

---

### Task 7: Asset picker endpoint + course-builder material integration

**Files:**
- Modify: `app/Http/Controllers/Admin/Marketing/AssetController.php` (add `picker`)
- Modify: `routes/marketing.php` (add `assets.picker` route)
- Modify: `app/Http/Requests/Training/MaterialRequest.php` (allow asset-backed)
- Modify: `app/Http/Controllers/Admin/Training/CourseContentController.php` (store/update accept `marketing_asset_id`)
- Modify: `resources/views/admin/training/courses/content.blade.php` (library picker in material modal)

**Interfaces:**
- Consumes: `Marketing\Asset` scopes `active()`/`usableInTraining()`.
- Produces: route `marketing.assets.picker` returning JSON `[{id,title,type,file_url,link_url}]`; course materials with `marketing_asset_id` set (`type` = asset type `image|pdf|video`).

- [ ] **Step 1: Add `picker()` to `AssetController`**

Add this method to `app/Http/Controllers/Admin/Marketing/AssetController.php` (and add `use Illuminate\Http\Request;` at the top):

```php
    public function picker(Request $request)
    {
        $type = $request->query('asset_type'); // image | pdf | video
        $assets = Asset::active()
            ->usableInTraining()
            ->when(in_array($type, ['image', 'pdf', 'video'], true), fn ($q) => $q->where('type', $type))
            ->whereIn('type', ['image', 'pdf', 'video'])
            ->orderBy('title')
            ->get()
            ->map(fn (Asset $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type,
                'file_url' => $a->file_url,
                'link_url' => $a->link_url,
            ]);

        return response()->json(['assets' => $assets]);
    }
```

- [ ] **Step 2: Add the picker route to `routes/marketing.php`** (inside the `marketing.` group, in the `assets` prefix group)

```php
        Route::get('/picker', [AssetController::class, 'picker'])->name('picker')->middleware('permission:Marketing Center,is_read');
```

> Place `/picker` BEFORE the `/{id}/edit` route is not required (different verb/path), but keep it inside the `assets.` group so the name becomes `marketing.assets.picker`.

- [ ] **Step 3: Modify `app/Http/Requests/Training/MaterialRequest.php`**

Replace the `rules()` method so an asset-backed material (has `marketing_asset_id`) skips file/type validation:

```php
    public function rules(): array
    {
        if ($this->filled('marketing_asset_id')) {
            return [
                'title' => ['required', 'string', 'max:200'],
                'marketing_asset_id' => ['required', 'string', \Illuminate\Validation\Rule::exists('pgsql.marketing.assets', 'id')],
                'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ];
        }

        $type = $this->input('type');
        $fileRequired = in_array($type, ['pdf', 'image'], true) && ! $this->route('material');

        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', \Illuminate\Validation\Rule::in(['pdf', 'image', 'youtube'])],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', $this->fileMimeRule($type)],
            'youtube_url' => [$type === 'youtube' ? 'required' : 'nullable', 'string', 'max:500', $this->youtubeRule($type)],
        ];
    }
```

(Leave `fileMimeRule()` and `youtubeRule()` unchanged.)

- [ ] **Step 4: Modify `CourseContentController` store/update to accept asset-backed materials**

In `app/Http/Controllers/Admin/Training/CourseContentController.php`, add `use App\Models\Marketing\Asset;` at the top. Replace `storeMaterial()` and `updateMaterial()` with:

```php
    public function storeMaterial(MaterialRequest $request, string $courseId, string $moduleId)
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);

        if ($request->filled('marketing_asset_id')) {
            $asset = Asset::active()->usableInTraining()
                ->whereIn('type', ['image', 'pdf', 'video'])
                ->findOrFail($request->input('marketing_asset_id'));

            CourseMaterial::create([
                'module_id' => $module->id,
                'company_id' => $module->company_id,
                'title' => $request->string('title'),
                'type' => $asset->type, // image | pdf | video
                'marketing_asset_id' => $asset->id,
                'estimated_minutes' => $request->filled('estimated_minutes') ? (int) $request->input('estimated_minutes') : null,
                'sort_order' => (int) $request->input('sort_order', 0),
                'created_by' => Auth::id(),
            ]);

            return back()->with('success', 'Materi (dari pustaka) ditambahkan.');
        }

        $data = $this->materialPayload($request);
        $data['module_id'] = $module->id;
        $data['company_id'] = $module->company_id;
        $data['created_by'] = Auth::id();

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('training/materials', 'public');
        }

        CourseMaterial::create($data);

        return back()->with('success', 'Materi ditambahkan.');
    }

    public function updateMaterial(MaterialRequest $request, string $courseId, string $moduleId, string $materialId)
    {
        $material = CourseMaterial::where('module_id', $moduleId)->findOrFail($materialId);

        if ($request->filled('marketing_asset_id')) {
            $asset = Asset::active()->usableInTraining()
                ->whereIn('type', ['image', 'pdf', 'video'])
                ->findOrFail($request->input('marketing_asset_id'));

            // Switching to an asset-backed material: drop any local file.
            if ($material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }
            $material->update([
                'title' => $request->string('title'),
                'type' => $asset->type,
                'marketing_asset_id' => $asset->id,
                'file_path' => null,
                'youtube_url' => null,
                'estimated_minutes' => $request->filled('estimated_minutes') ? (int) $request->input('estimated_minutes') : null,
                'sort_order' => (int) $request->input('sort_order', 0),
                'updated_by' => Auth::id(),
            ]);

            return back()->with('success', 'Materi diperbarui (dari pustaka).');
        }

        $data = $this->materialPayload($request);
        $data['updated_by'] = Auth::id();
        $data['marketing_asset_id'] = null; // reverting to a local material

        if ($request->hasFile('file')) {
            if ($material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }
            $data['file_path'] = $request->file('file')->store('training/materials', 'public');
        }

        if ($data['type'] === 'youtube') {
            $data['file_path'] = null;
        } else {
            $data['youtube_url'] = null;
        }

        $material->update($data);

        return back()->with('success', 'Materi diperbarui.');
    }
```

> `Storage` is already imported in this controller. `materialPayload()` stays unchanged. Ensure `'marketing_asset_id'` is in `CourseMaterial::$fillable` (done in Task 3).

- [ ] **Step 5: Add the library picker to the material modal in `content.blade.php`**

In `resources/views/admin/training/courses/content.blade.php`, inside the material modal `<form id="materialForm">` body (after the existing "Tipe" select block and before the estimasi field), add a source toggle + library select:

```blade
                <div class="mb-3">
                    <label class="form-label d-block">Sumber</label>
                    <div class="form-check form-check-inline">
                        <input type="radio" name="mat_source" id="srcUpload" class="form-check-input" value="upload" checked onchange="toggleMatSource()">
                        <label class="form-check-label" for="srcUpload">Upload / Link</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="radio" name="mat_source" id="srcLibrary" class="form-check-input" value="library" onchange="toggleMatSource()">
                        <label class="form-check-label" for="srcLibrary">Pilih dari Pustaka</label>
                    </div>
                </div>
                <div class="mb-3 d-none" id="matLibraryWrap">
                    <label class="form-label">Aset Pustaka</label>
                    <div class="row g-2">
                        <div class="col-5">
                            <select id="matLibType" class="form-select" onchange="loadLibraryAssets()">
                                <option value="image">Gambar</option>
                                <option value="pdf">PDF</option>
                                <option value="video">Video</option>
                            </select>
                        </div>
                        <div class="col-7">
                            <select name="marketing_asset_id" id="matLibAsset" class="form-select" disabled></select>
                        </div>
                    </div>
                    <small class="text-muted">Hanya aset ber-scope Training & berstatus aktif.</small>
                </div>
```

Then extend the modal's `@push('page-js')` script with:

```javascript
        const assetPickerUrl = "{{ route('marketing.assets.picker') }}";

        function toggleMatSource() {
            const lib = document.getElementById('srcLibrary').checked;
            document.getElementById('matLibraryWrap').classList.toggle('d-none', !lib);
            // Hide/disable upload-mode fields when using the library.
            document.getElementById('matType').closest('.mb-3').classList.toggle('d-none', lib);
            document.getElementById('matFileWrap').classList.toggle('d-none', lib || document.getElementById('matType').value === 'youtube');
            document.getElementById('matYoutubeWrap').classList.toggle('d-none', lib || document.getElementById('matType').value !== 'youtube');
            document.getElementById('matType').disabled = lib;
            document.getElementById('matFile').disabled = lib;
            document.getElementById('matYoutube').disabled = lib;
            const libSel = document.getElementById('matLibAsset');
            libSel.disabled = !lib;
            if (lib && libSel.options.length === 0) loadLibraryAssets();
        }

        function loadLibraryAssets() {
            const type = document.getElementById('matLibType').value;
            const sel = document.getElementById('matLibAsset');
            sel.innerHTML = '<option value="">memuat…</option>';
            fetch(assetPickerUrl + '?asset_type=' + encodeURIComponent(type), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => {
                    sel.innerHTML = '';
                    if (!d.assets || d.assets.length === 0) { sel.innerHTML = '<option value="">(tidak ada aset)</option>'; return; }
                    d.assets.forEach(a => {
                        const o = document.createElement('option');
                        o.value = a.id; o.textContent = a.title;
                        sel.appendChild(o);
                    });
                });
        }
```

Also update the existing `fillMaterial()` function so opening the modal resets to Upload mode: add at the end of `fillMaterial(mat)`:

```javascript
            document.getElementById('srcUpload').checked = true;
            document.getElementById('matLibAsset').innerHTML = '';
            toggleMatSource();
```

> When the library radio is selected, the upload fields are `disabled` so the browser omits them from the POST, and `MaterialRequest` takes the asset-backed branch (it sees `marketing_asset_id`).

- [ ] **Step 6: Lint**

Run: `$PHP -l app/Http/Controllers/Admin/Marketing/AssetController.php && $PHP -l app/Http/Requests/Training/MaterialRequest.php && $PHP -l app/Http/Controllers/Admin/Training/CourseContentController.php && $PHP -l routes/marketing.php`
Expected: `No syntax errors detected` four times.

- [ ] **Step 7: [CONTROLLER] Verify picker route + runtime (attach an asset as a material)**

Run: `$PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan route:list --name=marketing.assets.picker`
Expected: `marketing.assets.picker` listed.

Runtime: create a Training-scoped active image asset, a course/module, attach the asset as a material via `storeMaterial` logic, confirm `type='image'` and `marketing_asset_id` set and `effective_file_url` resolves; then clean up. (Controller writes a short tinker script mirroring the storeMaterial asset branch.)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Admin/Marketing/AssetController.php routes/marketing.php app/Http/Requests/Training/MaterialRequest.php app/Http/Controllers/Admin/Training/CourseContentController.php resources/views/admin/training/courses/content.blade.php
git commit -m "feat(marketing): asset picker + course material integration"
```

---

### Task 8: Course thumbnail integration + asset-aware rendering

**Files:**
- Modify: `app/Http/Requests/Training/CourseRequest.php` (allow `thumbnail_asset_id`)
- Modify: `app/Http/Controllers/Admin/Training/CourseController.php` (accept `thumbnail_asset_id`; pass thumbnail assets to views)
- Modify: `resources/views/admin/training/courses/_form.blade.php` (thumbnail-from-library picker)
- Modify: `resources/views/academy/material.blade.php` (asset-aware content rendering)
- Modify: `resources/views/academy/course.blade.php` (material icon uses `effective_type`)

**Interfaces:**
- Consumes: `Course.thumbnailAsset()` + `thumbnail_url` accessor (Task 3); `CourseMaterial.effective_*` accessors (Task 3); `Marketing\Asset`.
- Produces: courses with `thumbnail_asset_id`; viewers that render asset-backed materials/thumbnails.

- [ ] **Step 1: Modify `CourseRequest`**

In `app/Http/Requests/Training/CourseRequest.php`, add to the `rules()` array:

```php
            'thumbnail_asset_id' => ['nullable', 'string', \Illuminate\Validation\Rule::exists('pgsql.marketing.assets', 'id')],
```

- [ ] **Step 2: Modify `CourseController`**

In `app/Http/Controllers/Admin/Training/CourseController.php`, add `use App\Models\Marketing\Asset;` at the top. In `create()` and `edit()`, pass thumbnail-capable assets to the view. Change the `create()` return to:

```php
    public function create()
    {
        return view('admin.training.courses.create', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'thumbnailAssets' => $this->thumbnailAssets(),
        ]);
    }
```

Change `edit()` return to include `'thumbnailAssets' => $this->thumbnailAssets(),` alongside `course` + `categories`. Then add this private helper and include `thumbnail_asset_id` in `payload()`:

```php
    private function thumbnailAssets()
    {
        return Asset::active()->where('type', 'image')->where('can_be_thumbnail', true)
            ->orderBy('title')->get(['id', 'title']);
    }
```

In `payload()`, add:

```php
            'thumbnail_asset_id' => $request->input('thumbnail_asset_id') ?: null,
```

> The `getThumbnailUrlAttribute()` accessor (Task 3) already prefers the asset when `thumbnail_asset_id` is set, so no controller change to thumbnail rendering is needed. Uploading a file and picking an asset can coexist; the asset wins in the accessor.

- [ ] **Step 3: Add the thumbnail picker to `_form.blade.php`**

In `resources/views/admin/training/courses/_form.blade.php`, replace the thumbnail column (`<div class="col-md-6"> ... thumbnail ... </div>`) with:

```blade
    <div class="col-md-6">
        <label class="form-label">Thumbnail (upload)</label>
        <input type="file" name="thumbnail" class="form-control" accept="image/*">
        @if (!empty($course?->thumbnail_url))
            <img src="{{ $course->thumbnail_url }}" alt="thumb" class="mt-2 rounded" style="max-height:80px">
        @endif
    </div>
    <div class="col-md-6">
        <label class="form-label">Atau pilih dari Pustaka</label>
        <select name="thumbnail_asset_id" class="form-select">
            <option value="">— tidak pakai pustaka —</option>
            @foreach (($thumbnailAssets ?? []) as $ta)
                <option value="{{ $ta->id }}" @selected(old('thumbnail_asset_id', $course->thumbnail_asset_id ?? '') === $ta->id)>{{ $ta->title }}</option>
            @endforeach
        </select>
        <small class="text-muted">Jika dipilih, mengalahkan upload.</small>
    </div>
```

- [ ] **Step 4: Rewrite the content block in `academy/material.blade.php`**

Replace the `<div class="card-body">…type branches…</div>` inside the material card with:

```blade
            <div class="card-body">
                @php($et = $material->effective_type)
                @if ($et === 'video')
                    @if ($material->effective_video_embed_id)
                        <div class="ratio ratio-16x9">
                            <iframe src="https://www.youtube.com/embed/{{ $material->effective_video_embed_id }}" title="{{ $material->title }}" allowfullscreen></iframe>
                        </div>
                    @elseif ($material->effective_video_url)
                        <a href="{{ $material->effective_video_url }}" target="_blank" rel="noopener" class="btn btn-outline-primary"><i class="ti ti-external-link me-1"></i>Buka video</a>
                    @else
                        <div class="alert alert-warning mb-0">Materi tidak dapat ditampilkan.</div>
                    @endif
                @elseif ($et === 'image' && $material->effective_file_url)
                    <img src="{{ $material->effective_file_url }}" alt="{{ $material->title }}" class="img-fluid rounded">
                @elseif ($et === 'pdf' && $material->effective_file_url)
                    <div class="ratio" style="--bs-aspect-ratio: 130%">
                        <iframe src="{{ $material->effective_file_url }}" title="{{ $material->title }}"></iframe>
                    </div>
                    <a href="{{ $material->effective_file_url }}" target="_blank" class="btn btn-sm btn-outline-secondary mt-2"><i class="ti ti-download me-1"></i>Buka PDF</a>
                @else
                    <div class="alert alert-warning mb-0">Materi tidak dapat ditampilkan.</div>
                @endif
            </div>
```

- [ ] **Step 5: Update the material icon in `academy/course.blade.php`**

In `resources/views/academy/course.blade.php`, replace the material-type icon expression:

```blade
                                <i class="ti {{ $mat->effective_type === 'video' ? 'ti-brand-youtube' : ($mat->effective_type === 'pdf' ? 'ti-file-type-pdf' : 'ti-photo') }} me-2"></i>
```

- [ ] **Step 6: Lint**

Run: `$PHP -l app/Http/Requests/Training/CourseRequest.php && $PHP -l app/Http/Controllers/Admin/Training/CourseController.php`
Expected: `No syntax errors detected` twice.

- [ ] **Step 7: [CONTROLLER] Verify rendering + thumbnail resolution at runtime**

Run `route:clear`/`view:clear`, then a tinker scenario: create an image asset with `can_be_thumbnail=true, status=active`; a course referencing it via `thumbnail_asset_id`; assert `$course->thumbnail_url === asset.file_url`. Create a video asset (TikTok link) → course material referencing it; assert `effective_type='video'` and `effective_video_embed_id` is null (link card path). Clean up.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Training/CourseRequest.php app/Http/Controllers/Admin/Training/CourseController.php resources/views/admin/training/courses/_form.blade.php resources/views/academy/material.blade.php resources/views/academy/course.blade.php
git commit -m "feat(marketing): course thumbnail-from-library + asset-aware rendering"
```

---

## Final verification (after all tasks)

- [ ] `$PHP artisan route:list | grep marketing` shows category + asset + picker routes.
- [ ] As Super Admin, walk the flow: create asset category → create assets (image/video-link/pdf/text; set scopes, one Training-scoped image with `can_be_thumbnail`) → in Training, create a course whose thumbnail = a library asset, and a module material picked from the library → open `/academy`, confirm the course card thumbnail and the material viewer render the asset content (YouTube embeds, TikTok/IG shows a "Buka video" card, image/pdf inline).
- [ ] Delete-protection: try to delete an asset in use → blocked with a clear message.
- [ ] `git log --oneline` shows one commit per task on `feature/marketing-center`.

## Deferred / not in this plan (per spec)

Reseller/agent-facing consumption UI, usage tracking, WA-text personalization, Promo/Campaign management, Link Order analytics, per-reseller asset targeting.
