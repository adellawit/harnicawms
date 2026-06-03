<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_data.method_payments', function (Blueprint $table) {
            $table->boolean('uses_payment_gateway')->default(false)->after('is_active');
            $table->string('gateway_provider', 30)->nullable()->after('uses_payment_gateway');
            $table->string('payment_group_code', 50)->nullable()->after('gateway_provider');
            $table->string('gateway_channel_code', 50)->nullable()->after('payment_group_code');

            $table->index('uses_payment_gateway');
            $table->index(['branch_id', 'payment_group_code']);
            $table->index(['branch_id', 'gateway_channel_code']);
        });

        foreach (['TRANSFER', 'QRIS', 'EWALLET'] as $pgCode) {
            DB::table('master_data.method_payments')
                ->where('code', $pgCode)
                ->whereNull('deleted_at')
                ->update([
                    'uses_payment_gateway' => true,
                    'gateway_provider' => 'xendit',
                    'payment_group_code' => $pgCode,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('master_data.method_payments', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'gateway_channel_code']);
            $table->dropIndex(['branch_id', 'payment_group_code']);
            $table->dropIndex(['uses_payment_gateway']);
            $table->dropColumn([
                'uses_payment_gateway',
                'gateway_provider',
                'payment_group_code',
                'gateway_channel_code',
            ]);
        });
    }
};
