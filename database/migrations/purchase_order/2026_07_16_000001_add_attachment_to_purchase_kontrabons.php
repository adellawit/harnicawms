<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.purchase_kontrabons', function (Blueprint $table) {
            $table->string('attachment_path', 500)->nullable()->after('notes');
            $table->string('attachment_name', 255)->nullable()->after('attachment_path');
            $table->string('attachment_mime', 120)->nullable()->after('attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('product.purchase_kontrabons', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime']);
        });
    }
};
