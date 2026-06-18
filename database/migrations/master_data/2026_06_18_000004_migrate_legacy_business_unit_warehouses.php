<?php

use App\Services\MasterData\WarehouseBootstrapService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(WarehouseBootstrapService::class)->migrateAll();
    }

    public function down(): void
    {
        app(WarehouseBootstrapService::class)->rollback();
    }
};
