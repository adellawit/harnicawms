<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');
        DB::statement('CREATE SCHEMA IF NOT EXISTS human_resources');

        Schema::create('human_resources.employees', function (Blueprint $table) {
            // === PRIMARY KEY ===
            $table->uuid('id')
                ->primary()
                ->default(DB::raw('public.uuid_generate_v7()'));

            // === RELASI ORGANISASI ===
            $table->uuid('position_id')->nullable();
            $table->uuid('division_id')->nullable();
            $table->uuid('business_unit_id')->nullable();

            // === IDENTITAS KARYAWAN ===
            $table->string('employee_code', 50)->unique(); // NIK internal
            $table->string('identity_number', 50)->unique(); // KTP / Passport
            $table->string('fullname', 150);
            $table->string('nickname', 100)->nullable();
            $table->enum('gender', ['Laki-laki', 'Perempuan', 'Other'])->nullable();
            $table->string('place_of_birth', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('marital_status', 50)->nullable();
            $table->integer('number_of_dependents')->default(0);

            // === KONTAK & ALAMAT ===
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('email', 150)->nullable();

            // === DATA KEPEGAWAIAN ===
            $table->date('join_date')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('employment_status', [
                'Permanent',
                'Contract',
                'Probation',
                'Intern',
                'Freelance'
            ])->default('Probation');

            $table->enum('employee_status', [
                'Active',
                'Inactive',
                'Resigned',
                'Terminated'
            ])->default('Active');

            // === PAYROLL & LEGAL ===
            $table->string('tax_number', 50)->nullable(); // NPWP
            $table->string('bpjs_kesehatan', 50)->nullable();
            $table->string('bpjs_ketenagakerjaan', 50)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('bank_account_name', 150)->nullable();

            // === EMERGENCY CONTACT ===
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_relation', 100)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();

            // === AUDIT ===
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // === INDEXING ===
            $table->index('position_id');
            $table->index('division_id');
            $table->index('business_unit_id');
        });

        // === FK LINTAS SCHEMA (EXPLICIT) ===
        Schema::table('human_resources.employees', function (Blueprint $table) {
            $table->foreign('position_id')
                ->references('id')
                ->on('master_data.positions')
                ->onDelete('set null');

            $table->foreign('division_id')
                ->references('id')
                ->on('master_data.divisions')
                ->onDelete('set null');

            $table->foreign('business_unit_id')
                ->references('id')
                ->on('master_data.business_units')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_resources.employees');
    }
};
