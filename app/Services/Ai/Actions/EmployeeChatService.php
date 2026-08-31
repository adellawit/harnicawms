<?php

namespace App\Services\Ai\Actions;

use App\Models\Division;
use App\Models\Employees;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\AgentContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create karyawan + akun login dari chat (manage_record entity=employee).
 *
 * Dipanggil dari AgentRecordActionService::handle() saat operation=create
 * dan entity employee. ManageRecordTool::execute() → handle().
 * Tidak ada file EmployeeChat* lain. Tulis DB, bukan data file.
 *
 * Kolom employees (contoh sintetis): fullname, employee_code EMP-20260816-001,
 * identity_number NIK-CHAT-…, email, join_date Y-m-d, employee_status Active,
 * employment_status Probation, position_id/division_id/business_unit_id nullable.
 * Kolom users: username dari email, password digenerate, role_id, need_update_password.
 */
class EmployeeChatService
{
    public function __construct(
        protected EmployeeChatFieldMapper $mapper,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function create(array $arguments, AgentContext $context, bool $commit = true): array
    {
        $mapped = $this->mapper->map($arguments, now()->toDateString());

        if ($mapped['missing'] !== []) {
            return $this->missing($mapped['missing'], (string) $mapped['message']);
        }

        $role = $this->resolveRole($mapped['role_id'], $mapped['role_name']);
        if ($role === null) {
            $message = $mapped['role_name'] !== null
                ? 'Role "'.$mapped['role_name'].'" tidak ditemukan. Role-nya apa? Misalnya Staff, Manager, atau Super Admin.'
                : $this->mapper->questionFor(['role']);

            return $this->missing(['role'], $message);
        }

        if (strcasecmp((string) $role->name, 'Super Admin') === 0
            && ($arguments['_confirmed'] ?? false) !== true) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'super_admin',
                'title' => 'Tetapkan Super Admin?',
                'body' => 'Karyawan "'.$mapped['fullname'].'" akan mendapat role Super Admin.',
                'confirm_label' => 'Ya, tetapkan',
                'cancel_label' => 'Batal',
                'message' => 'Role Super Admin perlu konfirmasi. Belum ada karyawan yang dibuat.',
            ];
        }

        $position = $this->resolveNamed(
            Position::query()->whereNull('deleted_at'),
            $mapped['position_id'],
            $mapped['position_name'],
        );
        if ($mapped['position_name'] !== null && $position === null && $mapped['position_id'] === null) {
            return $this->missing(
                ['position'],
                'Jabatan "'.$mapped['position_name'].'" tidak ditemukan. Jabatannya apa?',
            );
        }

        $division = $this->resolveNamed(
            Division::query()->whereNull('deleted_at'),
            $mapped['division_id'],
            $mapped['division_name'],
        );
        if ($mapped['division_name'] !== null && $division === null && $mapped['division_id'] === null) {
            return $this->missing(
                ['division'],
                'Divisi "'.$mapped['division_name'].'" tidak ditemukan. Divisinya apa?',
            );
        }

        $existing = $this->findExisting($mapped['fullname'], $mapped['email']);
        if ($existing !== null) {
            $item = $this->serialize($existing);

            return [
                'success' => true,
                'applied' => false,
                'already_exists' => true,
                'entity' => 'employee',
                'item' => $item,
                'items' => [$item],
                'message' => 'Karyawan "'.$item['label'].'" sudah ada.',
            ];
        }

        if (! $commit) {
            return [
                'success' => true,
                'needs_confirmation' => true,
                'confirmation_kind' => 'employee_create',
                'title' => 'Tambah karyawan?',
                'body' => 'Karyawan "'.$mapped['fullname'].'" dengan role '.$role->name.' akan ditambahkan beserta akun login. Belum ada data yang disimpan.',
                'confirm_label' => 'Tambah',
                'cancel_label' => 'Batal',
                'message' => 'Penambahan karyawan perlu konfirmasi di kartu. Belum ada data yang diubah.',
            ];
        }

        $username = $this->uniqueUsername($mapped['username'] ?? $this->usernameFromName($mapped['fullname']));
        $passwordGenerated = $mapped['password'] === null;
        $password = $mapped['password'] ?? Str::password(12);
        $businessUnitId = $mapped['business_unit_id']
            ?? $context->branchId
            ?? $context->user->current_business_unit_id;
        $nameParts = preg_split('/\s+/', $mapped['fullname'], 2, PREG_SPLIT_NO_EMPTY) ?: [$mapped['fullname']];
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? $nameParts[0];

        try {
            $bundle = DB::transaction(function () use (
                $mapped,
                $context,
                $role,
                $position,
                $division,
                $username,
                $password,
                $passwordGenerated,
                $businessUnitId,
                $firstName,
                $lastName,
            ) {
                $employee = Employees::query()->create([
                    'position_id' => $position?->id,
                    'division_id' => $division?->id,
                    'business_unit_id' => $businessUnitId,
                    'employee_code' => $mapped['employee_code'] ?? $this->uniqueEmployeeCode(),
                    'identity_number' => $mapped['identity_number'] ?? $this->uniqueIdentityNumber(),
                    'fullname' => $mapped['fullname'],
                    'nickname' => $mapped['nickname'] ?? $firstName,
                    'email' => $mapped['email'],
                    'phone_number' => $mapped['phone_number'],
                    'join_date' => $mapped['join_date'] ?? now()->toDateString(),
                    'employment_status' => $mapped['employment_status'] ?? 'Probation',
                    'employee_status' => $mapped['employee_status'] ?? 'Active',
                    'number_of_dependents' => 0,
                    'created_by' => $context->user->id,
                    'updated_by' => $context->user->id,
                ]);

                $user = User::query()->create([
                    'employee_id' => $employee->id,
                    'role_id' => $role->id,
                    'current_business_unit_id' => $businessUnitId,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'username' => $username,
                    'email' => $mapped['email'],
                    'phone' => $mapped['phone_number'],
                    'password' => $password,
                    'need_update_password' => $passwordGenerated,
                    'url_image' => config('app.url').'/assets/img/wms/avatar/user-default.jpg',
                    'created_by' => $context->user->id,
                    'updated_by' => $context->user->id,
                ]);

                if (strcasecmp((string) $role->name, 'Super Admin') === 0) {
                    $user->is_super_admin = true;
                    $user->save();
                }

                return $employee->fresh();
            });
        } catch (QueryException $e) {
            return [
                'success' => false,
                'message' => 'Gagal menyimpan karyawan. Periksa nama, email, atau kode yang sudah dipakai, lalu coba lagi dari chat.',
            ];
        }

        $item = $this->serialize($bundle);

        return [
            'success' => true,
            'applied' => true,
            'entity' => 'employee',
            'item' => $item,
            'items' => [$item],
            'password_generated' => $passwordGenerated,
            'message' => 'Karyawan "'.$item['label'].'" berhasil ditambahkan'
                .($item['role'] ? ' dengan role '.$item['role'] : '')
                .'. '
                .($passwordGenerated
                    ? 'Username '.$item['username'].'. Password sementara sudah dibuat; user diminta ganti saat login pertama.'
                    : 'Username '.$item['username'].'.'),
        ];
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    public function missing(array $missing, string $message): array
    {
        return [
            'success' => false,
            'missing' => array_values($missing),
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Employees $employee): array
    {
        $user = User::query()
            ->with('role')
            ->where('employee_id', $employee->id)
            ->first();

        $employee->loadMissing(['position', 'division']);

        $label = (string) $employee->fullname;
        $code = (string) $employee->employee_code;
        $roleName = $user?->role?->name;

        return [
            'id' => $employee->id,
            'fullname' => $label,
            'name' => $label,
            'label' => $label,
            'code' => $code,
            'employee_code' => $code,
            'email' => $employee->email,
            'username' => $user?->username,
            'role' => $roleName,
            'role_id' => $user?->role_id,
            'position' => $employee->position?->name,
            'division' => $employee->division?->name,
            'join_date' => optional($employee->join_date)?->toDateString(),
            'employee_status' => $employee->employee_status,
            'employment_status' => $employee->employment_status,
        ];
    }

    protected function findExisting(string $fullname, ?string $email): ?Employees
    {
        return Employees::query()
            ->where(function ($query) use ($fullname, $email) {
                $query->where('fullname', 'ilike', $fullname);
                if ($email !== null) {
                    $query->orWhere('email', 'ilike', $email);
                }
            })
            ->first();
    }

    protected function resolveRole(?string $roleId, ?string $roleName): ?Role
    {
        if ($roleId !== null) {
            return Role::query()->whereNull('deleted_at')->find($roleId);
        }

        if ($roleName !== null) {
            return $this->findByName(Role::query()->whereNull('deleted_at'), $roleName);
        }

        return $this->defaultRole();
    }

    protected function defaultRole(): ?Role
    {
        foreach (['Staff', 'Administrator', 'Manager'] as $name) {
            $role = Role::query()->whereNull('deleted_at')->where('name', 'ilike', $name)->first();
            if ($role !== null) {
                return $role;
            }
        }

        return Role::query()
            ->whereNull('deleted_at')
            ->where('name', 'not ilike', 'Super Admin')
            ->orderBy('name')
            ->first()
            ?? Role::query()->whereNull('deleted_at')->orderBy('name')->first();
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function resolveNamed(Builder $query, ?string $id, ?string $name): ?Model
    {
        if ($id !== null) {
            return $query->find($id);
        }

        if ($name === null) {
            return null;
        }

        return $this->findByName($query, $name);
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function findByName(Builder $query, string $name): ?Model
    {
        $exact = (clone $query)->where('name', 'ilike', $name)->first();
        if ($exact !== null) {
            return $exact;
        }

        $compact = $this->compact($name);

        return $query->get()->first(
            fn (Model $row) => $this->compact((string) $row->getAttribute('name')) === $compact
        );
    }

    protected function uniqueEmployeeCode(): string
    {
        $prefix = 'EMP-'.now()->format('Ymd').'-';
        $i = 1;

        do {
            $code = $prefix.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $i++;
        } while (Employees::withTrashed()->where('employee_code', $code)->exists());

        return $code;
    }

    protected function uniqueIdentityNumber(): string
    {
        do {
            $nik = 'NIK-CHAT-'.now()->format('Ymd').Str::upper(Str::random(6));
        } while (Employees::withTrashed()->where('identity_number', $nik)->exists());

        return $nik;
    }

    protected function uniqueUsername(string $base): string
    {
        $username = $base;
        $i = 2;

        while (User::withTrashed()->where('username', $username)->exists()) {
            $username = $base.$i;
            $i++;
        }

        return $username;
    }

    protected function usernameFromName(string $fullname): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '.', $fullname));
        $slug = trim($slug, '.');

        return $slug !== '' ? $slug : 'karyawan';
    }

    protected function compact(string $value): string
    {
        return (string) preg_replace('/\s+/u', '', mb_strtolower(trim($value)));
    }
}
