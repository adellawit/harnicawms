<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm.membership_point_configurations', function (Blueprint $table) {
            $table->uuid('branch_id')->nullable()->after('id');
            $table->index('branch_id', 'membership_point_configurations_branch_id_idx');
            $table->foreign('branch_id', 'membership_point_configurations_branch_fk')
                ->references('id')
                ->on('master_data.business_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm.membership_point_configurations', function (Blueprint $table) {
            $table->dropForeign('membership_point_configurations_branch_fk');
            $table->dropIndex('membership_point_configurations_branch_id_idx');
            $table->dropColumn('branch_id');
        });
    }
};
