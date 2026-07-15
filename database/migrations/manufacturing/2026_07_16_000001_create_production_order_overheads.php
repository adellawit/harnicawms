<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturing.production_order_overheads', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('production_order_id');
            $table->string('description', 255);
            $table->decimal('amount', 18, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('production_order_id');
        });

        Schema::table('manufacturing.production_order_overheads', function (Blueprint $table) {
            $table->foreign('production_order_id')
                ->references('id')
                ->on('manufacturing.production_orders')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing.production_order_overheads');
    }
};
