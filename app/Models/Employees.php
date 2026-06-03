<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employees extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The database connection that should be used by the model.
     *
     * @var string
     */
    protected $connection = 'pgsql';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'human_resources.employees';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',

        // === RELASI ORGANISASI ===
        'position_id',
        'division_id',
        'business_unit_id',

        // === IDENTITAS KARYAWAN ===
        'employee_code',
        'identity_number',
        'fullname',
        'nickname',
        'gender',
        'place_of_birth',
        'date_of_birth',
        'religion',
        'marital_status',
        'number_of_dependents',

        // === KONTAK & ALAMAT ===
        'address',
        'city',
        'province',
        'postal_code',
        'phone_number',
        'email',

        // === DATA KEPEGAWAIAN ===
        'join_date',
        'contract_start_date',
        'contract_end_date',
        'employment_status',
        'employee_status',

        // === PAYROLL & LEGAL ===
        'tax_number',
        'bpjs_kesehatan',
        'bpjs_ketenagakerjaan',
        'bank_name',
        'bank_account_number',
        'bank_account_name',

        // === EMERGENCY CONTACT ===
        'emergency_contact_name',
        'emergency_contact_relation',
        'emergency_contact_phone',

        // === AUDIT ===
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'join_date' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'number_of_dependents' => 'integer',
    ];

    /**
     * Get the position associated with the employee.
     */
    public function position(): HasOne
    {
        return $this->hasOne(Position::class, 'id', 'position_id');
    }

    /**
     * Get the division associated with the employee.
     */
    public function division(): HasOne
    {
        return $this->hasOne(Division::class, 'id', 'division_id');
    }

    /**
     * Get the business unit associated with the employee.
     */
    public function businessUnit(): HasOne
    {
        return $this->hasOne(BusinessUnit::class, 'id', 'business_unit_id');
    }
}
