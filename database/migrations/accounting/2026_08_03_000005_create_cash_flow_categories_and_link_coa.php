<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CATEGORIES = [
        ['id' => 'a1c0cf01-0001-4001-8001-000000000001', 'code' => 'operating', 'name' => 'Operating', 'sort_order' => 1],
        ['id' => 'a1c0cf01-0001-4001-8001-000000000002', 'code' => 'investing', 'name' => 'Investing', 'sort_order' => 2],
        ['id' => 'a1c0cf01-0001-4001-8001-000000000003', 'code' => 'financing', 'name' => 'Financing', 'sort_order' => 3],
        ['id' => 'a1c0cf01-0001-4001-8001-000000000004', 'code' => 'none', 'name' => 'Tidak digolongkan', 'sort_order' => 99],
    ];

    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS accounting');

        if (! Schema::hasTable('accounting.cash_flow_categories')) {
            Schema::create('accounting.cash_flow_categories', function (Blueprint $table) {
                $table->uuid('id')
                    ->primary()
                    ->default(DB::raw('public.uuid_generate_v7()'));

                $table->string('code', 50);
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);

                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->uuid('deleted_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('is_active');
                $table->index('sort_order');
            });

            DB::statement('
                CREATE UNIQUE INDEX cash_flow_categories_code_unique
                ON accounting.cash_flow_categories (code)
                WHERE deleted_at IS NULL
            ');
        }

        $now = now();
        foreach (self::CATEGORIES as $row) {
            $exists = DB::table('accounting.cash_flow_categories')
                ->where('id', $row['id'])
                ->orWhere('code', $row['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('accounting.cash_flow_categories')->insert([
                'id' => $row['id'],
                'code' => $row['code'],
                'name' => $row['name'],
                'description' => null,
                'sort_order' => $row['sort_order'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasColumn('accounting.chart_of_accounts', 'cash_flow_category_id')) {
            Schema::table('accounting.chart_of_accounts', function (Blueprint $table) {
                $table->uuid('cash_flow_category_id')->nullable()->after('normal_balance');
                $table->index('cash_flow_category_id');
            });
        }

        if (Schema::hasColumn('accounting.chart_of_accounts', 'cash_flow_category')) {
            foreach (self::CATEGORIES as $row) {
                DB::table('accounting.chart_of_accounts')
                    ->where('cash_flow_category', $row['code'])
                    ->update(['cash_flow_category_id' => $row['id']]);
            }

            DB::table('accounting.chart_of_accounts')
                ->whereNull('cash_flow_category_id')
                ->update(['cash_flow_category_id' => 'a1c0cf01-0001-4001-8001-000000000004']);

            DB::statement('DROP INDEX IF EXISTS accounting.accounting_chart_of_accounts_cash_flow_category_index');

            Schema::table('accounting.chart_of_accounts', function (Blueprint $table) {
                $table->dropColumn('cash_flow_category');
            });
        }

        $fkExists = DB::selectOne("
            SELECT 1
            FROM information_schema.table_constraints
            WHERE constraint_schema = 'accounting'
              AND table_name = 'chart_of_accounts'
              AND constraint_name = 'chart_of_accounts_cash_flow_category_id_foreign'
        ");

        if (! $fkExists) {
            Schema::table('accounting.chart_of_accounts', function (Blueprint $table) {
                $table->foreign('cash_flow_category_id')
                    ->references('id')
                    ->on('accounting.cash_flow_categories')
                    ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        $fkExists = DB::selectOne("
            SELECT 1
            FROM information_schema.table_constraints
            WHERE constraint_schema = 'accounting'
              AND table_name = 'chart_of_accounts'
              AND constraint_name = 'chart_of_accounts_cash_flow_category_id_foreign'
        ");

        if ($fkExists) {
            Schema::table('accounting.chart_of_accounts', function (Blueprint $table) {
                $table->dropForeign(['cash_flow_category_id']);
            });
        }

        if (! Schema::hasColumn('accounting.chart_of_accounts', 'cash_flow_category')) {
            Schema::table('accounting.chart_of_accounts', function (Blueprint $table) {
                $table->string('cash_flow_category', 30)->nullable()->after('normal_balance');
            });
        }

        foreach (self::CATEGORIES as $row) {
            DB::table('accounting.chart_of_accounts')
                ->where('cash_flow_category_id', $row['id'])
                ->update(['cash_flow_category' => $row['code']]);
        }

        if (Schema::hasColumn('accounting.chart_of_accounts', 'cash_flow_category_id')) {
            DB::statement('DROP INDEX IF EXISTS accounting.accounting_chart_of_accounts_cash_flow_category_id_index');
            Schema::table('accounting.chart_of_accounts', function (Blueprint $table) {
                $table->dropColumn('cash_flow_category_id');
            });
        }

        Schema::dropIfExists('accounting.cash_flow_categories');
    }
};
