# Training Academy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an internal Training Academy where Administrator/Marketing manage learning content (course → module → material) and Agents study it with progress tracking, no certification.

**Architecture:** New PostgreSQL `training` schema with 6 tables (categories, courses, modules, materials, material_progress, course_access). Server-rendered Blade in the existing admin `web` app, gated by the existing menu/permission system. Management UI under `/training` (resource `Training Academy`), learner UI under `/academy` (resource `Academy`). All progress derived from two tracking tables via a `ProgressService`.

**Tech Stack:** Laravel 12, Blade + Bootstrap (Sneat theme, `ti ti-*` Tabler icons), PostgreSQL (schema-qualified tables, UUID v7 PKs), jQuery for light interactivity, DomPDF not needed.

## Global Constraints

- **No automated test suite exists.** Verification is manual: `php -l` lint + `php artisan` commands + browser preview. Do NOT scaffold PHPUnit/Pest. Do NOT run `migrate:fresh`, `db:wipe`, or any destructive DB command — the configured DB (`DB_HOST=192.168.10.250`) is shared/live. `php artisan migrate` (additive) is allowed. Re-running individual idempotent seeders (`--class=`) is allowed.
- **PHP binary:** there is no system `php` on PATH. Use Herd's: `"/Users/vinzaipsandy/Library/Application Support/Herd/bin/php"`. In this doc it is written as `$PHP`.
- **All tables** live in schema `training`, use UUID v7 PK (`->default(DB::raw('public.uuid_generate_v7()'))`), `$connection = 'pgsql'`, `company_id` uuid nullable, audit columns `created_by/updated_by/deleted_by` (uuid nullable), `timestamps()`, and `softDeletes()` — EXCEPT the two progress tables (`material_progress`, `course_access`) which have no soft delete and no audit columns.
- **FK targets:** users → `auth.users`, company → `master_data.business_units`.
- **File uploads** use the `public` disk: `$request->file('x')->store('training/<subdir>', 'public')`; render with `Storage::url($path)` (matches `ProfileController`).
- **Material types** are exactly `pdf | image | youtube`. `estimated_minutes` is nullable (optional).
- **Course visibility:** agents see only `status = 'published'` courses; soft-deleted rows are excluded from every learner query and every progress calculation.
- **Permission resource names** (the middleware argument) equal the seeded `master_data.menus.name`: `Training Academy` (management) and `Academy` (learner).
- **Management roles:** `Administrator` (existing, id `08d263b7-2c3b-43f0-a49b-b80d9d4b7685`) and a NEW `Marketing` role. **Learner role:** `Agent` (existing, id `2ac6f6a1-7b8c-4d9e-9f10-111213141516`, iam_access id `2ac6f6a1-7b8c-4d9e-9f10-111213141517`). Super Admin (id `147c8a8e-52dc-4a79-a8ce-acb612b6e484`, iam_access `87d14961-0c14-474f-a6fa-b1130b521d39`) auto-bypasses all checks.
- **Branch:** `feature/training-academy`. Commit after each task.
- **Design system:** reuse components at `resources/views/components/` (e.g. `<x-app-layout>`, `<x-page-header>`); reference live examples at `http://localhost:8000/design-system`.

---

## File Structure

**Migrations**
- `database/migrations/training/2026_07_13_000001_create_training_schema_tables.php` — schema + all 6 tables.

**Models** (`app/Models/Training/`)
- `Category.php`, `Course.php`, `CourseModule.php`, `CourseMaterial.php`, `MaterialProgress.php`, `CourseAccess.php`

**Services**
- `app/Services/Training/ProgressService.php` — all progress math.
- `app/Support/YouTube.php` — parse a YouTube URL to an embed id (pure helper).

**Controllers**
- `app/Http/Controllers/Admin/Training/CategoryController.php`
- `app/Http/Controllers/Admin/Training/CourseController.php`
- `app/Http/Controllers/Admin/Training/CourseContentController.php` (modules + materials)
- `app/Http/Controllers/Admin/Training/ReportController.php`
- `app/Http/Controllers/Academy/AcademyController.php` (dashboard, course detail, material viewer, complete)

**Requests** (`app/Http/Requests/Training/`)
- `CategoryRequest.php`, `CourseRequest.php`, `ModuleRequest.php`, `MaterialRequest.php`

**Routes**
- `routes/training.php` — both `/training` (management) and `/academy` (learner) groups; `require`d from `routes/web.php`.

**Views** (`resources/views/admin/training/` and `resources/views/academy/`)
- management: `categories/index`, `courses/index`, `courses/create`, `courses/edit`, `courses/content`, `reports/index`
- learner: `academy/dashboard`, `academy/course`, `academy/material`

**Seeders (edit existing)**
- `RoleSeeder.php` (+Marketing role), `IamAccessSeeder.php` (+Marketing iam_access), `MenuSeeder.php` (+2 menus), `IamHasAccessSeeder.php` (+grants).

---

### Task 1: Database schema + tables

**Files:**
- Create: `database/migrations/training/2026_07_13_000001_create_training_schema_tables.php`

**Interfaces:**
- Produces tables: `training.course_categories`, `training.courses`, `training.course_modules`, `training.course_materials`, `training.material_progress`, `training.course_access`.

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
        DB::statement('CREATE SCHEMA IF NOT EXISTS training');

        Schema::create('training.course_categories', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->string('name', 150);
            $table->string('color', 7)->nullable();   // hex e.g. #5C9E84
            $table->string('icon', 60)->nullable();    // e.g. ti-briefcase
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
        });

        Schema::create('training.courses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->uuid('category_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('status', 20)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('company_id');
            $table->index('category_id');
            $table->index('status');
        });

        Schema::create('training.course_modules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->uuid('course_id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('course_id');
        });

        Schema::create('training.course_materials', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id')->nullable();
            $table->uuid('module_id');
            $table->string('title', 200);
            $table->string('type', 20); // pdf | image | youtube
            $table->string('file_path')->nullable();
            $table->string('youtube_url')->nullable();
            $table->integer('estimated_minutes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('module_id');
        });

        Schema::create('training.material_progress', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('user_id');
            $table->uuid('material_id');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'material_id']);
            $table->index('material_id');
        });

        Schema::create('training.course_access', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('user_id');
            $table->uuid('course_id');
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->uuid('last_material_id')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
            $table->index('course_id');
        });

        Schema::table('training.courses', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('training.course_categories')->onDelete('restrict');
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('set null');
        });
        Schema::table('training.course_modules', function (Blueprint $table) {
            $table->foreign('course_id')->references('id')->on('training.courses')->onDelete('cascade');
        });
        Schema::table('training.course_materials', function (Blueprint $table) {
            $table->foreign('module_id')->references('id')->on('training.course_modules')->onDelete('cascade');
        });
        Schema::table('training.material_progress', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');
            $table->foreign('material_id')->references('id')->on('training.course_materials')->onDelete('cascade');
        });
        Schema::table('training.course_access', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('training.courses')->onDelete('cascade');
            $table->foreign('last_material_id')->references('id')->on('training.course_materials')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training.course_access');
        Schema::dropIfExists('training.material_progress');
        Schema::dropIfExists('training.course_materials');
        Schema::dropIfExists('training.course_modules');
        Schema::dropIfExists('training.courses');
        Schema::dropIfExists('training.course_categories');
    }
};
```

- [ ] **Step 2: Lint**

Run: `$PHP -l database/migrations/training/2026_07_13_000001_create_training_schema_tables.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Run migration (additive, safe)**

Run: `$PHP artisan migrate --path=database/migrations/training`
Expected: `DONE` for `2026_07_13_000001_create_training_schema_tables`.

- [ ] **Step 4: Verify tables exist**

Run: `$PHP artisan tinker --execute="echo implode(',', array_column(DB::select(\"select tablename from pg_tables where schemaname='training' order by tablename\"), 'tablename'));"`
Expected: `course_access,course_categories,course_materials,course_modules,courses,material_progress`

- [ ] **Step 5: Commit**

```bash
git add database/migrations/training/2026_07_13_000001_create_training_schema_tables.php
git commit -m "feat(training): create training schema and tables"
```

---

### Task 2: Eloquent models

**Files:**
- Create: `app/Models/Training/Category.php`, `Course.php`, `CourseModule.php`, `CourseMaterial.php`, `MaterialProgress.php`, `CourseAccess.php`

**Interfaces:**
- Consumes: tables from Task 1.
- Produces:
  - `Category` fields `name,color,icon,sort_order,is_active`; relation `courses()`.
  - `Course` fields `category_id,title,description,thumbnail_path,status,published_at,sort_order`; relations `category()`, `modules()`; scope `published()`; accessor `thumbnail_url`.
  - `CourseModule` fields `course_id,title,description,sort_order`; relations `course()`, `materials()`.
  - `CourseMaterial` fields `module_id,title,type,file_path,youtube_url,estimated_minutes,sort_order`; relation `module()`; accessors `file_url`, `youtube_embed_id`.
  - `MaterialProgress` fields `user_id,material_id,viewed_at,completed_at` (no soft delete).
  - `CourseAccess` fields `user_id,course_id,first_opened_at,last_accessed_at,last_material_id` (no soft delete).

- [ ] **Step 1: Create `app/Models/Training/Category.php`**

```php
<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'training.course_categories';

    protected $fillable = [
        'company_id', 'name', 'color', 'icon', 'sort_order', 'is_active',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'category_id', 'id');
    }
}
```

- [ ] **Step 2: Create `app/Models/Training/Course.php`**

```php
<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Course extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'training.courses';

    protected $fillable = [
        'company_id', 'category_id', 'title', 'description', 'thumbnail_path',
        'status', 'published_at', 'sort_order', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class, 'course_id', 'id')->orderBy('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail_path ? Storage::url($this->thumbnail_path) : null;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
```

- [ ] **Step 3: Create `app/Models/Training/CourseModule.php`**

```php
<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseModule extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'training.course_modules';

    protected $fillable = [
        'company_id', 'course_id', 'title', 'description', 'sort_order',
        'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = ['sort_order' => 'integer'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id', 'id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class, 'module_id', 'id')->orderBy('sort_order');
    }
}
```

- [ ] **Step 4: Create `app/Models/Training/CourseMaterial.php`**

```php
<?php

namespace App\Models\Training;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CourseMaterial extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'training.course_materials';

    protected $fillable = [
        'company_id', 'module_id', 'title', 'type', 'file_path', 'youtube_url',
        'estimated_minutes', 'sort_order', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'estimated_minutes' => 'integer',
        'sort_order' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'module_id', 'id');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }

    public function getYoutubeEmbedIdAttribute(): ?string
    {
        return $this->youtube_url ? YouTube::embedId($this->youtube_url) : null;
    }
}
```

- [ ] **Step 5: Create `app/Models/Training/MaterialProgress.php`**

```php
<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MaterialProgress extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'training.material_progress';

    protected $fillable = ['user_id', 'material_id', 'viewed_at', 'completed_at'];

    protected $casts = [
        'viewed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
```

- [ ] **Step 6: Create `app/Models/Training/CourseAccess.php`**

```php
<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CourseAccess extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'training.course_access';

    protected $fillable = ['user_id', 'course_id', 'first_opened_at', 'last_accessed_at', 'last_material_id'];

    protected $casts = [
        'first_opened_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];
}
```

- [ ] **Step 7: Lint all six**

Run: `for f in app/Models/Training/*.php; do $PHP -l "$f"; done`
Expected: `No syntax errors detected` six times. (`YouTube` support class is created in Task 6; if verifying now, that import is unresolved only at runtime, not at lint time.)

- [ ] **Step 8: Commit**

```bash
git add app/Models/Training/
git commit -m "feat(training): eloquent models for training schema"
```

---

### Task 3: Menus, Marketing role & permissions (safe additive seeding)

> **Why additive:** `MenuSeeder` and `IamHasAccessSeeder` both `TRUNCATE ... CASCADE` and rebuild from their arrays — running them on the shared DB would erase any UI-configured menus/permissions. So training menus + grants live in a NEW idempotent `TrainingAccessSeeder` (no truncate). `RoleSeeder` is safe (it uses `updateOrCreate`).

**Files:**
- Modify: `database/seeders/RoleSeeder.php` (add Marketing role to the `$roles` array)
- Create: `database/seeders/TrainingAccessSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php` (register `TrainingAccessSeeder` after `IamHasAccessSeeder`)

**Interfaces:**
- Consumes: nothing (pure DB rows).
- Produces: menu `name = 'Training Academy'` (route `training.courses.index`) and `name = 'Academy'` (route `academy.dashboard`); Marketing role id `c1a2b3d4-e5f6-4a01-8b02-000000000001`; iam_has_access grants: Administrator+Marketing → Training Academy (CRUD), Agent → Academy (read).

- [ ] **Step 1: Add Marketing role to `RoleSeeder`**

In `database/seeders/RoleSeeder.php`, add this entry to the `$roles` array (after the Agent entry):

```php
            [
                'id' => 'c1a2b3d4-e5f6-4a01-8b02-000000000001',
                'name' => 'Marketing',
            ],
```

- [ ] **Step 2: Create `database/seeders/TrainingAccessSeeder.php`**

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

    public function run(): void
    {
        // 1) Ensure Marketing role exists (safe if RoleSeeder already ran).
        Role::updateOrCreate(
            ['id' => self::MARKETING_ROLE_ID],
            ['name' => 'Marketing']
        );

        // 2) Ensure the two menus exist (additive; not part of the truncating MenuSeeder).
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

        // 3) Grant management (CRUD) to Administrator + Marketing on "Training Academy".
        foreach ([self::ADMINISTRATOR_ROLE_ID, self::MARKETING_ROLE_ID] as $roleId) {
            $this->grant($roleId, self::MENU_MANAGE_ID, create: true, read: true, update: true, delete: true);
        }

        // 4) Grant read to Agent on "Academy".
        $this->grant(self::AGENT_ROLE_ID, self::MENU_LEARN_ID, create: false, read: true, update: false, delete: false);
    }

    private function grant(string $roleId, string $menuId, bool $create, bool $read, bool $update, bool $delete): void
    {
        // IamAccess has no HasUuids trait, so provide an explicit id for the create branch.
        // firstOrCreate ignores the create attributes when a row already exists (Agent/Super Admin do).
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

> Note: `IamAccess` has a string, non-incrementing PK and no `HasUuids` trait, so the `grant()` helper passes an explicit `id` in the create branch (see code). Existing containers (Agent, Super Admin) are matched by `role_id` and reused unchanged.

- [ ] **Step 3: Register the seeder in `DatabaseSeeder`**

In `database/seeders/DatabaseSeeder.php`, add `TrainingAccessSeeder::class,` immediately after `IamHasAccessSeeder::class,`:

```php
            IamHasAccessSeeder::class,
            TrainingAccessSeeder::class,
```

- [ ] **Step 4: Lint**

Run: `$PHP -l database/seeders/TrainingAccessSeeder.php && $PHP -l database/seeders/RoleSeeder.php && $PHP -l database/seeders/DatabaseSeeder.php`
Expected: `No syntax errors detected` three times.

- [ ] **Step 5: Run ONLY the safe seeders (never MenuSeeder/IamHasAccessSeeder here)**

Run: `$PHP artisan db:seed --class=RoleSeeder && $PHP artisan db:seed --class=TrainingAccessSeeder`
Expected: both complete without error.

- [ ] **Step 6: Verify grants landed**

Run: `$PHP artisan tinker --execute="echo App\Models\Menu::whereIn('name',['Training Academy','Academy'])->count().' menus; '.App\Models\IamHasAccess::whereIn('sidebar_menu_id',['c1a2b3d4-e5f6-4a01-8b02-000000000010','c1a2b3d4-e5f6-4a01-8b02-000000000011'])->count().' grants';"`
Expected: `2 menus; 3 grants`

- [ ] **Step 7: Commit**

```bash
git add database/seeders/RoleSeeder.php database/seeders/TrainingAccessSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(training): add Marketing role + training menus/permissions (additive seeder)"
```

---

### Task 4: Routes wiring, YouTube helper & Category CRUD

**Files:**
- Create: `routes/training.php`
- Modify: `routes/web.php` (add `require __DIR__.'/training.php';` next to the other requires near the bottom, e.g. after `require __DIR__ . '/distribution.php';`)
- Create: `app/Support/YouTube.php`
- Create: `app/Http/Controllers/Admin/Training/CategoryController.php`
- Create: `app/Http/Requests/Training/CategoryRequest.php`
- Create: `resources/views/admin/training/categories/index.blade.php`

**Interfaces:**
- Consumes: `Category` model (Task 2), permission `Training Academy` (Task 3).
- Produces: `YouTube::embedId(string $url): ?string`; route names `training.categories.index|store|update|destroy`; the `routes/training.php` file that later tasks append route groups to.

- [ ] **Step 1: Create `app/Support/YouTube.php`**

```php
<?php

namespace App\Support;

class YouTube
{
    /**
     * Extract the 11-char video id from any common YouTube URL form.
     * Supports: youtu.be/<id>, youtube.com/watch?v=<id>, /embed/<id>, /shorts/<id>.
     */
    public static function embedId(string $url): ?string
    {
        $url = trim($url);
        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/watch\?[^ ]*v=([A-Za-z0-9_-]{11})~',
            '~youtube\.com/embed/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $url, $m)) {
                return $m[1];
            }
        }
        return null;
    }
}
```

- [ ] **Step 2: Create `app/Http/Requests/Training/CategoryRequest.php`**

```php
<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware handles permission
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

- [ ] **Step 3: Create `app/Http/Controllers/Admin/Training/CategoryController.php`**

```php
<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\CategoryRequest;
use App\Models\Training\Category;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('courses')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.training.categories.index', compact('categories'));
    }

    public function store(CategoryRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['created_by'] = Auth::id();

        Category::create($data);

        return redirect()->route('training.categories.index')
            ->with('success', 'Kategori ditambahkan.');
    }

    public function update(CategoryRequest $request, string $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['updated_by'] = Auth::id();

        $category->update($data);

        return redirect()->route('training.categories.index')
            ->with('success', 'Kategori diperbarui.');
    }

    public function destroy(string $id)
    {
        $category = Category::withCount('courses')->findOrFail($id);
        if ($category->courses_count > 0) {
            return redirect()->route('training.categories.index')
                ->with('error', 'Kategori dipakai oleh course dan tidak bisa dihapus.');
        }
        $category->deleted_by = Auth::id();
        $category->save();
        $category->delete();

        return redirect()->route('training.categories.index')
            ->with('success', 'Kategori dihapus.');
    }
}
```

- [ ] **Step 4: Create `routes/training.php`**

```php
<?php

use App\Http\Controllers\Admin\Training\CategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Training Academy — management (/training) + learner (/academy)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {

    // === Management (Administrator / Marketing) ===
    Route::prefix('training')->name('training.')->group(function () {
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index')->middleware('permission:Training Academy,is_read');
            Route::post('/', [CategoryController::class, 'store'])->name('store')->middleware('permission:Training Academy,is_create');
            Route::put('/{id}', [CategoryController::class, 'update'])->name('update')->middleware('permission:Training Academy,is_update');
            Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('destroy')->middleware('permission:Training Academy,is_delete');
        });
    });

});
```

- [ ] **Step 5: Wire `routes/training.php` into `routes/web.php`**

Add next to the other route-file requires near the bottom of `routes/web.php`:

```php
require __DIR__ . '/training.php';
```

- [ ] **Step 6: Create `resources/views/admin/training/categories/index.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Kategori Training | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => 'Kategori', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Kategori Course</h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#catModal"
                    onclick="fillCatForm({})"><i class="ti ti-plus me-1"></i> Tambah Kategori</button>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Urutan</th><th>Nama</th><th>Warna</th><th>Ikon</th><th>Course</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($categories as $cat)
                            <tr>
                                <td>{{ $cat->sort_order }}</td>
                                <td>{{ $cat->name }}</td>
                                <td><span class="badge" style="background: {{ $cat->color ?: '#e7e7e7' }}">{{ $cat->color ?: '-' }}</span></td>
                                <td>@if($cat->icon)<i class="ti {{ $cat->icon }}"></i> <code>{{ $cat->icon }}</code>@else - @endif</td>
                                <td>{{ $cat->courses_count }}</td>
                                <td><span class="badge bg-label-{{ $cat->is_active ? 'success' : 'secondary' }}">{{ $cat->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#catModal"
                                        onclick='fillCatForm(@json($cat))'>Edit</button>
                                    <form action="{{ route('training.categories.destroy', $cat->id) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm('Hapus kategori ini?')">
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
            <form class="modal-content" id="catForm" method="POST" action="{{ route('training.categories.store') }}">
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
                            <input type="text" name="icon" id="catIcon" class="form-control" placeholder="ti-briefcase"></div>
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
        const catStoreUrl = "{{ route('training.categories.store') }}";
        const catUpdateBase = "{{ url('training/categories') }}";
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

- [ ] **Step 7: Lint**

Run: `$PHP -l app/Support/YouTube.php && $PHP -l app/Http/Requests/Training/CategoryRequest.php && $PHP -l app/Http/Controllers/Admin/Training/CategoryController.php && $PHP -l routes/training.php`
Expected: `No syntax errors detected` four times.

- [ ] **Step 8: Verify route registration & clear caches**

Run: `$PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan route:list --name=training.categories`
Expected: lists `training.categories.index/store/update/destroy`.

- [ ] **Step 9: Browser verification**

1. Ensure the dev server is running (`http://localhost:8000`).
2. Log in as the Super Admin dev account (`demo@wit.id` / `demo2026*#`).
3. Navigate to `http://localhost:8000/training/categories`.
4. Confirm the page renders (200), "Tambah Kategori" opens the modal, create a category "Penjualan" (color `#5C9E84`, icon `ti-briefcase`), submit → row appears with success alert.
5. Edit it, then delete it → success alerts.
Use the preview tools (read_page / computer / read_console_messages) to confirm no console/network errors.

- [ ] **Step 10: Commit**

```bash
git add app/Support/YouTube.php app/Http/Requests/Training/CategoryRequest.php app/Http/Controllers/Admin/Training/CategoryController.php routes/training.php routes/web.php resources/views/admin/training/categories/index.blade.php
git commit -m "feat(training): routes wiring, youtube helper, category CRUD"
```

---

### Task 5: Course CRUD (thumbnail + draft/published)

**Files:**
- Create: `app/Http/Requests/Training/CourseRequest.php`
- Create: `app/Http/Controllers/Admin/Training/CourseController.php`
- Modify: `routes/training.php` (append course routes inside the `training.` group)
- Create: `resources/views/admin/training/courses/index.blade.php`
- Create: `resources/views/admin/training/courses/_form.blade.php`
- Create: `resources/views/admin/training/courses/create.blade.php`
- Create: `resources/views/admin/training/courses/edit.blade.php`

**Interfaces:**
- Consumes: `Course`, `Category` (Task 2); YouTube n/a.
- Produces: route names `training.courses.index|create|store|edit|update|destroy|publish`; `Course` rows with `status` and `thumbnail_path`.

- [ ] **Step 1: Create `app/Http/Requests/Training/CourseRequest.php`**

```php
<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'string', Rule::exists('pgsql.training.course_categories', 'id')],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

> If the `pgsql.training.course_categories` connection-qualified `exists` rule errors in this environment, use a closure rule instead: `fn ($attr, $value, $fail) => \App\Models\Training\Category::whereKey($value)->exists() ?: $fail('Kategori tidak valid.')`. Verify in Step 8.

- [ ] **Step 2: Create `app/Http/Controllers/Admin/Training/CourseController.php`**

```php
<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\CourseRequest;
use App\Models\Training\Category;
use App\Models\Training\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::with('category')
            ->withCount('modules')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.training.courses.index', compact('courses'));
    }

    public function create()
    {
        return view('admin.training.courses.create', [
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(CourseRequest $request)
    {
        $data = $this->payload($request);
        $data['created_by'] = Auth::id();

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail_path'] = $request->file('thumbnail')->store('training/thumbnails', 'public');
        }

        $course = Course::create($data);

        return redirect()->route('training.courses.content', $course->id)
            ->with('success', 'Course dibuat. Sekarang tambahkan modul & materi.');
    }

    public function edit(string $id)
    {
        $course = Course::findOrFail($id);

        return view('admin.training.courses.edit', [
            'course' => $course,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function update(CourseRequest $request, string $id)
    {
        $course = Course::findOrFail($id);
        $data = $this->payload($request);
        $data['updated_by'] = Auth::id();

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail_path) {
                Storage::disk('public')->delete($course->thumbnail_path);
            }
            $data['thumbnail_path'] = $request->file('thumbnail')->store('training/thumbnails', 'public');
        }

        $course->update($data);

        return redirect()->route('training.courses.index')->with('success', 'Course diperbarui.');
    }

    public function destroy(string $id)
    {
        $course = Course::findOrFail($id);
        $course->deleted_by = Auth::id();
        $course->save();
        $course->delete();

        return redirect()->route('training.courses.index')->with('success', 'Course dihapus.');
    }

    public function publish(Request $request, string $id)
    {
        $course = Course::findOrFail($id);
        if ($course->status === 'published') {
            $course->update(['status' => 'draft', 'updated_by' => Auth::id()]);
            $msg = 'Course dikembalikan ke draft.';
        } else {
            $course->update([
                'status' => 'published',
                'published_at' => $course->published_at ?: now(),
                'updated_by' => Auth::id(),
            ]);
            $msg = 'Course dipublikasikan.';
        }

        return redirect()->route('training.courses.index')->with('success', $msg);
    }

    private function payload(CourseRequest $request): array
    {
        return [
            'title' => $request->string('title'),
            'category_id' => $request->input('category_id'),
            'description' => $request->input('description'),
            'status' => $request->input('status'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'published_at' => $request->input('status') === 'published' ? now() : null,
        ];
    }
}
```

- [ ] **Step 3: Append course routes to `routes/training.php`** (inside the `Route::prefix('training')->name('training.')` group, after the categories group)

```php
        Route::prefix('courses')->name('courses.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\Training\CourseController::class, 'index'])->name('index')->middleware('permission:Training Academy,is_read');
            Route::get('/create', [\App\Http\Controllers\Admin\Training\CourseController::class, 'create'])->name('create')->middleware('permission:Training Academy,is_create');
            Route::post('/', [\App\Http\Controllers\Admin\Training\CourseController::class, 'store'])->name('store')->middleware('permission:Training Academy,is_create');
            Route::get('/{id}/edit', [\App\Http\Controllers\Admin\Training\CourseController::class, 'edit'])->name('edit')->middleware('permission:Training Academy,is_update');
            Route::put('/{id}', [\App\Http\Controllers\Admin\Training\CourseController::class, 'update'])->name('update')->middleware('permission:Training Academy,is_update');
            Route::delete('/{id}', [\App\Http\Controllers\Admin\Training\CourseController::class, 'destroy'])->name('destroy')->middleware('permission:Training Academy,is_delete');
            Route::post('/{id}/publish', [\App\Http\Controllers\Admin\Training\CourseController::class, 'publish'])->name('publish')->middleware('permission:Training Academy,is_update');
        });
```

- [ ] **Step 4: Create `resources/views/admin/training/courses/_form.blade.php`**

```blade
@php($cat = old('category_id', $course->category_id ?? ''))
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Judul Course <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $course->title ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Kategori <span class="text-danger">*</span></label>
        <select name="category_id" class="form-select" required>
            <option value="">— pilih —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected($cat === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="3">{{ old('description', $course->description ?? '') }}</textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label">Thumbnail</label>
        <input type="file" name="thumbnail" class="form-control" accept="image/*">
        @if (!empty($course?->thumbnail_url))
            <img src="{{ $course->thumbnail_url }}" alt="thumb" class="mt-2 rounded" style="max-height:80px">
        @endif
    </div>
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="draft" @selected(old('status', $course->status ?? 'draft') === 'draft')>Draft</option>
            <option value="published" @selected(old('status', $course->status ?? '') === 'published')>Published</option>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Urutan</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $course->sort_order ?? 0) }}" min="0">
    </div>
</div>
```

- [ ] **Step 5: Create `resources/views/admin/training/courses/create.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Buat Course | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => 'Buat Course', 'active' => true],
        ]" />
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif
        <form method="POST" action="{{ route('training.courses.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="card mb-4"><div class="card-header"><h5 class="card-title mb-0">Detail Course</h5></div>
                <div class="card-body">@include('admin.training.courses._form', ['course' => null])</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan & Lanjut Isi</button>
            <a href="{{ route('training.courses.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Create `resources/views/admin/training/courses/edit.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Edit Course | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => 'Edit Course', 'active' => true],
        ]" />
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif
        <form method="POST" action="{{ route('training.courses.update', $course->id) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="card mb-4"><div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Detail Course</h5>
                <a href="{{ route('training.courses.content', $course->id) }}" class="btn btn-sm btn-outline-primary">Kelola Isi</a>
            </div>
                <div class="card-body">@include('admin.training.courses._form', ['course' => $course])</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i> Simpan</button>
            <a href="{{ route('training.courses.index') }}" class="btn btn-outline-secondary">Batal</a>
        </form>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Create `resources/views/admin/training/courses/index.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Training Academy | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Courses</h5>
                <div>
                    <a href="{{ route('training.categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="ti ti-tags me-1"></i>Kategori</a>
                    <a href="{{ route('training.reports.index') }}" class="btn btn-outline-secondary btn-sm"><i class="ti ti-chart-bar me-1"></i>Laporan</a>
                    <a href="{{ route('training.courses.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i>Buat Course</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Judul</th><th>Kategori</th><th>Modul</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($courses as $course)
                            <tr>
                                <td>{{ $course->title }}</td>
                                <td>{{ $course->category?->name ?? '-' }}</td>
                                <td>{{ $course->modules_count }}</td>
                                <td><span class="badge bg-label-{{ $course->status === 'published' ? 'success' : 'secondary' }}">{{ ucfirst($course->status) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('training.courses.content', $course->id) }}" class="btn btn-sm btn-outline-primary">Isi</a>
                                    <a href="{{ route('training.courses.edit', $course->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <form action="{{ route('training.courses.publish', $course->id) }}" method="POST" class="d-inline">@csrf
                                        <button class="btn btn-sm btn-outline-{{ $course->status === 'published' ? 'warning' : 'success' }}">{{ $course->status === 'published' ? 'Unpublish' : 'Publish' }}</button>
                                    </form>
                                    <form action="{{ route('training.courses.destroy', $course->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus course ini?')">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada course.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $courses->links() }}</div>
        </div>
    </div>
</x-app-layout>
```

> The index links to `training.courses.content` (Task 6) and `training.reports.index` (Task 10). Those route names must exist before the index renders. When executing tasks in order, implement Task 6 and Task 10 before browser-verifying the index, OR temporarily comment the two links; the final browser check happens after Task 10. Verify links in Step 9 after Task 10, or verify create/edit/list now and defer the two cross-task links.

- [ ] **Step 8: Lint**

Run: `$PHP -l app/Http/Requests/Training/CourseRequest.php && $PHP -l app/Http/Controllers/Admin/Training/CourseController.php && $PHP -l routes/training.php`
Expected: `No syntax errors detected` three times.

- [ ] **Step 9: Verify + browser**

Run: `$PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan route:list --name=training.courses`
Expected: lists index/create/store/edit/update/destroy/publish.

Browser (as Super Admin): navigate `http://localhost:8000/training/courses/create`, fill title + category (create one first via Task 4 page), upload a jpg thumbnail, submit → redirected to content page (Task 6). Confirm the course appears in `/training/courses`, publish toggles the badge. Check `read_console_messages` for errors.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Requests/Training/CourseRequest.php app/Http/Controllers/Admin/Training/CourseController.php routes/training.php resources/views/admin/training/courses/
git commit -m "feat(training): course CRUD with thumbnail and publish toggle"
```

---

### Task 6: Content builder (modules + materials)

**Files:**
- Create: `app/Http/Requests/Training/ModuleRequest.php`
- Create: `app/Http/Requests/Training/MaterialRequest.php`
- Create: `app/Http/Controllers/Admin/Training/CourseContentController.php`
- Modify: `routes/training.php` (append content + modules + materials routes)
- Create: `resources/views/admin/training/courses/content.blade.php`

**Interfaces:**
- Consumes: `Course`, `CourseModule`, `CourseMaterial` (Task 2); `YouTube::embedId` (Task 4).
- Produces: route names `training.courses.content`, `training.modules.store|update|destroy`, `training.materials.store|update|destroy`.

- [ ] **Step 1: Create `app/Http/Requests/Training/ModuleRequest.php`**

```php
<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class ModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

- [ ] **Step 2: Create `app/Http/Requests/Training/MaterialRequest.php`**

```php
<?php

namespace App\Http\Requests\Training;

use App\Support\YouTube;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');
        // On create a file is required for pdf/image; on update (material already has a file) it is optional.
        $fileRequired = in_array($type, ['pdf', 'image'], true) && ! $this->route('material');

        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(['pdf', 'image', 'youtube'])],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', $this->fileMimeRule($type)],
            'youtube_url' => [$type === 'youtube' ? 'required' : 'nullable', 'string', 'max:500', $this->youtubeRule($type)],
        ];
    }

    private function fileMimeRule(?string $type): string
    {
        return match ($type) {
            'pdf' => 'mimes:pdf',
            'image' => 'mimes:jpg,jpeg,png,webp',
            default => 'prohibited',
        };
    }

    private function youtubeRule(?string $type): \Closure
    {
        return function ($attr, $value, $fail) use ($type) {
            if ($type === 'youtube' && $value && ! YouTube::embedId($value)) {
                $fail('URL YouTube tidak dikenali.');
            }
        };
    }
}
```

- [ ] **Step 3: Create `app/Http/Controllers/Admin/Training/CourseContentController.php`**

```php
<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Training\MaterialRequest;
use App\Http\Requests\Training\ModuleRequest;
use App\Models\Training\Course;
use App\Models\Training\CourseMaterial;
use App\Models\Training\CourseModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CourseContentController extends Controller
{
    public function content(string $courseId)
    {
        $course = Course::with(['modules.materials'])->findOrFail($courseId);

        return view('admin.training.courses.content', compact('course'));
    }

    public function storeModule(ModuleRequest $request, string $courseId)
    {
        $course = Course::findOrFail($courseId);
        CourseModule::create([
            'course_id' => $course->id,
            'company_id' => $course->company_id,
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', 'Modul ditambahkan.');
    }

    public function updateModule(ModuleRequest $request, string $courseId, string $moduleId)
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);
        $module->update([
            'title' => $request->string('title'),
            'description' => $request->input('description'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Modul diperbarui.');
    }

    public function destroyModule(string $courseId, string $moduleId)
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);
        $module->deleted_by = Auth::id();
        $module->save();
        $module->delete();

        return back()->with('success', 'Modul dihapus.');
    }

    public function storeMaterial(MaterialRequest $request, string $courseId, string $moduleId)
    {
        $module = CourseModule::where('course_id', $courseId)->findOrFail($moduleId);
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
        $data = $this->materialPayload($request);
        $data['updated_by'] = Auth::id();

        if ($request->hasFile('file')) {
            if ($material->file_path) {
                Storage::disk('public')->delete($material->file_path);
            }
            $data['file_path'] = $request->file('file')->store('training/materials', 'public');
        }

        // Clear the field that does not belong to the chosen type.
        if ($data['type'] === 'youtube') {
            $data['file_path'] = null;
        } else {
            $data['youtube_url'] = null;
        }

        $material->update($data);

        return back()->with('success', 'Materi diperbarui.');
    }

    public function destroyMaterial(string $courseId, string $moduleId, string $materialId)
    {
        $material = CourseMaterial::where('module_id', $moduleId)->findOrFail($materialId);
        $material->deleted_by = Auth::id();
        $material->save();
        $material->delete();

        return back()->with('success', 'Materi dihapus.');
    }

    private function materialPayload(MaterialRequest $request): array
    {
        return [
            'title' => $request->string('title'),
            'type' => $request->input('type'),
            'youtube_url' => $request->input('type') === 'youtube' ? $request->input('youtube_url') : null,
            'estimated_minutes' => $request->filled('estimated_minutes') ? (int) $request->input('estimated_minutes') : null,
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
```

- [ ] **Step 4: Append content routes to `routes/training.php`** (inside the `training.` group, after the `courses` group — note these are siblings of `courses`, using the `Ctl` alias below)

Add this at the top `use` block of `routes/training.php`:

```php
use App\Http\Controllers\Admin\Training\CourseContentController;
```

Then add inside the `Route::prefix('training')->name('training.')->group(...)`:

```php
        Route::get('/courses/{course}/content', [CourseContentController::class, 'content'])->name('courses.content')->middleware('permission:Training Academy,is_read');

        Route::prefix('courses/{course}/modules')->name('modules.')->group(function () {
            Route::post('/', [CourseContentController::class, 'storeModule'])->name('store')->middleware('permission:Training Academy,is_create');
            Route::put('/{module}', [CourseContentController::class, 'updateModule'])->name('update')->middleware('permission:Training Academy,is_update');
            Route::delete('/{module}', [CourseContentController::class, 'destroyModule'])->name('destroy')->middleware('permission:Training Academy,is_delete');

            Route::post('/{module}/materials', [CourseContentController::class, 'storeMaterial'])->name('materials.store')->middleware('permission:Training Academy,is_create');
            Route::put('/{module}/materials/{material}', [CourseContentController::class, 'updateMaterial'])->name('materials.update')->middleware('permission:Training Academy,is_update');
            Route::delete('/{module}/materials/{material}', [CourseContentController::class, 'destroyMaterial'])->name('materials.destroy')->middleware('permission:Training Academy,is_delete');
        });
```

> Route names produced: `training.courses.content`, `training.modules.store|update|destroy`, `training.modules.materials.store|materials.update|materials.destroy`. Use these exact names in the view.

- [ ] **Step 5: Create `resources/views/admin/training/courses/content.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Kelola Isi Course | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => $course->title, 'url' => route('training.courses.edit', $course->id)],
            ['label' => 'Kelola Isi', 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif
        @if (session('error'))<x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>@endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
        @endif

        <div class="card mb-4"><div class="card-body d-flex justify-content-between align-items-center">
            <div><h5 class="mb-1">{{ $course->title }}</h5>
                <span class="badge bg-label-{{ $course->status === 'published' ? 'success' : 'secondary' }}">{{ ucfirst($course->status) }}</span></div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#moduleModal" onclick="fillModule({})"><i class="ti ti-plus me-1"></i>Tambah Modul</button>
        </div></div>

        @forelse ($course->modules as $module)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><span class="text-muted small">#{{ $module->sort_order }}</span> <strong>{{ $module->title }}</strong></div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#materialModal"
                            onclick='fillMaterial({ module_id: "{{ $module->id }}" })'>+ Materi</button>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#moduleModal"
                            onclick='fillModule(@json($module))'>Edit</button>
                        <form action="{{ route('training.modules.destroy', [$course->id, $module->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus modul beserta materinya?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Hapus</button></form>
                    </div>
                </div>
                <div class="table-responsive"><table class="table mb-0">
                    <thead><tr><th>#</th><th>Materi</th><th>Tipe</th><th>Estimasi</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($module->materials as $mat)
                            <tr>
                                <td>{{ $mat->sort_order }}</td>
                                <td>{{ $mat->title }}</td>
                                <td><span class="badge bg-label-info">{{ $mat->type }}</span></td>
                                <td>{{ $mat->estimated_minutes ? $mat->estimated_minutes.' mnt' : '—' }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#materialModal"
                                        onclick='fillMaterial(@json($mat))'>Edit</button>
                                    <form action="{{ route('training.modules.materials.destroy', [$course->id, $module->id, $mat->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus materi ini?')">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Hapus</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada materi.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        @empty
            <div class="alert alert-info">Belum ada modul. Klik "Tambah Modul" untuk mulai.</div>
        @endforelse
    </div>

    {{-- Module modal --}}
    <div class="modal fade" id="moduleModal" tabindex="-1"><div class="modal-dialog">
        <form class="modal-content" id="moduleForm" method="POST">@csrf
            <input type="hidden" name="_method" id="moduleMethod" value="POST">
            <div class="modal-header"><h5 class="modal-title" id="moduleTitle">Tambah Modul</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Judul Modul <span class="text-danger">*</span></label><input type="text" name="title" id="moduleTitleInput" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Deskripsi</label><textarea name="description" id="moduleDesc" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" id="moduleSort" class="form-control" value="0" min="0"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
        </form>
    </div></div>

    {{-- Material modal --}}
    <div class="modal fade" id="materialModal" tabindex="-1"><div class="modal-dialog">
        <form class="modal-content" id="materialForm" method="POST" enctype="multipart/form-data">@csrf
            <input type="hidden" name="_method" id="materialMethod" value="POST">
            <input type="hidden" name="module_id_hidden" id="materialModuleId">
            <div class="modal-header"><h5 class="modal-title" id="materialModalTitle">Tambah Materi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Judul <span class="text-danger">*</span></label><input type="text" name="title" id="matTitle" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Tipe <span class="text-danger">*</span></label>
                    <select name="type" id="matType" class="form-select" onchange="toggleMatFields()">
                        <option value="pdf">PDF</option><option value="image">Gambar</option><option value="youtube">YouTube</option>
                    </select></div>
                <div class="mb-3" id="matFileWrap"><label class="form-label">File</label>
                    <input type="file" name="file" id="matFile" class="form-control">
                    <small class="text-muted" id="matFileHint"></small></div>
                <div class="mb-3 d-none" id="matYoutubeWrap"><label class="form-label">URL YouTube</label>
                    <input type="text" name="youtube_url" id="matYoutube" class="form-control" placeholder="https://youtu.be/..."></div>
                <div class="mb-3"><label class="form-label">Estimasi menit <span class="text-muted small">(opsional)</span></label>
                    <input type="number" name="estimated_minutes" id="matMinutes" class="form-control" min="0" placeholder="mis. 5"></div>
                <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" id="matSort" class="form-control" value="0" min="0"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan</button></div>
        </form>
    </div></div>

    @push('page-js')
    <script>
        const courseId = "{{ $course->id }}";
        const modulesBase = "{{ url('training/courses/'.$course->id.'/modules') }}";

        function fillModule(m) {
            const isEdit = !!m.id;
            document.getElementById('moduleTitle').textContent = isEdit ? 'Edit Modul' : 'Tambah Modul';
            document.getElementById('moduleForm').action = isEdit ? (modulesBase + '/' + m.id) : modulesBase;
            document.getElementById('moduleMethod').value = isEdit ? 'PUT' : 'POST';
            document.getElementById('moduleTitleInput').value = m.title || '';
            document.getElementById('moduleDesc').value = m.description || '';
            document.getElementById('moduleSort').value = m.sort_order ?? 0;
        }

        function toggleMatFields() {
            const t = document.getElementById('matType').value;
            document.getElementById('matFileWrap').classList.toggle('d-none', t === 'youtube');
            document.getElementById('matYoutubeWrap').classList.toggle('d-none', t !== 'youtube');
            document.getElementById('matFileHint').textContent = t === 'pdf' ? 'Format .pdf' : (t === 'image' ? 'Format .jpg/.png/.webp' : '');
        }

        function fillMaterial(mat) {
            const isEdit = !!mat.id;
            const moduleId = mat.module_id;
            document.getElementById('materialModalTitle').textContent = isEdit ? 'Edit Materi' : 'Tambah Materi';
            document.getElementById('materialForm').action = isEdit
                ? (modulesBase + '/' + moduleId + '/materials/' + mat.id)
                : (modulesBase + '/' + moduleId + '/materials');
            document.getElementById('materialMethod').value = isEdit ? 'PUT' : 'POST';
            document.getElementById('materialModuleId').value = moduleId || '';
            document.getElementById('matTitle').value = mat.title || '';
            document.getElementById('matType').value = mat.type || 'pdf';
            document.getElementById('matYoutube').value = mat.youtube_url || '';
            document.getElementById('matMinutes').value = mat.estimated_minutes ?? '';
            document.getElementById('matSort').value = mat.sort_order ?? 0;
            document.getElementById('matFile').value = '';
            toggleMatFields();
        }
    </script>
    @endpush
</x-app-layout>
```

> The material form posts to a URL that embeds the module id (built in `fillMaterial`); the `module_id_hidden` field is not read server-side (module comes from the route), it only aids debugging. File input for `youtube` type is hidden and left empty, so `MaterialRequest`'s `prohibited` mime rule never triggers on a real submit.

- [ ] **Step 6: Lint**

Run: `$PHP -l app/Http/Requests/Training/ModuleRequest.php && $PHP -l app/Http/Requests/Training/MaterialRequest.php && $PHP -l app/Http/Controllers/Admin/Training/CourseContentController.php && $PHP -l routes/training.php`
Expected: `No syntax errors detected` four times.

- [ ] **Step 7: Verify + browser**

Run: `$PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan route:list --name=training.modules`
Expected: lists module + material routes.

Browser (as Super Admin): open a course's content page. Add a module. Add a PDF material (upload a .pdf, estimasi 5), an image material, and a YouTube material (`https://youtu.be/dQw4w9WgXcQ`). Confirm each row appears with correct type + estimasi ("—" when blank). Edit and delete one of each. Check `read_console_messages`/`preview_logs` for errors, and confirm files land under `storage/app/public/training/materials`.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/Training/ModuleRequest.php app/Http/Requests/Training/MaterialRequest.php app/Http/Controllers/Admin/Training/CourseContentController.php routes/training.php resources/views/admin/training/courses/content.blade.php
git commit -m "feat(training): content builder for modules and materials"
```

---

### Task 7: ProgressService (progress math)

**Files:**
- Create: `app/Services/Training/ProgressService.php`

**Interfaces:**
- Consumes: `Course` with `modules.materials` loaded; `MaterialProgress` (Task 2).
- Produces:
  - `completedMaterialIds(string $userId): array` — material ids the user has completed.
  - `courseProgress(Course $course, array $completedIds): array` — keys `total_materials, completed_count, percent, modules_total, modules_completed, minutes_done, minutes_remaining, has_minutes`.
  - `dashboardStats(iterable $courses, array $completedIds): array` — keys `modules_total, modules_completed, minutes_done, has_minutes`.

- [ ] **Step 1: Create `app/Services/Training/ProgressService.php`**

```php
<?php

namespace App\Services\Training;

use App\Models\Training\Course;
use App\Models\Training\MaterialProgress;

class ProgressService
{
    /**
     * Material ids the given user has completed (completed_at set).
     *
     * @return array<int, string>
     */
    public function completedMaterialIds(string $userId): array
    {
        return MaterialProgress::query()
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->pluck('material_id')
            ->all();
    }

    /**
     * Progress for a single course. $course must have modules.materials loaded
     * (soft-deleted rows are already excluded by the models' SoftDeletes scope).
     *
     * @param  array<int, string>  $completedIds
     * @return array<string, mixed>
     */
    public function courseProgress(Course $course, array $completedIds): array
    {
        $completed = array_flip($completedIds);

        $totalMaterials = 0;
        $completedCount = 0;
        $modulesTotal = 0;
        $modulesCompleted = 0;
        $minutesDone = 0;
        $minutesRemaining = 0;
        $hasMinutes = false;

        foreach ($course->modules as $module) {
            $materials = $module->materials;
            if ($materials->isEmpty()) {
                continue; // empty modules do not count toward module totals
            }
            $modulesTotal++;
            $allDone = true;

            foreach ($materials as $material) {
                $totalMaterials++;
                $isDone = isset($completed[$material->id]);
                if ($isDone) {
                    $completedCount++;
                } else {
                    $allDone = false;
                }

                if ($material->estimated_minutes !== null) {
                    $hasMinutes = true;
                    if ($isDone) {
                        $minutesDone += $material->estimated_minutes;
                    } else {
                        $minutesRemaining += $material->estimated_minutes;
                    }
                }
            }

            if ($allDone) {
                $modulesCompleted++;
            }
        }

        $percent = $totalMaterials > 0
            ? (int) round($completedCount / $totalMaterials * 100)
            : 0;

        return [
            'total_materials' => $totalMaterials,
            'completed_count' => $completedCount,
            'percent' => $percent,
            'modules_total' => $modulesTotal,
            'modules_completed' => $modulesCompleted,
            'minutes_done' => $minutesDone,
            'minutes_remaining' => $minutesRemaining,
            'has_minutes' => $hasMinutes,
        ];
    }

    /**
     * Aggregate header stats across a set of (loaded) courses.
     *
     * @param  iterable<int, Course>  $courses
     * @param  array<int, string>  $completedIds
     * @return array<string, mixed>
     */
    public function dashboardStats(iterable $courses, array $completedIds): array
    {
        $modulesTotal = 0;
        $modulesCompleted = 0;
        $minutesDone = 0;
        $hasMinutes = false;

        foreach ($courses as $course) {
            $p = $this->courseProgress($course, $completedIds);
            $modulesTotal += $p['modules_total'];
            $modulesCompleted += $p['modules_completed'];
            $minutesDone += $p['minutes_done'];
            $hasMinutes = $hasMinutes || $p['has_minutes'];
        }

        return [
            'modules_total' => $modulesTotal,
            'modules_completed' => $modulesCompleted,
            'minutes_done' => $minutesDone,
            'has_minutes' => $hasMinutes,
        ];
    }
}
```

- [ ] **Step 2: Lint**

Run: `$PHP -l app/Services/Training/ProgressService.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verify with tinker (uses a real published course if one exists)**

Run:
```
$PHP artisan tinker --execute="
\$svc = new App\Services\Training\ProgressService();
\$course = App\Models\Training\Course::with('modules.materials')->first();
if (!\$course) { echo 'no course yet — create one in Task 5/6 first'; }
else { print_r(\$svc->courseProgress(\$course, [])); }
"
```
Expected: an array with `percent => 0`, `modules_total`/`total_materials` reflecting the course's content, and `minutes_*` numbers (0 if no estimasi). No errors.

- [ ] **Step 4: Commit**

```bash
git add app/Services/Training/ProgressService.php
git commit -m "feat(training): progress calculation service"
```

---

### Task 8: Learner dashboard (`/academy`)

**Files:**
- Create: `app/Http/Controllers/Academy/AcademyController.php`
- Modify: `routes/training.php` (append the `academy.` learner group)
- Create: `resources/views/academy/dashboard.blade.php`

**Interfaces:**
- Consumes: `Course::published()` with `modules.materials` + `category`; `ProgressService` (Task 7); `CourseAccess` (Task 2).
- Produces: route name `academy.dashboard`; `AcademyController` (extended in Task 9).

- [ ] **Step 1: Create `app/Http/Controllers/Academy/AcademyController.php`**

```php
<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\Training\Course;
use App\Models\Training\CourseAccess;
use App\Services\Training\ProgressService;
use Illuminate\Support\Facades\Auth;

class AcademyController extends Controller
{
    public function __construct(private readonly ProgressService $progress)
    {
    }

    public function dashboard()
    {
        $userId = Auth::id();
        $completedIds = $this->progress->completedMaterialIds($userId);

        $courses = Course::published()
            ->with(['category', 'modules.materials'])
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->get();

        $progressByCourse = [];
        foreach ($courses as $course) {
            $progressByCourse[$course->id] = $this->progress->courseProgress($course, $completedIds);
        }

        $stats = $this->progress->dashboardStats($courses, $completedIds);

        // "Sedang Dipelajari": most-recently accessed published course not yet 100%.
        $continue = null;
        $accesses = CourseAccess::where('user_id', $userId)
            ->orderByDesc('last_accessed_at')
            ->get();
        foreach ($accesses as $access) {
            $course = $courses->firstWhere('id', $access->course_id);
            if ($course && ($progressByCourse[$course->id]['percent'] ?? 0) < 100) {
                $continue = ['course' => $course, 'access' => $access, 'progress' => $progressByCourse[$course->id]];
                break;
            }
        }

        return view('academy.dashboard', compact('courses', 'progressByCourse', 'stats', 'continue'));
    }
}
```

- [ ] **Step 2: Append the learner route group to `routes/training.php`** (a sibling of the `training.` group, still inside the `auth,verified` middleware group)

Add at the top `use` block:

```php
use App\Http\Controllers\Academy\AcademyController;
```

Then add (after the `Route::prefix('training')...` group, still inside `Route::middleware(['auth','verified'])->group(...)`):

```php
    // === Learner (Agent) ===
    Route::prefix('academy')->name('academy.')->group(function () {
        Route::get('/', [AcademyController::class, 'dashboard'])->name('dashboard')->middleware('permission:Academy,is_read');
    });
```

- [ ] **Step 3: Create `resources/views/academy/dashboard.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Training Academy | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Academy', 'active' => true],
        ]" />

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h4 class="fw-bold mb-1">Training Academy</h4><p class="text-muted mb-0">Tingkatkan kemampuan jualan Anda</p></div>
        </div>

        {{-- Header stats --}}
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                    <div><small class="text-muted">Modul Selesai</small>
                        <h3 class="mb-2">{{ $stats['modules_completed'] }} / {{ $stats['modules_total'] }}</h3>
                        <div class="progress" style="height:6px"><div class="progress-bar" role="progressbar"
                            style="width: {{ $stats['modules_total'] ? round($stats['modules_completed'] / $stats['modules_total'] * 100) : 0 }}%"></div></div>
                    </div>
                    <span class="badge bg-label-success rounded p-2"><i class="ti ti-school ti-md"></i></span>
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card h-100"><div class="card-body d-flex justify-content-between align-items-center">
                    <div><small class="text-muted">Jam Belajar</small>
                        <h3 class="mb-0">@if($stats['has_minutes']){{ number_format($stats['minutes_done'] / 60, 1) }} jam @else — @endif</h3></div>
                    <span class="badge bg-label-primary rounded p-2"><i class="ti ti-clock ti-md"></i></span>
                </div></div>
            </div>
        </div>

        {{-- Sedang Dipelajari --}}
        @if ($continue)
            @php($cp = $continue['progress'])
            <div class="card mb-4"><div class="card-body">
                <h6 class="mb-3"><i class="ti ti-player-play me-1"></i>Sedang Dipelajari</h6>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="flex-grow-1">
                        <strong>{{ $continue['course']->title }}</strong>
                        <div class="text-muted small mb-2">{{ $continue['course']->category?->name }}</div>
                        <div class="progress" style="height:8px; max-width:600px"><div class="progress-bar bg-success" style="width: {{ $cp['percent'] }}%"></div></div>
                        <small class="text-muted">{{ $cp['percent'] }}% selesai @if($cp['has_minutes']) · {{ $cp['minutes_remaining'] }} menit tersisa @endif</small>
                    </div>
                    <a href="{{ $continue['access']->last_material_id
                        ? route('academy.materials.show', $continue['access']->last_material_id)
                        : route('academy.courses.show', $continue['course']->id) }}" class="btn btn-success"><i class="ti ti-player-play me-1"></i>Lanjutkan</a>
                </div>
            </div></div>
        @endif

        {{-- Semua Kursus --}}
        <h5 class="mb-3">Semua Kursus</h5>
        <div class="row g-4">
            @forelse ($courses as $course)
                @php($p = $progressByCourse[$course->id])
                <div class="col-md-4 col-lg-3">
                    <div class="card h-100">
                        <div class="rounded-top" style="height:120px; background: {{ $course->category?->color ?: '#5C9E84' }}; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                            @if ($course->thumbnail_url)
                                <img src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}" style="width:100%; height:100%; object-fit:cover;">
                            @else
                                <i class="ti {{ $course->category?->icon ?: 'ti-book' }}" style="font-size:2.5rem; color:#fff;"></i>
                            @endif
                        </div>
                        <div class="card-body">
                            @if ($course->category)<span class="badge bg-label-secondary mb-2"><i class="ti ti-tag me-1"></i>{{ $course->category->name }}</span>@endif
                            <h6 class="mb-1">{{ $course->title }}</h6>
                            <small class="text-muted d-block mb-2">{{ $p['modules_total'] }} modul</small>
                            <div class="progress mb-2" style="height:6px"><div class="progress-bar bg-success" style="width: {{ $p['percent'] }}%"></div></div>
                            <small class="text-muted">{{ $p['percent'] }}% selesai</small>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('academy.courses.show', $course->id) }}" class="btn btn-sm w-100 btn-{{ $p['percent'] === 0 ? 'primary' : ($p['percent'] >= 100 ? 'outline-secondary' : 'success') }}">
                                {{ $p['percent'] === 0 ? 'Mulai Kursus' : ($p['percent'] >= 100 ? 'Ulangi' : 'Lanjutkan') }}
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="alert alert-info">Belum ada kursus yang tersedia.</div></div>
            @endforelse
        </div>
    </div>
</x-app-layout>
```

> This view references `academy.courses.show` and `academy.materials.show` (Task 9). Implement Task 9 before browser-verifying the dashboard's links, or verify the stats/cards render first and follow the links after Task 9.

- [ ] **Step 4: Lint + verify**

Run: `$PHP -l app/Http/Controllers/Academy/AcademyController.php && $PHP -l routes/training.php && $PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan route:list --name=academy.dashboard`
Expected: no syntax errors; route `academy.dashboard` listed.

- [ ] **Step 5: Browser**

As Super Admin, navigate `http://localhost:8000/academy`. Confirm header stats render (0/0 and "—" for hours when no estimasi/data), published courses appear as cards with progress bars and correct button labels (Mulai when 0%). No console errors.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Academy/AcademyController.php routes/training.php resources/views/academy/dashboard.blade.php
git commit -m "feat(training): learner academy dashboard"
```

---

### Task 9: Course detail, material viewer, view-logging & mark-complete

**Files:**
- Modify: `app/Http/Controllers/Academy/AcademyController.php` (add `course`, `material`, `complete`)
- Modify: `routes/training.php` (append learner course/material routes)
- Create: `resources/views/academy/course.blade.php`
- Create: `resources/views/academy/material.blade.php`

**Interfaces:**
- Consumes: `AcademyController` (Task 8), `ProgressService`, `MaterialProgress`, `CourseAccess`, `CourseMaterial`.
- Produces: route names `academy.courses.show`, `academy.materials.show`, `academy.materials.complete`.

- [ ] **Step 1: Add methods to `app/Http/Controllers/Academy/AcademyController.php`**

Add these imports at the top (next to existing `use` lines):

```php
use App\Models\Training\CourseMaterial;
use App\Models\Training\MaterialProgress;
```

Add these methods to the class:

```php
    public function course(string $courseId)
    {
        $userId = Auth::id();
        $course = Course::published()->with(['category', 'modules.materials'])->findOrFail($courseId);

        $completedIds = array_flip($this->progress->completedMaterialIds($userId));
        $progress = $this->progress->courseProgress($course, array_keys($completedIds));

        return view('academy.course', compact('course', 'progress', 'completedIds'));
    }

    public function material(string $materialId)
    {
        $userId = Auth::id();
        $material = CourseMaterial::with('module.course')->findOrFail($materialId);
        $course = $material->module?->course;

        abort_if(! $course || ! $course->isPublished(), 404);

        // Log "viewed" (idempotent — keep first viewed_at, refresh nothing else).
        MaterialProgress::firstOrCreate(
            ['user_id' => $userId, 'material_id' => $material->id],
            ['viewed_at' => now()]
        );

        // Upsert course access (resume pointer + last accessed).
        $access = CourseAccess::firstOrNew(['user_id' => $userId, 'course_id' => $course->id]);
        $access->first_opened_at = $access->first_opened_at ?: now();
        $access->last_accessed_at = now();
        $access->last_material_id = $material->id;
        $access->save();

        // Ordered material list across the course for prev/next.
        $ordered = $course->loadMissing('modules.materials')->modules
            ->flatMap(fn ($m) => $m->materials)
            ->values();
        $idx = $ordered->search(fn ($m) => $m->id === $material->id);
        $prev = $idx > 0 ? $ordered[$idx - 1] : null;
        $next = ($idx !== false && $idx < $ordered->count() - 1) ? $ordered[$idx + 1] : null;

        $isCompleted = MaterialProgress::where('user_id', $userId)
            ->where('material_id', $material->id)
            ->whereNotNull('completed_at')
            ->exists();

        return view('academy.material', compact('material', 'course', 'prev', 'next', 'isCompleted'));
    }

    public function complete(string $materialId)
    {
        $userId = Auth::id();
        $material = CourseMaterial::with('module.course')->findOrFail($materialId);
        $course = $material->module?->course;
        abort_if(! $course || ! $course->isPublished(), 404);

        $progress = MaterialProgress::firstOrNew(['user_id' => $userId, 'material_id' => $material->id]);
        $progress->viewed_at = $progress->viewed_at ?: now();
        $progress->completed_at = now();
        $progress->save();

        return redirect()->route('academy.materials.show', $material->id)->with('success', 'Materi ditandai selesai.');
    }
```

- [ ] **Step 2: Append learner course/material routes to `routes/training.php`** (inside the `academy.` group)

```php
        Route::get('/courses/{course}', [AcademyController::class, 'course'])->name('courses.show')->middleware('permission:Academy,is_read');
        Route::get('/materials/{material}', [AcademyController::class, 'material'])->name('materials.show')->middleware('permission:Academy,is_read');
        Route::post('/materials/{material}/complete', [AcademyController::class, 'complete'])->name('materials.complete')->middleware('permission:Academy,is_read');
```

- [ ] **Step 3: Create `resources/views/academy/course.blade.php`**

```blade
<x-app-layout>
    @section('title', $course->title . ' | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Academy', 'url' => route('academy.dashboard')],
            ['label' => $course->title, 'active' => true],
        ]" />

        <div class="card mb-4"><div class="card-body">
            @if ($course->category)<span class="badge bg-label-secondary mb-2">{{ $course->category->name }}</span>@endif
            <h4 class="mb-1">{{ $course->title }}</h4>
            @if ($course->description)<p class="text-muted">{{ $course->description }}</p>@endif
            <div class="progress mb-2" style="height:8px; max-width:600px"><div class="progress-bar bg-success" style="width: {{ $progress['percent'] }}%"></div></div>
            <small class="text-muted">{{ $progress['completed_count'] }}/{{ $progress['total_materials'] }} materi · {{ $progress['percent'] }}% selesai
                @if($progress['has_minutes']) · {{ $progress['minutes_remaining'] }} menit tersisa @endif</small>
        </div></div>

        @forelse ($course->modules as $module)
            <div class="card mb-3">
                <div class="card-header"><strong>{{ $module->title }}</strong>
                    @if($module->description)<div class="text-muted small">{{ $module->description }}</div>@endif</div>
                <div class="list-group list-group-flush">
                    @forelse ($module->materials as $mat)
                        @php($done = isset($completedIds[$mat->id]))
                        <a href="{{ route('academy.materials.show', $mat->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span>
                                <i class="ti {{ $mat->type === 'youtube' ? 'ti-brand-youtube' : ($mat->type === 'pdf' ? 'ti-file-type-pdf' : 'ti-photo') }} me-2"></i>
                                {{ $mat->title }}
                                @if($mat->estimated_minutes)<span class="text-muted small ms-2">{{ $mat->estimated_minutes }} mnt</span>@endif
                            </span>
                            @if ($done)<span class="badge bg-label-success"><i class="ti ti-check"></i> Selesai</span>
                            @else<i class="ti ti-chevron-right text-muted"></i>@endif
                        </a>
                    @empty
                        <div class="list-group-item text-muted">Belum ada materi.</div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="alert alert-info">Course ini belum memiliki modul.</div>
        @endforelse
    </div>
</x-app-layout>
```

- [ ] **Step 4: Create `resources/views/academy/material.blade.php`**

```blade
<x-app-layout>
    @section('title', $material->title . ' | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Academy', 'url' => route('academy.dashboard')],
            ['label' => $course->title, 'url' => route('academy.courses.show', $course->id)],
            ['label' => $material->title, 'active' => true],
        ]" />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">{{ $material->title }}</h5>
                @if ($isCompleted)
                    <span class="badge bg-label-success"><i class="ti ti-check me-1"></i>Selesai</span>
                @endif
            </div>
            <div class="card-body">
                @if ($material->type === 'youtube' && $material->youtube_embed_id)
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/{{ $material->youtube_embed_id }}" title="{{ $material->title }}" allowfullscreen></iframe>
                    </div>
                @elseif ($material->type === 'image' && $material->file_url)
                    <img src="{{ $material->file_url }}" alt="{{ $material->title }}" class="img-fluid rounded">
                @elseif ($material->type === 'pdf' && $material->file_url)
                    <div class="ratio" style="--bs-aspect-ratio: 130%">
                        <iframe src="{{ $material->file_url }}" title="{{ $material->title }}"></iframe>
                    </div>
                    <a href="{{ $material->file_url }}" target="_blank" class="btn btn-sm btn-outline-secondary mt-2"><i class="ti ti-download me-1"></i>Buka PDF</a>
                @else
                    <div class="alert alert-warning mb-0">Materi tidak dapat ditampilkan.</div>
                @endif
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <div>
                @if ($prev)<a href="{{ route('academy.materials.show', $prev->id) }}" class="btn btn-outline-secondary"><i class="ti ti-chevron-left me-1"></i>Sebelumnya</a>@endif
            </div>
            <div class="d-flex gap-2">
                @unless ($isCompleted)
                    <form method="POST" action="{{ route('academy.materials.complete', $material->id) }}">@csrf
                        <button class="btn btn-success"><i class="ti ti-check me-1"></i>Tandai selesai</button>
                    </form>
                @endunless
                @if ($next)<a href="{{ route('academy.materials.show', $next->id) }}" class="btn btn-primary">Berikutnya<i class="ti ti-chevron-right ms-1"></i></a>
                @else<a href="{{ route('academy.courses.show', $course->id) }}" class="btn btn-outline-primary">Kembali ke Course</a>@endif
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Lint + verify**

Run: `$PHP -l app/Http/Controllers/Academy/AcademyController.php && $PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan route:list --name=academy`
Expected: no syntax errors; routes `academy.dashboard/courses.show/materials.show/materials.complete` listed.

- [ ] **Step 6: Browser end-to-end**

As Super Admin (needs a published course with modules/materials from Tasks 5-6):
1. `/academy` → click a course "Mulai Kursus" → course detail lists modules/materials.
2. Click a material → viewer renders (test all three: youtube iframe, image, pdf iframe).
3. Click "Tandai selesai" → badge appears, and the material shows as Selesai in the course list.
4. Return to `/academy` → "Sedang Dipelajari" now shows this course with a "Lanjutkan" button; the course card % and "Modul Selesai" header reflect the completion.
Confirm via `read_page` the numbers changed; check `read_console_messages` for errors.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Academy/AcademyController.php routes/training.php resources/views/academy/course.blade.php resources/views/academy/material.blade.php
git commit -m "feat(training): course detail, material viewer, view-logging and completion"
```

---

### Task 10: Progress report for managers

**Files:**
- Create: `app/Http/Controllers/Admin/Training/ReportController.php`
- Modify: `routes/training.php` (append the report route)
- Create: `resources/views/admin/training/reports/index.blade.php`

**Interfaces:**
- Consumes: `Agent` (`App\Models\Partner\Agent`), `Course::published()`, `ProgressService`, `CourseAccess`.
- Produces: route name `training.reports.index`.

- [ ] **Step 1: Create `app/Http/Controllers/Admin/Training/ReportController.php`**

```php
<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Models\Partner\Agent;
use App\Models\Training\Course;
use App\Models\Training\CourseAccess;
use App\Services\Training\ProgressService;

class ReportController extends Controller
{
    public function __construct(private readonly ProgressService $progress)
    {
    }

    public function index()
    {
        $courses = Course::published()->with('modules.materials')->get();

        // Last-activity per user (single grouped query).
        $lastActivity = CourseAccess::selectRaw('user_id, MAX(last_accessed_at) as last_at')
            ->groupBy('user_id')
            ->pluck('last_at', 'user_id');

        $agents = Agent::with('user')
            ->whereNotNull('user_id')
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($agents as $agent) {
            $completedIds = $this->progress->completedMaterialIds($agent->user_id);

            $coursesCompleted = 0;
            foreach ($courses as $course) {
                $p = $this->progress->courseProgress($course, $completedIds);
                if ($p['total_materials'] > 0 && $p['percent'] >= 100) {
                    $coursesCompleted++;
                }
            }

            $rows[] = [
                'name' => $agent->name,
                'code' => $agent->code,
                'courses_completed' => $coursesCompleted,
                'materials_completed' => count($completedIds),
                'last_activity' => $lastActivity[$agent->user_id] ?? null,
            ];
        }

        return view('admin.training.reports.index', [
            'rows' => $rows,
            'coursesTotal' => $courses->count(),
        ]);
    }
}
```

> Performance note: `completedMaterialIds` runs one query per agent. For the expected agent count this is fine; if the agent list grows large, replace the loop with a single grouped `material_progress` query keyed by `user_id`. Not needed for v1.

- [ ] **Step 2: Append the report route to `routes/training.php`** (inside the `training.` group)

Add to the top `use` block:

```php
use App\Http\Controllers\Admin\Training\ReportController;
```

Add inside the `Route::prefix('training')->name('training.')->group(...)`:

```php
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:Training Academy,is_read');
```

- [ ] **Step 3: Create `resources/views/admin/training/reports/index.blade.php`**

```blade
<x-app-layout>
    @section('title', 'Laporan Training | ')
    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Training Academy', 'url' => route('training.courses.index')],
            ['label' => 'Laporan', 'active' => true],
        ]" />

        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">Progress Belajar Agent</h5>
                <small class="text-muted">{{ $coursesTotal }} course published</small></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Agent</th><th>Kode</th><th class="text-end">Course Selesai</th><th class="text-end">Materi Selesai</th><th>Aktivitas Terakhir</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td><code>{{ $row['code'] }}</code></td>
                                <td class="text-end">{{ $row['courses_completed'] }} / {{ $coursesTotal }}</td>
                                <td class="text-end">{{ $row['materials_completed'] }}</td>
                                <td>{{ $row['last_activity'] ? \Illuminate\Support\Carbon::parse($row['last_activity'])->diffForHumans() : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">Belum ada agent.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 4: Lint + verify**

Run: `$PHP -l app/Http/Controllers/Admin/Training/ReportController.php && $PHP artisan route:clear && $PHP artisan view:clear && $PHP artisan route:list --name=training.reports`
Expected: no syntax errors; `training.reports.index` listed.

- [ ] **Step 5: Browser**

As Super Admin, navigate `http://localhost:8000/training/reports`. Confirm the table lists agents with "Course Selesai X/N", "Materi Selesai", and "Aktivitas Terakhir" (— when none). No console errors.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Admin/Training/ReportController.php routes/training.php resources/views/admin/training/reports/index.blade.php
git commit -m "feat(training): manager progress report"
```

---

## Final verification (after all tasks)

- [ ] Run `$PHP artisan route:list | grep -E 'training|academy'` and confirm all expected routes exist.
- [ ] As Super Admin, walk the full flow: create category → create course → add modules/materials (pdf+image+youtube, some with estimasi) → publish → open `/academy` → study + mark complete → confirm dashboard stats + `/training/reports` update.
- [ ] (Optional, if a real Agent-role and Administrator/Marketing-role test account exist) Log in as an Agent and confirm `/academy` is reachable but `/training/courses` returns 403; log in as Administrator/Marketing and confirm the reverse.
- [ ] Confirm `git log --oneline` shows one commit per task on `feature/training-academy`.

---

## Deferred / not in this plan (per spec YAGNI)

Certification, real-time duration tracking, YouTube API duration fetch, per-agent course assignment/targeting, reseller access. Duration is captured only as an optional manual `estimated_minutes` per material.
