<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE SCHEMA IF NOT EXISTS accounting');

        Schema::create('accounting.chart_of_accounts', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id');
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('account_type', 30); // asset, liability, equity, revenue, expense
            $table->string('normal_balance', 10); // debit, credit
            $table->uuid('parent_id')->nullable();
            $table->unsignedSmallInteger('level')->default(1);
            $table->boolean('is_header')->default(false);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('parent_id');
            $table->index('account_type');
            $table->index('is_active');
            $table->index(['company_id', 'code']);
        });

        Schema::table('accounting.chart_of_accounts', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('restrict');

            $table->foreign('parent_id')
                ->references('id')
                ->on('accounting.chart_of_accounts')
                ->onDelete('restrict');
        });

        DB::statement('
            CREATE UNIQUE INDEX chart_of_accounts_company_code_unique
            ON accounting.chart_of_accounts (company_id, code)
            WHERE deleted_at IS NULL
        ');

        Schema::create('accounting.account_mappings', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id');
            $table->string('mapping_key', 80);
            $table->uuid('account_id');
            $table->string('description', 255)->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('mapping_key');
            $table->index('account_id');
        });

        Schema::table('accounting.account_mappings', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('restrict');

            $table->foreign('account_id')
                ->references('id')
                ->on('accounting.chart_of_accounts')
                ->onDelete('restrict');
        });

        DB::statement('
            CREATE UNIQUE INDEX account_mappings_company_key_unique
            ON accounting.account_mappings (company_id, mapping_key)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting.account_mappings');
        Schema::dropIfExists('accounting.chart_of_accounts');
    }
};
