<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\User;
use App\Models\Employees;
use App\Services\SidebarService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function updateProfileView(): View
    {
        $user = User::where('id', auth()->user()->id)
            ->with('employee')
            ->first();

        return view('admin.profile.edit-profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:Laki-laki,Perempuan,Other',
            'address' => 'nullable|string',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
        ], [
            'first_name.required' => 'First name is required.',
            'first_name.string' => 'First name must be a valid string.',
            'first_name.max' => 'First name must not exceed 255 characters.',
            'last_name.string' => 'Last name must be a valid string.',
            'last_name.max' => 'Last name must not exceed 255 characters.',
            'gender.in' => 'Gender is invalid.',
            'email.email' => 'Email format is invalid.',
            'email.max' => 'Email must not exceed 255 characters.',
            'phone_number.max' => 'Phone number must not exceed 20 characters.',
        ]);

        $user = User::where('id', auth()->user()->id)
            ->first();

        $urlImage = $user->url_image;
        if ($request->hasFile('upload')) {
            $request->validate([
                'upload' => 'mimes:jpeg,jpg,png|required|max:1024'
            ], [
                'upload.mimes' => 'The uploaded file must be a file of type: jpeg, jpg, or png.',
                'upload.required' => 'File is required.',
                'upload.max' => 'The uploaded file must not exceed 1 MB.',
            ]);

            $path = $request->file('upload')->store("images/profile", 'public');

            $urlImage = config('app.url') . Storage::url($path);
        }

        // Update User
        $user->first_name = $request['first_name'];
        $user->last_name = $request['last_name'] ?? '';
        $user->url_image = $urlImage;
        $user->updated_by = auth('web')->id();
        $user->save();

        // Update Employee jika employee_id ada
        if ($user->employee_id) {
            $employee = Employees::where('id', $user->employee_id)->first();
            if ($employee) {
                $employee->update([
                    'fullname' => $request['first_name'] . ' ' . ($request['last_name'] ?? ''),
                    'gender' => $request['gender'],
                    'address' => $request['address'],
                    'phone_number' => $request['phone_number'],
                    'email' => $request['email'],
                    'updated_by' => auth('web')->id(),
                ]);
            }
        }

        return redirect()->route('profile.view')->with('success', 'Profile updated successfully');
    }

    public function changePasswordView(): View
    {
        return view('admin.profile.change-password-profile');
    }

    public function changePasswordProfile(Request $request)
    {
        $request->validate([
            'old_password' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:3',
                'confirmed'
            ],
        ], [
            'old_password.required' => 'Please enter your current password.',
            'new_password.required' => 'Please enter a new password.',
            'new_password.string' => 'The new password must be a valid string.',
            'new_password.min' => 'The new password must be at least 3 characters.',
            'new_password.confirmed' => 'The new password confirmation does not match.',
        ]);

        $user = User::where('id', auth()->user()->id)
            ->first();

        if (!Hash::check($request->old_password, $user->password)) {
            $error = \Illuminate\Validation\ValidationException::withMessages([
                'old_password' => ['Your current password is incorrect.'],
            ]);
            throw $error;
        }

        $user->password = Hash::make($request->get('new_password'));
        $user->updated_by = auth('web')->id();
        $user->save();

        return redirect()->route('profile.change-password-view')->with('success', 'Password changed successfully');
    }

    public function switchBranchView(): View
    {
        $user = User::findOrFail(auth('web')->id());
        $currentBranch = $user->businessUnit;

        $switchableIds = $user->getSwitchableBusinessUnitIds();

        $holding = null;
        $company = null;

        if ($currentBranch) {
            $companyId = match ($currentBranch->type_code) {
                'COMPANY' => $currentBranch->id,
                'BRANCH' => $currentBranch->parent_id,
                default => null,
            };

            if ($companyId) {
                $company = BusinessUnit::find($companyId);
                if ($company?->parent_id) {
                    $holding = BusinessUnit::find($company->parent_id);
                }
            } elseif ($currentBranch->type_code === 'HOLDING') {
                $holding = $currentBranch;
            }
        }

        $branches = BusinessUnit::whereIn('id', $switchableIds)
            ->where('type_code', 'BRANCH')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        return view('admin.profile.change-branch', compact('user', 'currentBranch', 'holding', 'company', 'branches'));
    }

    public function switchBranch(Request $request)
    {
        $request->validate([
            'business_unit_id' => 'required|exists:master_data.business_units,id',
        ]);

        $user = User::findOrFail(auth('web')->id());
        $targetUnit = BusinessUnit::findOrFail($request->business_unit_id);

        $accessibleIds = $user->getSwitchableBusinessUnitIds();
        if (!in_array($targetUnit->id, $accessibleIds)) {
            return redirect()->back()->with('error', 'Anda tidak memiliki akses ke lokasi tersebut.');
        }

        $user->current_business_unit_id = $targetUnit->id;
        $user->updated_by = $user->id;
        $user->save();

        SidebarService::loadSidebarsAndPermissions();

        return redirect()->route('dashboard')->with('success', 'Lokasi berhasil diubah ke ' . $targetUnit->name);
    }
}
