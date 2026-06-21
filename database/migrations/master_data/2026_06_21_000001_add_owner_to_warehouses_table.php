<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('master_data.warehouses')) {
            return;
        }

        Schema::table('master_data.warehouses', function (Blueprint $table) {
            if (! Schema::hasColumn('master_data.warehouses', 'owner_type')) {
                $table->string('owner_type', 30)->nullable()->after('branch_id');
            }

            if (! Schema::hasColumn('master_data.warehouses', 'owner_id')) {
                $table->uuid('owner_id')->nullable()->after('owner_type');
            }
        });

        DB::statement("
            UPDATE master_data.warehouses
            SET owner_type = CASE WHEN branch_id IS NULL THEN 'COMPANY' ELSE 'BRANCH' END,
                owner_id = COALESCE(branch_id, company_id)
            WHERE owner_type IS NULL
              AND owner_id IS NULL
        ");

        Schema::table('master_data.warehouses', function (Blueprint $table) {
            $table->index(['owner_type', 'owner_id'], 'warehouses_owner_type_owner_id_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('master_data.warehouses')) {
            return;
        }

        Schema::table('master_data.warehouses', function (Blueprint $table) {
            $table->dropIndex('warehouses_owner_type_owner_id_idx');
            $table->dropColumn(['owner_type', 'owner_id']);
        });
    }
};
