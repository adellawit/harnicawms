<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS accounting');

        Schema::create('accounting.beginning_balances', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id');
            $table->uuid('fiscal_calendar_id');
            $table->string('status', 20)->default('draft'); // draft, posted
            $table->timestamp('posted_at')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('fiscal_calendar_id');
            $table->index('status');
        });

        Schema::table('accounting.beginning_balances', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('restrict');

            $table->foreign('fiscal_calendar_id')
                ->references('id')
                ->on('accounting.fiscal_calendars')
                ->onDelete('cascade');
        });

        DB::statement('
            CREATE UNIQUE INDEX beginning_balances_calendar_unique
            ON accounting.beginning_balances (fiscal_calendar_id)
            WHERE deleted_at IS NULL
        ');

        Schema::create('accounting.beginning_balance_lines', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('beginning_balance_id');
            $table->uuid('account_id');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('source', 30)->default('manual'); // manual, suggested, auto_balance
            $table->boolean('is_auto_balancing')->default(false);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('beginning_balance_id');
            $table->index('account_id');
        });

        Schema::table('accounting.beginning_balance_lines', function (Blueprint $table) {
            $table->foreign('beginning_balance_id')
                ->references('id')
                ->on('accounting.beginning_balances')
                ->onDelete('cascade');

            $table->foreign('account_id')
                ->references('id')
                ->on('accounting.chart_of_accounts')
                ->onDelete('restrict');
        });

        DB::statement('
            CREATE UNIQUE INDEX beginning_balance_lines_account_unique
            ON accounting.beginning_balance_lines (beginning_balance_id, account_id)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting.beginning_balance_lines');
        Schema::dropIfExists('accounting.beginning_balances');
    }
};
