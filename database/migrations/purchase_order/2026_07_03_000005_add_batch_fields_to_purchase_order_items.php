<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('product.purchase_order_items', 'batch_number')) {
                $table->string('batch_number', 100)->nullable()->after('unit_id');
                $table->index('batch_number');
            }
            if (! Schema::hasColumn('product.purchase_order_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('batch_number');
                $table->index('expiry_date');
            }
            if (! Schema::hasColumn('product.purchase_order_items', 'product_batch_id')) {
                $table->uuid('product_batch_id')->nullable()->after('expiry_date');
                $table->index('product_batch_id');
            }
        });

        if (Schema::hasTable('product.product_batches')) {
            Schema::table('product.purchase_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('product.purchase_order_items', 'product_batch_id')) {
                    $table->foreign('product_batch_id')
                        ->references('id')
                        ->on('product.product_batches')
                        ->onDelete('set null');
                }
            });
        }

        Schema::table('product.purchase_order_receive_items', function (Blueprint $table) {
            if (! Schema::hasColumn('product.purchase_order_receive_items', 'batch_number')) {
                $table->string('batch_number', 100)->nullable()->after('unit_id');
            }
            if (! Schema::hasColumn('product.purchase_order_receive_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('batch_number');
            }
            if (! Schema::hasColumn('product.purchase_order_receive_items', 'product_batch_id')) {
                $table->uuid('product_batch_id')->nullable()->after('expiry_date');
                $table->index('product_batch_id');
            }
        });

        if (Schema::hasTable('product.product_batches')) {
            Schema::table('product.purchase_order_receive_items', function (Blueprint $table) {
                if (Schema::hasColumn('product.purchase_order_receive_items', 'product_batch_id')) {
                    $table->foreign('product_batch_id')
                        ->references('id')
                        ->on('product.product_batches')
                        ->onDelete('set null');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('product.purchase_order_receive_items', function (Blueprint $table) {
            if (Schema::hasColumn('product.purchase_order_receive_items', 'product_batch_id')) {
                $table->dropForeign(['product_batch_id']);
                $table->dropColumn('product_batch_id');
            }
            if (Schema::hasColumn('product.purchase_order_receive_items', 'expiry_date')) {
                $table->dropColumn('expiry_date');
            }
            if (Schema::hasColumn('product.purchase_order_receive_items', 'batch_number')) {
                $table->dropColumn('batch_number');
            }
        });

        Schema::table('product.purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('product.purchase_order_items', 'product_batch_id')) {
                $table->dropForeign(['product_batch_id']);
                $table->dropColumn('product_batch_id');
            }
            if (Schema::hasColumn('product.purchase_order_items', 'expiry_date')) {
                $table->dropColumn('expiry_date');
            }
            if (Schema::hasColumn('product.purchase_order_items', 'batch_number')) {
                $table->dropColumn('batch_number');
            }
        });
    }
};
