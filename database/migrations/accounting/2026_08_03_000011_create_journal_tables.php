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

        Schema::create('accounting.journals', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id');
            $table->uuid('fiscal_calendar_id')->nullable();
            $table->uuid('fiscal_period_id')->nullable();
            $table->string('journal_no', 50);
            $table->date('journal_date');
            $table->text('description')->nullable(); // Description All
            $table->string('status', 20)->default('draft'); // draft, posted
            $table->timestamp('posted_at')->nullable();
            $table->uuid('posted_by')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('fiscal_calendar_id');
            $table->index('fiscal_period_id');
            $table->index('journal_date');
            $table->index('status');
            $table->index('journal_no');
        });

        Schema::table('accounting.journals', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('restrict');

            $table->foreign('fiscal_calendar_id')
                ->references('id')
                ->on('accounting.fiscal_calendars')
                ->onDelete('restrict');

            $table->foreign('fiscal_period_id')
                ->references('id')
                ->on('accounting.fiscal_periods')
                ->onDelete('restrict');
        });

        DB::statement('
            CREATE UNIQUE INDEX journals_company_no_unique
            ON accounting.journals (company_id, journal_no)
            WHERE deleted_at IS NULL
        ');

        Schema::create('accounting.journal_lines', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('journal_id');
            $table->uuid('account_id');
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->string('description', 500)->nullable(); // Description per COA
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('journal_id');
            $table->index('account_id');
        });

        Schema::table('accounting.journal_lines', function (Blueprint $table) {
            $table->foreign('journal_id')
                ->references('id')
                ->on('accounting.journals')
                ->onDelete('cascade');

            $table->foreign('account_id')
                ->references('id')
                ->on('accounting.chart_of_accounts')
                ->onDelete('restrict');
        });

        Schema::create('accounting.journal_attachments', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('journal_id');
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('journal_id');
        });

        Schema::table('accounting.journal_attachments', function (Blueprint $table) {
            $table->foreign('journal_id')
                ->references('id')
                ->on('accounting.journals')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting.journal_attachments');
        Schema::dropIfExists('accounting.journal_lines');
        Schema::dropIfExists('accounting.journals');
    }
};
