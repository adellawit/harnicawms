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

        Schema::create('accounting.fiscal_calendars', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('company_id');
            $table->string('name', 255);
            $table->unsignedSmallInteger('fiscal_year');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('fiscal_year');
            $table->index('is_active');
            $table->index('is_closed');
        });

        Schema::table('accounting.fiscal_calendars', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('restrict');
        });

        DB::statement('
            CREATE UNIQUE INDEX fiscal_calendars_company_year_unique
            ON accounting.fiscal_calendars (company_id, fiscal_year)
            WHERE deleted_at IS NULL
        ');

        Schema::create('accounting.fiscal_periods', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('fiscal_calendar_id');
            $table->unsignedSmallInteger('period_no');
            $table->string('name', 100);
            $table->string('code', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // open, closed, locked
            $table->boolean('is_adjustment')->default(false);

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('fiscal_calendar_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        Schema::table('accounting.fiscal_periods', function (Blueprint $table) {
            $table->foreign('fiscal_calendar_id')
                ->references('id')
                ->on('accounting.fiscal_calendars')
                ->onDelete('cascade');
        });

        DB::statement('
            CREATE UNIQUE INDEX fiscal_periods_calendar_period_unique
            ON accounting.fiscal_periods (fiscal_calendar_id, period_no)
            WHERE deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting.fiscal_periods');
        Schema::dropIfExists('accounting.fiscal_calendars');
    }
};
