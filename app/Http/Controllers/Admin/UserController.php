<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\City;
use App\Models\Division;
use App\Models\Employees;
use App\Models\Position;
use App\Models\Province;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;
use App\Services\SidebarService;

class UserController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->get('status', '');
        $branchId = $request->get('branch_id', auth('web')->user()->current_business_unit_id);

        $isFilter = $status !== '' || $branchId !== auth('web')->user()->current_business_unit_id
            || $request->filled('employment_status')
            || $request->filled('employee_status');

        return view('admin.human-resources.employee.index', compact('status', 'branchId', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $branchId = $request->get('branch_id');

        $query = User::select([
                'auth.users.id',
                'human_resources.employees.employee_code',
                'human_resources.employees.identity_number',
                'human_resources.employees.fullname',
                'auth.users.first_name',
                'auth.users.last_name',
                'auth.users.username',
                'auth.users.url_image',
                'master_data.positions.name as position',
                'human_resources.employees.employment_status',
                'human_resources.employees.employee_status',
                'auth.users.created_at',
                'auth.users.deleted_at'
            ])
            ->join('human_resources.employees', 'auth.users.employee_id', '=', 'human_resources.employees.id')
            ->leftJoin('master_data.positions', 'human_resources.employees.position_id', '=', 'master_data.positions.id')
            ->when($branchId, fn ($q) => $q->where('auth.users.current_business_unit_id', $branchId));

        // Handle soft deletes filtering
        if ($request['status'] == "deleted") {
            $query->whereNotNull('auth.users.deleted_at');
        } elseif ($request['status'] == "active") {
            $query->whereNull('auth.users.deleted_at');
        }

        $query->orderBy('auth.users.created_at', 'DESC');

        $dt = new DataTables();

        /** @var \Illuminate\Database\Eloquent\Builder $query */
        return $dt->eloquent($query)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value']) {
                    $query->where(function ($q) use ($search) {
                        $q->orWhere('human_resources.employees.employee_code', 'LIKE', "%{$search}%")
                            ->orWhere('human_resources.employees.identity_number', 'LIKE', "%{$search}%")
                            ->orWhere('human_resources.employees.fullname', 'LIKE', "%{$search}%")
                            ->orWhere('auth.users.username', 'LIKE', "%{$search}%")
                            ->orWhere('auth.users.created_at', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $positions = Position::whereNull('deleted_at')->get();
        $divisions = Division::whereNull('deleted_at')->get();
        $roles = Role::whereNull('deleted_at')->get();
        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        return view('admin.human-resources.employee.insert', compact('positions', 'divisions', 'roles', 'provinces'));
    }

    public function importView(Request $request)
    {
        return view('admin.human-resources.employee.import');
    }

    public function insertData(Request $request)
    {
        $request->validate([
            // === IDENTITAS KARYAWAN ===
            'employee_code' => 'required|string|max:50|unique:human_resources.employees,employee_code',
            'identity_number' => 'required|string|max:50|unique:human_resources.employees,identity_number',
            'fullname' => 'required|string|max:150',
            'nickname' => 'nullable|string|max:100',
            'gender' => 'nullable|in:Laki-laki,Perempuan,Other',
            'place_of_birth' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'religion' => 'nullable|string|max:50',
            'marital_status' => 'nullable|string|max:50',
            'number_of_dependents' => 'nullable|integer|min:0',

            // === KONTAK & ALAMAT ===
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',

            // === DATA KEPEGAWAIAN ===
            'position_id' => 'required|exists:master_data.positions,id',
            'division_id' => 'nullable|exists:master_data.divisions,id',
            'join_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:contract_start_date',
            'employment_status' => 'nullable|in:Permanent,Contract,Probation,Intern,Freelance',
            'employee_status' => 'nullable|in:Active,Inactive,Resigned,Terminated',

            // === PAYROLL & LEGAL ===
            'tax_number' => 'nullable|string|max:50',
            'bpjs_kesehatan' => 'nullable|string|max:50',
            'bpjs_ketenagakerjaan' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:150',

            // === EMERGENCY CONTACT ===
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_relation' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',

            // === ACCOUNT ===
            'username' => 'required|string|max:255|unique:auth.users,username',
            'password' => 'required|string|min:6|confirmed',
            'role_id' => 'required|exists:master_data.roles,id',

            // === BRANCH ASSIGNMENT ===
            'current_business_unit_id' => 'nullable|exists:master_data.business_units,id',
        ], [
            'employee_code.required' => 'Employee code is required.',
            'employee_code.unique' => 'Employee code already exists.',
            'identity_number.required' => 'Identity number is required.',
            'identity_number.unique' => 'Identity number already exists.',
            'fullname.required' => 'Full name is required.',
            'email.email' => 'Invalid email format.',
            'position_id.required' => 'Position is required.',
            'contract_end_date.after' => 'Contract end date must be after start date.',
            'username.required' => 'Username is required.',
            'username.unique' => 'Username already exists.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role_id.required' => 'Role is required.',
        ]);

        // Insert ke Tabel Employees
        $employee = Employees::create([
            // === RELASI ORGANISASI ===
            'position_id' => $request->position_id,
            'division_id' => $request->division_id,

            // === IDENTITAS KARYAWAN ===
            'employee_code' => $request->employee_code,
            'identity_number' => $request->identity_number,
            'fullname' => $request->fullname,
            'nickname' => $request->nickname,
            'gender' => $request->gender,
            'place_of_birth' => $request->place_of_birth,
            'date_of_birth' => $request->date_of_birth,
            'religion' => $request->religion,
            'marital_status' => $request->marital_status,
            'number_of_dependents' => $request->number_of_dependents ?? 0,

            // === KONTAK & ALAMAT ===
            'address' => $request->address,
            'city' => $request->city,
            'province' => $request->province,
            'postal_code' => $request->postal_code,
            'phone_number' => $request->phone_number,
            'email' => $request->email,

            // === DATA KEPEGAWAIAN ===
            'join_date' => $request->join_date,
            'contract_start_date' => $request->contract_start_date,
            'contract_end_date' => $request->contract_end_date,
            'employment_status' => $request->employment_status ?? 'Probation',
            'employee_status' => $request->employee_status ?? 'Active',

            // === PAYROLL & LEGAL ===
            'tax_number' => $request->tax_number,
            'bpjs_kesehatan' => $request->bpjs_kesehatan,
            'bpjs_ketenagakerjaan' => $request->bpjs_ketenagakerjaan,
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_account_name' => $request->bank_account_name,

            // === EMERGENCY CONTACT ===
            'emergency_contact_name' => $request->emergency_contact_name,
            'emergency_contact_relation' => $request->emergency_contact_relation,
            'emergency_contact_phone' => $request->emergency_contact_phone,

            // === AUDIT ===
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        // Split fullname for first_name and last_name
        $nameParts = explode(' ', $request->fullname, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        // Insert ke Tabel Users
        $user = User::create([
            'employee_id' => $employee->id,
            'role_id' => $request->role_id,
            'current_business_unit_id' => $request->current_business_unit_id ?: null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'url_image' => config('app.url') . '/assets/img/wms/avatar/user-default.jpg',
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('users.index.view')->with('success', 'Successfully added employee');
    }

    public function editView(Request $request, $id)
    {
        $user = User::where('id', $id)->first();

        if (!$user) {
            return redirect()->route('users.index.view')->with('warning', 'User not found');
        }

        $employee = Employees::where('id', $user->employee_id)->first();
        $positions = Position::whereNull('deleted_at')->get();
        $divisions = Division::whereNull('deleted_at')->get();
        $roles = Role::whereNull('deleted_at')->get();

        $selectedProvinceId = null;
        $selectedCityId = null;
        if ($employee && $employee->province) {
            // Cek apakah province tersimpan sebagai UUID atau nama
            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-/i', $employee->province)) {
                $prov = Province::find($employee->province);
                if ($prov) {
                    $selectedProvinceId = $prov->id;
                    $employee->province = $prov->name; // Resolve ke nama untuk tampilan
                }
            } else {
                $prov = Province::whereNull('deleted_at')->where('name', $employee->province)->first();
                if ($prov) {
                    $selectedProvinceId = $prov->id;
                }
            }

            if ($prov && $employee->city) {
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-/i', $employee->city)) {
                    $city = City::find($employee->city);
                    if ($city) {
                        $selectedCityId = $city->id;
                        $employee->city = $city->name; // Resolve ke nama untuk tampilan
                    }
                } else {
                    $city = City::whereNull('deleted_at')->where('province_id', $prov->id)->where('name', $employee->city)->first();
                    if ($city) {
                        $selectedCityId = $city->id;
                    }
                }
            }
        }

        $provinces = Province::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']);

        $selectedHoldingId = null;
        $selectedCompanyId = null;
        $selectedBranchId = null;
        if ($user->current_business_unit_id) {
            $branch = BusinessUnit::find($user->current_business_unit_id);
            if ($branch && $branch->type_code === 'BRANCH') {
                $selectedBranchId = $branch->id;
                $company = BusinessUnit::find($branch->parent_id);
                if ($company && $company->type_code === 'COMPANY') {
                    $selectedCompanyId = $company->id;
                    $selectedHoldingId = $company->parent_id;
                }
            }
        }

        return view('admin.human-resources.employee.edit', compact(
            'user', 'employee', 'positions', 'divisions', 'roles',
            'provinces', 'selectedProvinceId', 'selectedCityId',
            'selectedHoldingId', 'selectedCompanyId', 'selectedBranchId'
        ));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|string|exists:auth.users,id',
            // === IDENTITAS KARYAWAN ===
            'employee_code' => 'required|string|max:50|unique:human_resources.employees,employee_code,' . $request->employee_id . ',id',
            'identity_number' => 'required|string|max:50|unique:human_resources.employees,identity_number,' . $request->employee_id . ',id',
            'fullname' => 'required|string|max:150',
            'nickname' => 'nullable|string|max:100',
            'gender' => 'nullable|in:Laki-laki,Perempuan,Other',
            'place_of_birth' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'religion' => 'nullable|string|max:50',
            'marital_status' => 'nullable|string|max:50',
            'number_of_dependents' => 'nullable|integer|min:0',

            // === KONTAK & ALAMAT ===
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'phone_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:150',

            // === DATA KEPEGAWAIAN ===
            'position_id' => 'required|exists:master_data.positions,id',
            'division_id' => 'nullable|exists:master_data.divisions,id',
            'join_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:contract_start_date',
            'employment_status' => 'nullable|in:Permanent,Contract,Probation,Intern,Freelance',
            'employee_status' => 'nullable|in:Active,Inactive,Resigned,Terminated',

            // === PAYROLL & LEGAL ===
            'tax_number' => 'nullable|string|max:50',
            'bpjs_kesehatan' => 'nullable|string|max:50',
            'bpjs_ketenagakerjaan' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:100',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_account_name' => 'nullable|string|max:150',

            // === EMERGENCY CONTACT ===
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_relation' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:50',

            // === ACCOUNT ===
            'username' => 'required|string|max:255|unique:auth.users,username,' . $request->id . ',id',
            'role_id' => 'required|exists:master_data.roles,id',

            // === BRANCH ASSIGNMENT ===
            'current_business_unit_id' => 'nullable|exists:master_data.business_units,id',
        ], [
            'employee_code.required' => 'Employee code is required.',
            'employee_code.unique' => 'Employee code already exists.',
            'identity_number.required' => 'Identity number is required.',
            'identity_number.unique' => 'Identity number already exists.',
            'fullname.required' => 'Full name is required.',
            'email.email' => 'Invalid email format.',
            'position_id.required' => 'Position is required.',
            'contract_end_date.after' => 'Contract end date must be after start date.',
            'username.required' => 'Username is required.',
            'username.unique' => 'Username already exists.',
            'role_id.required' => 'Role is required.',
        ]);

        $user = User::where('id', $request->id)->first();
        $employee = Employees::where('id', $user->employee_id)->first();

        // Update Employee
        $employee->update([
            // === RELASI ORGANISASI ===
            'position_id' => $request->position_id,
            'division_id' => $request->division_id,

            // === IDENTITAS KARYAWAN ===
            'employee_code' => $request->employee_code,
            'identity_number' => $request->identity_number,
            'fullname' => $request->fullname,
            'nickname' => $request->nickname,
            'gender' => $request->gender,
            'place_of_birth' => $request->place_of_birth,
            'date_of_birth' => $request->date_of_birth,
            'religion' => $request->religion,
            'marital_status' => $request->marital_status,
            'number_of_dependents' => $request->number_of_dependents ?? 0,

            // === KONTAK & ALAMAT ===
            'address' => $request->address,
            'city' => $request->city,
            'province' => $request->province,
            'postal_code' => $request->postal_code,
            'phone_number' => $request->phone_number,
            'email' => $request->email,

            // === DATA KEPEGAWAIAN ===
            'join_date' => $request->join_date,
            'contract_start_date' => $request->contract_start_date,
            'contract_end_date' => $request->contract_end_date,
            'employment_status' => $request->employment_status,
            'employee_status' => $request->employee_status,

            // === PAYROLL & LEGAL ===
            'tax_number' => $request->tax_number,
            'bpjs_kesehatan' => $request->bpjs_kesehatan,
            'bpjs_ketenagakerjaan' => $request->bpjs_ketenagakerjaan,
            'bank_name' => $request->bank_name,
            'bank_account_number' => $request->bank_account_number,
            'bank_account_name' => $request->bank_account_name,

            // === EMERGENCY CONTACT ===
            'emergency_contact_name' => $request->emergency_contact_name,
            'emergency_contact_relation' => $request->emergency_contact_relation,
            'emergency_contact_phone' => $request->emergency_contact_phone,

            // === AUDIT ===
            'updated_by' => auth('web')->id(),
        ]);

        // Split fullname for first_name and last_name
        $nameParts = explode(' ', $request->fullname, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        // Update User
        $userData = [
            'role_id' => $request->role_id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $request->username,
            'current_business_unit_id' => $request->current_business_unit_id ?: null,
            'updated_by' => auth('web')->id(),
        ];

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        return redirect()->route('users.index.view')->with('success', 'Successfully updated employee');
    }

    public function detailView(Request $request, $id)
    {
        $user = User::where('id', $id)->with(['role', 'employee.position', 'employee.division', 'employee.businessUnit'])->first();

        if (!$user) {
            return redirect()->route('users.index.view')->with('warning', 'User not found');
        }

        $employee = Employees::where('id', $user->employee_id)->first();
        $positions = Position::whereNull('deleted_at')->get();
        $divisions = Division::whereNull('deleted_at')->get();
        $roles = Role::whereNull('deleted_at')->get();

        // Resolve province & city: jika tersimpan UUID, ubah ke nama
        if ($employee) {
            if ($employee->province && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-/i', $employee->province)) {
                $prov = Province::find($employee->province);
                $employee->province = $prov ? $prov->name : $employee->province;
            }
            if ($employee->city && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-/i', $employee->city)) {
                $city = City::find($employee->city);
                $employee->city = $city ? $city->name : $employee->city;
            }
        }

        $branchName = null;
        $companyName = null;
        $holdingName = null;
        if ($user->current_business_unit_id) {
            $branch = BusinessUnit::find($user->current_business_unit_id);
            if ($branch && $branch->type_code === 'BRANCH') {
                $branchName = $branch->name;
                $company = BusinessUnit::find($branch->parent_id);
                if ($company && $company->type_code === 'COMPANY') {
                    $companyName = $company->name;
                    $holding = BusinessUnit::find($company->parent_id);
                    if ($holding) {
                        $holdingName = $holding->name;
                    }
                }
            }
        }

        return view('admin.human-resources.employee.detail', compact(
            'user', 'employee', 'positions', 'divisions', 'roles',
            'holdingName', 'companyName', 'branchName'
        ));
    }


    public function deleteData(Request $request)
    {
        $request->validate([
            'user_id_deleted' => 'required|string|exists:auth.users,id',
        ], [
            'user_id_deleted.required' => 'User ID to be deleted is required.',
            'user_id_deleted.string' => 'User ID must be a string.',
            'user_id_deleted.exists' => 'Invalid user ID or user not found.',
        ]);

        $user = User::where('id', $request['user_id_deleted'])
            ->first();

        $user->updated_by = auth('web')->id();
        $user->deleted_by = auth('web')->id();

        $user->save();
        $user->delete();

        return redirect()->route('users.index.view')->with('success', 'Successfully deleted user');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'user_id_restored' => 'required|string|exists:auth.users,id',
        ], [
            'user_id_restored.required' => 'User ID to be restored is required.',
            'user_id_restored.string' => 'User ID must be a string.',
            'user_id_restored.exists' => 'Invalid user ID or user not found.',
        ]);

        $user = User::where('id', $request['user_id_restored'])
            ->withTrashed()
            ->first();

        $user->updated_by = auth('web')->id();
        $user->deleted_by = null;

        $user->save();
        $user->restore();

        return redirect()->route('users.index.view')->with('success', 'Successfully restored user');
    }

    public function loginAs(Request $request)
    {
        $currentUser = auth('web')->user();

        $isSuperAdmin = $currentUser->is_super_admin || $currentUser->role_id === '147c8a8e-52dc-4a79-a8ce-acb612b6e484';
        if (!$isSuperAdmin) {
            return redirect()->route('users.index.view')->with('warning', 'Anda tidak memiliki akses untuk fitur ini.');
        }

        $request->validate([
            'user_id' => 'required|exists:auth.users,id',
        ]);

        $targetUser = User::findOrFail($request->user_id);

        if ($targetUser->id === $currentUser->id) {
            return redirect()->route('users.index.view')->with('warning', 'Anda sudah login sebagai user ini.');
        }

        session(['impersonator_id' => $currentUser->id]);

        Auth::guard('web')->login($targetUser);
        SidebarService::loadSidebarsAndPermissions();

        return redirect()->route('dashboard')->with('success', 'Login sebagai ' . $targetUser->first_name . ' ' . $targetUser->last_name);
    }

    public function stopImpersonation(Request $request)
    {
        $impersonatorId = session('impersonator_id');

        if (!$impersonatorId) {
            return redirect()->route('dashboard')->with('warning', 'Tidak ada sesi impersonasi aktif.');
        }

        $originalUser = User::findOrFail($impersonatorId);

        session()->forget('impersonator_id');

        Auth::guard('web')->login($originalUser);
        SidebarService::loadSidebarsAndPermissions();

        return redirect()->route('users.index.view')->with('success', 'Kembali ke akun Anda.');
    }
}
