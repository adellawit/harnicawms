<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE SCHEMA IF NOT EXISTS partner');

        Schema::create('partner.agents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('default_warehouse_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('status', 30)->default('active');
            $table->string('approval_status', 30)->default('approved');
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
            $table->index('customer_id');
            $table->unique('user_id');
            $table->index('default_warehouse_id');
            $table->index(['status', 'approval_status']);
        });

        Schema::create('partner.resellers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id');
            $table->uuid('agent_id');
            $table->uuid('customer_id')->nullable();
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('status', 30)->default('active');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index('company_id');
            $table->index('agent_id');
            $table->index('customer_id');
            $table->index('status');
        });

        Schema::create('partner.partner_applications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('company_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('assigned_agent_id')->nullable();
            $table->uuid('converted_agent_id')->nullable();
            $table->uuid('converted_reseller_id')->nullable();
            $table->string('application_number', 50)->unique();
            $table->string('partner_type', 20);
            $table->string('name', 200);
            $table->string('email', 150)->nullable();
            $table->string('phone', 50)->nullable();
            $table->decimal('requested_purchase_quantity', 18, 4)->default(0);
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('status', 30)->default('submitted');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('customer_id');
            $table->index('assigned_agent_id');
            $table->index('partner_type');
            $table->index('status');
        });

        Schema::create('partner.partner_application_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('application_id');
            $table->string('document_type', 80);
            $table->string('file_path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('status', 30)->default('submitted');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('document_type');
            $table->index('status');
        });

        Schema::create('partner.partner_followups', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('application_id');
            $table->uuid('followup_by')->nullable();
            $table->string('followup_type', 50)->default('manual');
            $table->string('status', 30)->default('open');
            $table->text('notes')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('followup_by');
            $table->index('status');
            $table->index('next_followup_at');
        });

        Schema::create('partner.agent_reseller_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.uuid_generate_v7()'));
            $table->uuid('agent_id');
            $table->uuid('reseller_id');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('reseller_id');
            $table->index('is_active');
        });

        $this->addForeignKeys();

        DB::statement('
            CREATE UNIQUE INDEX agent_reseller_assignments_one_active_reseller
            ON partner.agent_reseller_assignments (reseller_id)
            WHERE is_active = true
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS partner.agent_reseller_assignments_one_active_reseller');
        Schema::dropIfExists('partner.agent_reseller_assignments');
        Schema::dropIfExists('partner.partner_followups');
        Schema::dropIfExists('partner.partner_application_documents');
        Schema::dropIfExists('partner.partner_applications');
        Schema::dropIfExists('partner.resellers');
        Schema::dropIfExists('partner.agents');
    }

    private function addForeignKeys(): void
    {
        Schema::table('partner.agents', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customer.customers')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('set null');
            $table->foreign('default_warehouse_id')->references('id')->on('master_data.warehouses')->onDelete('set null');
        });

        Schema::table('partner.resellers', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('partner.agents')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customer.customers')->onDelete('set null');
        });

        Schema::table('partner.partner_applications', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('master_data.business_units')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customer.customers')->onDelete('set null');
            $table->foreign('assigned_agent_id')->references('id')->on('partner.agents')->onDelete('set null');
            $table->foreign('converted_agent_id')->references('id')->on('partner.agents')->onDelete('set null');
            $table->foreign('converted_reseller_id')->references('id')->on('partner.resellers')->onDelete('set null');
        });

        Schema::table('partner.partner_application_documents', function (Blueprint $table) {
            $table->foreign('application_id')->references('id')->on('partner.partner_applications')->onDelete('cascade');
        });

        Schema::table('partner.partner_followups', function (Blueprint $table) {
            $table->foreign('application_id')->references('id')->on('partner.partner_applications')->onDelete('cascade');
        });

        Schema::table('partner.agent_reseller_assignments', function (Blueprint $table) {
            $table->foreign('agent_id')->references('id')->on('partner.agents')->onDelete('cascade');
            $table->foreign('reseller_id')->references('id')->on('partner.resellers')->onDelete('cascade');
        });
    }
};
