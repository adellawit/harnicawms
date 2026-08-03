<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting.beginning_balances', function (Blueprint $table) {
            $table->uuid('journal_id')->nullable()->after('fiscal_calendar_id');
            $table->index('journal_id');
        });

        Schema::table('accounting.beginning_balances', function (Blueprint $table) {
            $table->foreign('journal_id')
                ->references('id')
                ->on('accounting.journals')
                ->onDelete('set null');
        });

        Schema::table('accounting.journals', function (Blueprint $table) {
            $table->string('journal_type', 30)->default('manual')->after('status');
            $table->index('journal_type');
        });
    }

    public function down(): void
    {
        Schema::table('accounting.beginning_balances', function (Blueprint $table) {
            $table->dropForeign(['journal_id']);
            $table->dropColumn('journal_id');
        });

        Schema::table('accounting.journals', function (Blueprint $table) {
            $table->dropIndex(['journal_type']);
            $table->dropColumn('journal_type');
        });
    }
};
