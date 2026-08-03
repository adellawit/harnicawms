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

        Schema::create('accounting.cash_bank_reconciliations', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id');
            $table->uuid('account_id');
            $table->date('reconciliation_date');
            $table->decimal('statement_balance', 18, 2)->default(0);
            $table->decimal('book_balance', 18, 2)->default(0);
            $table->decimal('cleared_balance', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, completed
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->uuid('completed_by')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('account_id');
            $table->index('reconciliation_date');
            $table->index('status');
        });

        Schema::table('accounting.cash_bank_reconciliations', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('restrict');

            $table->foreign('account_id')
                ->references('id')
                ->on('accounting.chart_of_accounts')
                ->onDelete('restrict');
        });

        Schema::create('accounting.cash_bank_reconciliation_lines', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('reconciliation_id');
            $table->uuid('journal_line_id');
            $table->boolean('is_cleared')->default(false);

            $table->timestamps();

            $table->index('reconciliation_id');
            $table->index('journal_line_id');
        });

        Schema::table('accounting.cash_bank_reconciliation_lines', function (Blueprint $table) {
            $table->foreign('reconciliation_id')
                ->references('id')
                ->on('accounting.cash_bank_reconciliations')
                ->onDelete('cascade');

            $table->foreign('journal_line_id')
                ->references('id')
                ->on('accounting.journal_lines')
                ->onDelete('restrict');
        });

        DB::statement('
            CREATE UNIQUE INDEX cash_bank_recon_lines_unique
            ON accounting.cash_bank_reconciliation_lines (reconciliation_id, journal_line_id)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting.cash_bank_reconciliation_lines');
        Schema::dropIfExists('accounting.cash_bank_reconciliations');
    }
};
