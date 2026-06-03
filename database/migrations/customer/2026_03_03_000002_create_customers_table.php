<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Customers - master pelanggan dengan FK ke customer_groups.
     * Mendukung: legal entity, identitas, koordinat, attachments, customer app (username/password).
     */
    public function up(): void
    {
        Schema::create('customer.customers', function (Blueprint $table) {
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            $table->uuid('customer_group_id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('tax_number', 50)->nullable();
            $table->string('tax_name', 200)->nullable();
            $table->text('tax_address')->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->nullable();

            // Location
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('long', 11, 8)->nullable();

            // Identity
            $table->string('identity_type', 30)->nullable();
            $table->string('identity_number', 50)->nullable();
            $table->jsonb('attachments')->nullable();

            // Customer app
            $table->string('username', 100)->nullable()->unique();
            $table->string('password', 255)->nullable();
            $table->boolean('has_app_access')->default(false);

            $table->string('customer_type', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_group_id');
            $table->index('code');
            $table->index('is_active');
            $table->index('has_app_access');
            $table->index(['customer_group_id', 'code']);
        });

        Schema::table('customer.customers', function (Blueprint $table) {
            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer.customer_groups')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer.customers');
    }
};
