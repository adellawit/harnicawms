<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS master_data');

        Schema::create('master_data.business_unit_types', function (Blueprint $table) {
            $table->string('code', 30)->primary();
            $table->string('description', 100)->nullable();
        });

        DB::table('master_data.business_unit_types')->insert([
            ['code' => 'HOLDING', 'description' => 'Holding'],
            ['code' => 'COMPANY', 'description' => 'Company'],
            ['code' => 'BRANCH', 'description' => 'Branch'],
            ['code' => 'WAREHOUSE', 'description' => 'Warehouse'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data.business_unit_types');
    }
};
