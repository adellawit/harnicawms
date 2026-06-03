<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Services\SidebarService;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\IamAccess;
use App\Models\IamHasAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    public function indexView(Request $request)
    {
        // Load sidebar menus and permissions
        SidebarService::loadSidebarsAndPermissions();

        $status = "";
        if ($request->filled('status')) {
            $status = $request['status'];
        }

        $isFilter = false;
        if (($status != "")) {
            $isFilter = true;
        }

        return view('admin.access-management.role.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $data = Role::select(
            'roles.id',
            'roles.name',
            'roles.created_by',
            'roles.deleted_by'
        );

        if ($request['status'] == "active") {
        } else if ($request['status'] == "deleted") {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('roles.name', 'ASC');

        $data->get();

        $dt = new DataTables();

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value']) {
                    $query->where(function ($q) use ($search) {
                        $q->where('roles.id', 'LIKE', "%{$search}%")
                            ->orWhere('roles.name', 'LIKE', "%{$search}%")
                            ->orWhere('roles.created_by', 'LIKE', "%{$search}%")
                            ->orWhere('roles.deleted_by', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        $parentMenus = Menu::whereNull('parent_id')
            ->orWhere('level_sidebar', 1)
            ->with([
                'children' => fn ($q) => $q->orderBy('order_number'),
                'children.children' => fn ($q) => $q->orderBy('order_number'),
            ])
            ->orderBy('order_number')
            ->get();

        return view('admin.access-management.role.insert', compact('parentMenus'));
    }


    public function insertData(Request $request)
    {
        // Validate input
        $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
        ], [
            'name.required' => 'Role name is required.',
            'name.string' => 'Role name must be a string.',
            'name.max' => 'Role name maximum 255 characters.',
            'name.unique' => 'This role name is already registered.',
        ]);

        // Insert Role
        $role = Role::create([
            'name' => $request['name'],
            'created_by' => auth('web')->id(),
        ]);

        $iamAccess = IamAccess::create([
            'role_id' => $role->id,
            'is_notification' => false,
            'created_by' => auth('web')->id(),
        ]);

        $permissions = $request->input('permissions', []);
        foreach ($permissions as $group => $actions) {
            foreach ($actions as $sidebar_menu_id => $crud) {
                $id = $sidebar_menu_id;
                IamHasAccess::create([
                    'iam_access_id' => $iamAccess->id,
                    'sidebar_menu_id' => $id,
                    'is_create' => isset($crud['create']) ? 1 : 0,
                    'is_read' => isset($crud['read']) ? 1 : 0,
                    'is_update' => isset($crud['update']) ? 1 : 0,
                    'is_delete' => isset($crud['delete']) ? 1 : 0,
                    'is_custom_1' => isset($crud['custom1']) ? 1 : 0,
                    'is_custom_2' => isset($crud['custom2']) ? 1 : 0,
                    'is_custom_3' => isset($crud['custom3']) ? 1 : 0,
                    'is_custom_4' => isset($crud['custom4']) ? 1 : 0,
                    'is_custom_5' => isset($crud['custom5']) ? 1 : 0,
                    'created_by' => auth('web')->id(),
                ]);
            }
        }

        return redirect()->route('roles.index.view')->with('success', 'Successfully added role.');
    }

    public function editView($roleId)
    {
        Log::info($roleId);

        // Fetch the role data along with the related 'iamAccess' and 'iamHasAccess' for sidebar menu permissions
        $role = Role::with('iamAccess.iamHasAccess.sidebarMenu')->findOrFail($roleId);

        // Check if iamAccess exists
        if (!$role->iamAccess) {
            // If no iamAccess exists, create one
            $iamAccess = IamAccess::create([
                'role_id' => $role->id,
                'is_notification' => false,
                'created_by' => auth('web')->id(),
            ]);
            $role->load('iamAccess.iamHasAccess.sidebarMenu');
        }

        $parentMenus = Menu::whereNull('parent_id')
            ->orWhere('level_sidebar', 1)
            ->with([
                'children' => fn ($q) => $q->orderBy('order_number'),
                'children.children' => fn ($q) => $q->orderBy('order_number'),
            ])
            ->orderBy('order_number')
            ->get();

        // Get role permissions keyed by sidebar_menu_id
        $rolePermissions = collect([]);
        if ($role->iamAccess && $role->iamAccess->iamHasAccess) {
            $rolePermissions = $role->iamAccess->iamHasAccess->keyBy('sidebar_menu_id');
        }

        return view('admin.access-management.role.edit', compact('role', 'parentMenus', 'rolePermissions'));
    }

    public function editData(Request $request)
    {
        // Validate the name field
        $request->validate([
            'id' => 'required|string|exists:roles,id',
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
        ], [
            'id.required' => 'Role ID is required.',
            'id.exists' => 'Role not found.',
            'name.required' => 'Role name is required.',
            'name.string' => 'Role name must be a string.',
            'name.max' => 'Role name maximum 255 characters.',
            'permissions.array' => 'Permissions must be an array.',
        ]);

        // Find the role and associated IamAccess
        $role = Role::findOrFail($request['id']);
        $iamAccess = $role->iamAccess; // IamAccess related to the role

        // If iamAccess doesn't exist, create one
        if (!$iamAccess) {
            $iamAccess = IamAccess::create([
                'role_id' => $role->id,
                'is_notification' => false,
                'created_by' => auth('web')->id(),
            ]);
        }

        // Update the role name and other details
        $role->name = $request['name'];
        $role->updated_by = auth('web')->id();
        $role->save();

        // Delete existing permissions
        IamHasAccess::where('iam_access_id', $iamAccess->id)->forceDelete();


        // Update IamHasAccess permissions for the role
        if ($request->has('permissions') && is_array($request['permissions'])) {
            foreach ($request['permissions'] as $menuId => $permissions) {
                $id = $menuId;
                IamHasAccess::create([
                    'iam_access_id' => $iamAccess->id,
                    'sidebar_menu_id' => $id,
                    'is_create' => isset($permissions['create']) ? (int)$permissions['create'] : 0,
                    'is_read' => isset($permissions['read']) ? (int)$permissions['read'] : 0,
                    'is_update' => isset($permissions['update']) ? (int)$permissions['update'] : 0,
                    'is_delete' => isset($permissions['delete']) ? (int)$permissions['delete'] : 0,
                    'is_custom_1' => isset($permissions['custom1']) ? (int)$permissions['custom1'] : 0,
                    'is_custom_2' => isset($permissions['custom2']) ? (int)$permissions['custom2'] : 0,
                    'is_custom_3' => isset($permissions['custom3']) ? (int)$permissions['custom3'] : 0,
                    'is_custom_4' => isset($permissions['custom4']) ? (int)$permissions['custom4'] : 0,
                    'is_custom_5' => isset($permissions['custom5']) ? (int)$permissions['custom5'] : 0,
                    'created_by' => auth('web')->id(),
                ]);
            }
        }

        // Redirect back to the roles index with a success message
        return redirect()->route('roles.index.view')->with('success', 'Successfully updated role');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'role_id_deleted' => 'required|string|exists:roles,id',
        ], [
            'role_id_deleted.required' => 'Role ID to be deleted is required.',
            'role_id_deleted.string' => 'Role ID must be a string.',
            'role_id_deleted.exists' => 'Role ID is invalid or not found.',
        ]);

        $role = Role::where('id', $request['role_id_deleted'])
            ->first();

        $role->updated_by = auth('web')->id();
        $role->deleted_by = auth('web')->id();

        $role->save();
        $role->delete();

        return redirect()->route('roles.index.view')->with('success', 'Successfully deleted role');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'role_id_restored' => 'required|string|exists:roles,id',
        ], [
            'role_id_restored.required' => 'Role ID to be restored is required.',
            'role_id_restored.string' => 'Role ID must be a string.',
            'role_id_restored.exists' => 'Role ID is invalid or not found.',
        ]);

        $role = Role::where('id', $request['role_id_restored'])
            ->withTrashed()
            ->first();

        $role->updated_by = auth('web')->id();
        $role->deleted_by = null;

        $role->save();
        $role->restore();

        return redirect()->route('roles.index.view')->with('success', 'Successfully restored role');
    }
}
