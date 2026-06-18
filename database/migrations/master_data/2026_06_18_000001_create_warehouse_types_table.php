<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS master_data');

        Schema::create('master_data.warehouse_types', function (Blueprint $table) {
            $table->string('code', 30)->primary();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });

        DB::table('master_data.warehouse_types')->insert([
            ['code' => 'RAW_MATERIAL', 'name' => 'Gudang Bahan Baku', 'description' => 'Penyimpanan bahan baku', 'sort_order' => 10],
            ['code' => 'WIP', 'name' => 'Gudang WIP', 'description' => 'Work in progress / proses', 'sort_order' => 20],
            ['code' => 'PRODUCTION', 'name' => 'Gudang Produksi', 'description' => 'Area produksi / manufaktur', 'sort_order' => 30],
            ['code' => 'FG', 'name' => 'Gudang Barang Jadi', 'description' => 'Finished goods', 'sort_order' => 40],
            ['code' => 'GENERAL', 'name' => 'Gudang Umum', 'description' => 'Gudang serbaguna / default cabang', 'sort_order' => 50],
            ['code' => 'TRANSIT', 'name' => 'Gudang Transit', 'description' => 'Stok dalam perjalanan', 'sort_order' => 60],
            ['code' => 'QUARANTINE', 'name' => 'Gudang QC / Hold', 'description' => 'Karantina / quality hold', 'sort_order' => 70],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data.warehouse_types');
    }
};
