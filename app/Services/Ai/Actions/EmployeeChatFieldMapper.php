<?php

namespace App\Services\Ai\Actions;

/**
 * Normalisasi fields_json chat → kolom karyawan, tanpa akses database.
 *
 * Dipanggil dari EmployeeChatService::create(). Tidak ada file sejenis
 * (tidak ada EmployeeChat*). Tidak baca/tulis data file.
 *
 * Input chat (contoh sintetis): fullname "Budi Santoso", email "budi@example.test",
 * role "Staff", join_date "hari ini" → Y-m-d, status "aktif" → employee_status Active.
 */
class EmployeeChatFieldMapper
{
    /**
     * @param  array<string, mixed>  $arguments
     * @return array{
     *     fullname: string,
     *     email: ?string,
     *     username: ?string,
     *     password: ?string,
     *     role_name: ?string,
     *     role_id: ?string,
     *     position_name: ?string,
     *     position_id: ?string,
     *     division_name: ?string,
     *     division_id: ?string,
     *     join_date: ?string,
     *     employment_status: ?string,
     *     employee_status: ?string,
     *     phone_number: ?string,
     *     nickname: ?string,
     *     identity_number: ?string,
     *     employee_code: ?string,
     *     business_unit_id: ?string,
     *     missing: list<string>,
     *     message: ?string
     * }
     */
    public function map(array $arguments, string $today): array
    {
        $extra = $this->decodeFields($arguments);

        $fullname = $this->firstString($arguments['name'] ?? null, $extra, [
            'fullname', 'name', 'nama', 'nama_lengkap',
        ]);
        $email = $this->firstString(null, $extra, ['email']);
        $username = $this->firstString(null, $extra, ['username']);
        $password = $this->firstString(null, $extra, ['password']);
        $roleId = $this->firstString(null, $extra, ['role_id']);
        $roleName = $this->firstString(null, $extra, ['role', 'role_name', 'nama_role']);
        $positionId = $this->firstString(null, $extra, ['position_id']);
        $positionName = $this->firstString(null, $extra, ['position', 'position_name', 'jabatan']);
        $divisionId = $this->firstString(null, $extra, ['division_id']);
        $divisionName = $this->firstString(null, $extra, ['division', 'division_name', 'divisi']);
        $phone = $this->firstString(null, $extra, ['phone_number', 'phone', 'telepon', 'no_hp']);
        $nickname = $this->firstString(null, $extra, ['nickname', 'nama_panggilan']);
        $identity = $this->firstString($arguments['code'] ?? null, $extra, [
            'identity_number', 'nik', 'ktp',
        ]);
        $employeeCode = $this->firstString(null, $extra, ['employee_code', 'kode_karyawan', 'kode']);
        $businessUnitId = $this->firstString(null, $extra, [
            'business_unit_id', 'current_business_unit_id', 'branch_id', 'cabang_id',
        ]);

        $joinRaw = $this->firstString(null, $extra, [
            'join_date', 'tanggal_bergabung', 'tanggal_masuk', 'tanggal_join',
        ]);
        $joinDate = $this->parseJoinDate($joinRaw, $today);

        $employment = $this->parseEmploymentStatus($this->firstString(null, $extra, [
            'employment_status', 'status_kerja', 'status_kepegawaian',
        ]));
        $employeeStatus = $this->parseEmployeeStatus($this->firstString(null, $extra, [
            'employee_status', 'status_karyawan', 'status',
        ]));

        if ($username === null && $email !== null) {
            $username = $email;
        }

        $missing = [];
        $questions = [];

        if ($fullname === null) {
            $missing[] = 'fullname';
            $questions[] = 'Nama lengkap karyawan siapa yang mau ditambahkan?';
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $missing[] = 'email';
            $questions[] = 'Email "'.$email.'" tidak valid. Email yang benar apa?';
        }

        return [
            'fullname' => $fullname ?? '',
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'role_name' => $roleName,
            'role_id' => $roleId,
            'position_name' => $positionName,
            'position_id' => $positionId,
            'division_name' => $divisionName,
            'division_id' => $divisionId,
            'join_date' => $joinDate,
            'employment_status' => $employment,
            'employee_status' => $employeeStatus,
            'phone_number' => $phone,
            'nickname' => $nickname,
            'identity_number' => $identity,
            'employee_code' => $employeeCode,
            'business_unit_id' => $businessUnitId,
            'missing' => $missing,
            'message' => $questions === [] ? null : implode(' ', $questions),
        ];
    }

    public function parseJoinDate(?string $value, string $today): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        if (in_array($normalized, ['hari ini', 'today', 'sekarang', 'skrg'], true)) {
            return $today;
        }

        if (in_array($normalized, ['kemarin', 'yesterday'], true)) {
            return date('Y-m-d', strtotime($today.' -1 day') ?: strtotime('-1 day'));
        }

        $ts = strtotime($value);

        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    public function parseEmployeeStatus(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = $this->compact($value);

        return match (true) {
            in_array($key, ['aktif', 'active'], true) => 'Active',
            in_array($key, ['nonaktif', 'tidakaktif', 'inactive'], true) => 'Inactive',
            in_array($key, ['resign', 'resigned', 'keluar'], true) => 'Resigned',
            in_array($key, ['terminated', 'dipecat', 'phk'], true) => 'Terminated',
            in_array($value, ['Active', 'Inactive', 'Resigned', 'Terminated'], true) => $value,
            default => null,
        };
    }

    public function parseEmploymentStatus(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = $this->compact($value);

        return match (true) {
            in_array($key, ['tetap', 'permanen', 'permanent'], true) => 'Permanent',
            in_array($key, ['kontrak', 'contract'], true) => 'Contract',
            in_array($key, ['percobaan', 'probation'], true) => 'Probation',
            in_array($key, ['magang', 'intern'], true) => 'Intern',
            in_array($key, ['freelance', 'lepas'], true) => 'Freelance',
            in_array($value, ['Permanent', 'Contract', 'Probation', 'Intern', 'Freelance'], true) => $value,
            default => null,
        };
    }

    /**
     * @param  list<string>  $missing
     */
    public function questionFor(array $missing): string
    {
        $labels = [
            'fullname' => 'Nama lengkap karyawan siapa yang mau ditambahkan?',
            'role' => 'Role-nya apa? Misalnya Staff, Manager, atau Super Admin.',
            'position' => 'Jabatannya apa?',
            'division' => 'Divisinya apa?',
            'email' => 'Email karyawan siapa?',
        ];

        $parts = [];
        foreach ($missing as $field) {
            $parts[] = $labels[$field] ?? 'Nilai '.$field.' masih perlu dilengkapi.';
        }

        return $parts === [] ? 'Data karyawan belum lengkap.' : implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function decodeFields(array $arguments): array
    {
        $raw = $arguments['fields_json'] ?? null;

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  list<string>  $keys
     */
    protected function firstString(mixed $direct, array $extra, array $keys): ?string
    {
        if (is_string($direct) && trim($direct) !== '') {
            return trim($direct);
        }

        foreach ($keys as $key) {
            $value = $extra[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function compact(string $value): string
    {
        $lower = mb_strtolower(trim($value));

        return (string) preg_replace('/\s+/u', '', $lower);
    }
}
