# Database Migration Guide

Dokumentasi cara menjalankan database migration untuk proyek ini.

## Struktur Folder Migration

```
database/migrations/
├── (root)                       # Laravel default tables
│   ├── 2026_04_23_000000_create_uuid_v7_function.php
│   ├── 2026_04_23_000000_create_failed_jobs_table.php
│   ├── 2026_04_23_000001_create_password_reset_tokens_table.php
│   ├── 2026_04_23_000002_create_personal_access_tokens_table.php
│   └── 2026_04_23_000001_alter_audit_columns_to_uuid.php
├── public/                      # Public data (wilayah, parameter)
│   ├── 2026_04_23_000001_create_provinces_table.php
│   ├── 2026_04_23_000002_create_cities_table.php
│   ├── 2026_04_23_000003_create_parameters_table.php
│   ├── 2026_04_23_000004_create_parameter_details_table.php
│   └── 2026_04_23_000005_create_stock_mutation_types_table.php
├── master_data/                 # Master data tables
│   ├── 2026_04_23_000001_create_positions_table.php
│   ├── 2026_04_23_000002_create_divisions_table.php
│   ├── 2026_04_23_000003_create_business_unit_types_table.php
│   ├── 2026_04_23_000004_create_business_units_table.php
│   ├── 2026_04_23_000005_create_business_unit_branches_table.php
│   ├── 2026_04_23_000006_create_roles_table.php
│   ├── 2026_04_23_000007_create_menus_table.php
│   ├── 2026_04_23_000008_create_suppliers_table.php
│   ├── 2026_04_23_000001_add_tax_id_to_suppliers_table.php
│   └── 2026_04_23_000002_add_tax_ppn_to_suppliers_table.php
├── human_resources/             # HR & Employee management
│   └── 2026_04_23_000001_create_employees_table.php
├── auth/                        # Authentication & Authorization
│   ├── 2026_04_23_000001_create_users_table.php
│   ├── 2026_04_23_000002_create_iam_accesses_table.php
│   └── 2026_04_23_000003_create_iam_has_accesses_table.php
├── product/                     # Unified Product Master (FG, RM, Semi-finished, Service, Bundle)
│   ├── 2026_04_23_000001_create_product_schema_and_natures.php
│   ├── 2026_04_23_000002_create_products_table.php
│   ├── 2026_04_23_000003_create_product_unit_conversions_table.php
│   ├── 2026_04_23_000004_create_product_stock_table.php
│   ├── 2026_04_23_000005_create_product_stock_movements_table.php
│   ├── 2026_04_23_000006_create_product_prices_table.php
│   ├── 2026_04_23_000001_create_product_categories_table.php
│   ├── 2026_04_23_000003_create_product_attributes_table.php
│   ├── 2026_04_23_000004_create_product_variants_table.php
│   ├── 2026_04_23_000005_alter_product_stock_for_multi_warehouse.php
│   ├── 2026_04_23_000007_create_product_tags_and_collections_table.php
│   ├── 2026_04_23_000008_create_product_batches_table.php
│   ├── 2026_04_23_000009_create_product_cost_history_table.php
│   ├── 2026_04_23_000010_create_product_variant_prices_table.php
│   ├── 2026_04_23_000011_create_product_price_lists_table.php
│   ├── 2026_04_23_000001_fix_variant_price_unique_constraint.php
│   ├── 2026_04_23_000001_convert_product_stock_to_variant_stock.php
│   ├── 2026_02_28_143415_rename_price_adjustment_to_purchase_price_in_product_variants_table.php
│   └── 2026_03_02_000001_add_variant_columns_to_stock_movements.php
├── purchase_order/              # Purchase Order (PO & Receiving)
│   ├── 2026_04_23_000001_create_purchase_orders_table.php
│   └── 2026_04_23_000001_create_purchase_order_receives_table.php
├── transaction/                 # Transaction (Sales Order)
│   └── 2026_04_23_000001_create_sales_orders_table.php
├── operational/                 # Operational transactions
├── configuration/               # Configuration tables
│   └── 2026_04_23_000001_create_notifications_table.php
└── customer/                    # Customer data
    ├── 2026_03_03_000001_create_customer_groups_table.php
    └── 2026_03_03_000002_create_customers_table.php
```

## Cara Menjalankan Migration

### 1. Fresh Start (Reset Semua + Migrate + Seed)

Paling umum digunakan saat development. Menghapus semua tabel, migrate ulang, dan seed data.

```bash
php artisan migrate:all --fresh --seed
```

### 2. Migration Biasa (Tanpa Reset)

Hanya menjalankan migration yang belum pernah dijalankan.

```bash
php artisan migrate:all
```

### 3. Force di Production

```bash
php artisan migrate:all --force
php artisan migrate:all --fresh --seed --force
```

### 4. Manual Per Folder

Jika ingin menjalankan per folder secara manual, ikuti urutan dependency:

```bash
# Step 1: Root migrations (Laravel default + uuid_v7 function)
php artisan migrate

# Step 2: Public data (parameter, wilayah, stock_mutation_types)
php artisan migrate --path=database/migrations/public

# Step 3: Master data (positions, divisions, business_units, suppliers, roles, menus)
php artisan migrate --path=database/migrations/master_data

# Step 4: Human resources (employees - butuh master_data)
php artisan migrate --path=database/migrations/human_resources

# Step 5: Auth (users, iam_accesses - butuh human_resources)
php artisan migrate --path=database/migrations/auth

# Step 6: Product (unified product master - butuh public, master_data)
php artisan migrate --path=database/migrations/product

# Step 7: Purchase Order (PO & receiving - butuh product, master_data)
php artisan migrate --path=database/migrations/purchase_order

# Step 8: Transaction (sales orders - butuh product, master_data)
php artisan migrate --path=database/migrations/transaction

# Step 9: Operational
php artisan migrate --path=database/migrations/operational

# Step 10: Configuration
php artisan migrate --path=database/migrations/configuration

# Step 11: Seed data
php artisan db:seed
```

### 5. Rollback / Reset

```bash
# Rollback semua migration (hanya root folder)
php artisan migrate:rollback

# Rollback per folder
php artisan migrate:rollback --path=database/migrations/transaction
php artisan migrate:rollback --path=database/migrations/purchase_order
php artisan migrate:rollback --path=database/migrations/product
php artisan migrate:rollback --path=database/migrations/auth
php artisan migrate:rollback --path=database/migrations/human_resources
php artisan migrate:rollback --path=database/migrations/master_data
php artisan migrate:rollback --path=database/migrations/public
php artisan migrate:rollback --path=database/migrations/operational
php artisan migrate:rollback --path=database/migrations/configuration

# Reset total (fresh tanpa seed)
php artisan migrate:all --fresh

# Lihat status migration
php artisan migrate:status
```

> **Penting**: Rollback manual harus dilakukan dengan urutan terbalik dari dependency (child dulu, baru parent).

### 6. Seed Saja (Tanpa Migration)

```bash
# Jalankan semua seeder
php artisan db:seed

# Jalankan seeder spesifik
php artisan db:seed --class=ProductParameterSeeder
php artisan db:seed --class=ProductNatureSeeder
php artisan db:seed --class=MenuSeeder
php artisan db:seed --class=BusinessUnitSeeder
```

## Urutan Dependency

Migration harus dijalankan dengan urutan berikut berdasarkan dependency:

| Urutan | Folder | Schema DB | Dependency |
|--------|--------|-----------|------------|
| 1 | **root** | public | - (uuid_v7, failed_jobs, dll) |
| 2 | **public** | public | - (parameter, wilayah, stock_mutation_types) |
| 3 | **master_data** | master_data | - (positions, divisions, business_units, suppliers, roles, menus) |
| 4 | **human_resources** | human_resources | → master_data (positions, divisions, business_units) |
| 5 | **auth** | auth | → human_resources (employees) |
| 6 | **product** | product | → public (parameters), master_data (business_units) |
| 7 | **purchase_order** | product | → product (products, variants, units), master_data (suppliers, business_units) |
| 8 | **transaction** | transaction | → product (products, variants, units), master_data (business_units, method_payments) |
| 9 | **operational** | operational | → master_data (business_units) |
| 10 | **configuration** | configuration | - (notifications) |
| 11 | **customer** | customer | customer_groups, customers |

> **Catatan**: Folder `purchase_order` membuat tabel di schema `product`, bukan schema terpisah. Folder `transaction` menggunakan schema `transaction` terpisah.

### Diagram Dependency

```
┌─────────────┐
│    root      │ (uuid_v7, failed_jobs, etc)
└──────┬──────┘
       │
       ├───────────────────────────┐
       ▼                           ▼
┌──────────────┐            ┌──────────┐
│  master_data │            │  public   │
└──────┬───────┘            └─────┬────┘
       │                          │
       ▼                          │
┌──────────────────┐              │
│ human_resources   │              │
└──────┬───────────┘              │
       │                          │
       ▼                          │
┌──────────┐                      │
│   auth    │                      │
└──────┬───┘                      │
       │                          │
       ├──────────────────────────┤
       ▼                          ▼
┌──────────────┐
│   product    │  ← schema product
└──────┬───────┘
       │
       ├───────────────┬───────────────┐
       ▼               ▼               ▼
┌──────────────┐┌─────────────┐
│purchase_order││ transaction │
└──────────────┘└─────────────┘
       │
       ├───────────────┐
       ▼               ▼
┌──────────────┐┌──────────────┐
│ operational  ││configuration │
└──────────────┘└──────────────┘
```

## Seeder

### Urutan Seed (DatabaseSeeder)

```php
$this->call([
    // 1. Core reference data (no FK dependencies)
    ProvinceSeeder::class,
    CitySeeder::class,
    RoleSeeder::class,
    PositionSeeder::class,
    ParameterSeeder::class,
    DivisionSeeder::class,

    // 2. Business units (before employees)
    BusinessUnitSeeder::class,

    // 3. Employees & users (depends on: positions, divisions, business_units, roles)
    EmployeeSeeder::class,
    UserSeeder::class,

    // 4. Menu and access (depends on: roles, menus)
    MenuSeeder::class,
    IamAccessSeeder::class,
    IamHasAccessSeeder::class,

    // 5. Product reference data
    StockMutationTypeSeeder::class,
    ProductParameterSeeder::class,
    ProductNatureSeeder::class,
]);
```

### Seeder Individu

```bash
# Reference data
php artisan db:seed --class=ProvinceSeeder
php artisan db:seed --class=CitySeeder
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=PositionSeeder
php artisan db:seed --class=ParameterSeeder
php artisan db:seed --class=DivisionSeeder

# Business units
php artisan db:seed --class=BusinessUnitSeeder

# Users
php artisan db:seed --class=EmployeeSeeder
php artisan db:seed --class=UserSeeder

# Menu & access
php artisan db:seed --class=MenuSeeder
php artisan db:seed --class=IamAccessSeeder
php artisan db:seed --class=IamHasAccessSeeder

# Product
php artisan db:seed --class=StockMutationTypeSeeder
php artisan db:seed --class=ProductParameterSeeder
php artisan db:seed --class=ProductNatureSeeder
php artisan db:seed --class=ProductPriceListSeeder
php artisan db:seed --class=ProductPriceListVariantSeeder
```

## Konfigurasi

### AppServiceProvider

Path migration sub-folder didaftarkan di `app/Providers/AppServiceProvider.php`:

```php
$this->loadMigrationsFrom([
    database_path('migrations/auth'),
    database_path('migrations/human_resources'),
    database_path('migrations/master_data'),
    database_path('migrations/public'),
    database_path('migrations/product'),
    database_path('migrations/purchase_order'),
    database_path('migrations/transaction'),
    database_path('migrations/operational'),
    database_path('migrations/configuration'),
    database_path('migrations/customer'),
]);
```

### MigrateAllCommand

Custom command `migrate:all` menjalankan migration dari semua folder dengan urutan yang benar. Konfigurasi ada di `app/Console/Commands/MigrateAllCommand.php`.

## Naming Convention

Format: `YYYY_MM_DD_NNNNNN_description.php`

Contoh:
- `auth/2026_04_23_000001_create_users_table.php`
- `master_data/2026_04_23_000001_create_positions_table.php`
- `product/2026_04_23_000001_create_product_schema_and_natures.php`

## Troubleshooting

### Error: "relation does not exist"

**Masalah**: Tabel yang direferensikan belum dibuat.

**Solusi**: Pastikan menjalankan migration dengan urutan yang benar menggunakan `migrate:all`.

### Error: "there is no unique constraint matching given keys"

**Masalah**: Self-referencing foreign key dibuat bersamaan dengan tabel (PostgreSQL).

**Solusi**: Pisahkan pembuatan foreign key setelah tabel dibuat:

```php
Schema::create('table_name', function (Blueprint $table) {
    $table->uuid('parent_id')->nullable();
});

Schema::table('table_name', function (Blueprint $table) {
    $table->foreign('parent_id')->references('id')->on('table_name');
});
```

### Migration sudah pernah jalan sebelum rename file

**Masalah**: Tabel `migrations` masih menyimpan nama file lama.

**Solusi**: Gunakan `migrate:fresh` untuk reset database, atau update nama file di tabel `migrations`.

## Referensi

- [Laravel Migration Documentation](https://laravel.com/docs/migrations)
- [Laravel Artisan Commands](https://laravel.com/docs/artisan)
