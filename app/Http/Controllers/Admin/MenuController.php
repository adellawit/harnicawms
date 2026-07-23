<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderMenuRequest;
use App\Models\Menu;
use App\Services\MenuOrderingService;
use App\Services\SidebarService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MenuController extends Controller
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

        // Get parent menus with their children and grandchildren for hierarchical display
        $parentMenus = Menu::whereNull('parent_id')
            ->orWhere('level_sidebar', 1)
            ->when($status === 'deleted', function ($query) {
                return $query->onlyTrashed();
            })
            ->when($status === 'active', function ($query) {
                return $query;
            })
            ->when($status === '', function ($query) {
                return $query->withTrashed();
            })
            ->with([
                'children' => function ($query) use ($status) {
                    $query->when($status === 'deleted', function ($q) {
                        return $q->onlyTrashed();
                    })
                    ->when($status === '', function ($q) {
                        return $q->withTrashed();
                    })
                    ->orderBy('order_number');
                },
                'children.children' => function ($query) use ($status) {
                    $query->when($status === 'deleted', function ($q) {
                        return $q->onlyTrashed();
                    })
                    ->when($status === '', function ($q) {
                        return $q->withTrashed();
                    })
                    ->orderBy('order_number');
                },
            ])
            ->orderBy('order_number')
            ->get();

        return view('admin.access-management.menu.index', compact('status', 'isFilter', 'parentMenus'));
    }

    public function indexData(Request $request)
    {
        $data = Menu::select(
            'menus.id',
            'menus.parent_id',
            'menus.name',
            'menus.code',
            'menus.text_sidebar',
            'menus.icon',
            'menus.has_page',
            'menus.url_path',
            'menus.slug',
            'menus.level_sidebar',
            'menus.order_number',
            'menus.created_by',
            'menus.updated_by',
            'menus.deleted_by',
            'menus.is_label',
            'menus.has_create',
            'menus.has_update',
            'menus.has_read',
            'menus.has_delete',
            'menus.has_custom1',
            'menus.has_custom2',
            'menus.has_custom3',
            'menus.has_custom4',
            'menus.has_custom5'
        );



        // Handle filtering based on status
        if ($request->status === "active") {
            // Active records only
        } elseif ($request->status === "deleted") {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        // Order results by `order_number`
        $data = $data->orderBy('menus.order_number', 'ASC');

        // Fetch data
        $data->get();

        // Use DataTables for JSON response
        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value']) {
                    $query->where(function ($q) use ($search) {
                        $q->where('menus.id', 'LIKE', "%{$search}%")
                            ->orWhere('menus.name', 'LIKE', "%{$search}%")
                            ->orWhere('menus.code', 'LIKE', "%{$search}%")
                            ->orWhere('menus.text_sidebar', 'LIKE', "%{$search}%")
                            ->orWhere('menus.created_by', 'LIKE', "%{$search}%")
                            ->orWhere('menus.updated_by', 'LIKE', "%{$search}%")
                            ->orWhere('menus.deleted_by', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function reorder(ReorderMenuRequest $request, MenuOrderingService $orderingService)
    {
        $positions = $orderingService->reorder(
            $request->validated('menus'),
            auth('web')->id()
        );

        SidebarService::loadSidebarsAndPermissions();

        return response()->json([
            'message' => 'Menu order saved successfully.',
            'positions' => $positions,
        ]);
    }

    public function insertView(Request $request)
    {
        $parentMenus = Menu::where('has_page', true)
            ->whereIn('level_sidebar', [1, 2])
            ->orderBy('level_sidebar')
            ->orderBy('order_number')
            ->get(['id', 'name', 'level_sidebar', 'parent_id']);

        return view('admin.access-management.menu.insert', compact('parentMenus'));
    }

    public function insertData(Request $request)
    {
        // Validate input
        $request->validate([
            'name' => 'required|string|max:255|unique:menus,name',
            'code' => 'required|string|max:255',
            'text_sidebar' => 'required|string|max:255',
            'level_sidebar' => 'required|integer',
            'order_number' => 'nullable|integer',
            'url_path' => 'nullable|string',
            'slug' => 'nullable|string',
            'parent_id' => 'nullable|exists:menus,id',
        ], [
            'name.required' => 'Menu name is required.',
            'name.string' => 'Menu name must be a string.',
            'name.max' => 'Menu name maximum 255 characters.',
            'name.unique' => 'This menu name is already registered.',
            'code.required' => 'Menu code is required.',
            'text_sidebar.required' => 'Sidebar text is required.',
            'level_sidebar.required' => 'Sidebar level is required.',
            'level_sidebar.integer' => 'Sidebar level must be a number.',
            'order_number.integer' => 'Order number must be a number.',
            'parent_id.exists' => 'Selected parent menu is invalid.',
        ]);
        // Pastikan checkbox defaultnya false jika tidak dicentang
        $data['has_create'] = $request->has('has_create') ? true : false;
        $data['has_update'] = $request->has('has_update') ? true : false;
        $data['has_read'] = $request->has('has_read') ? true : false;
        $data['has_delete'] = $request->has('has_delete') ? true : false;
        $data['has_custom1'] = $request->has('has_custom1') ? true : false;
        $data['has_custom2'] = $request->has('has_custom2') ? true : false;
        $data['has_custom3'] = $request->has('has_custom3') ? true : false;
        $data['has_custom4'] = $request->has('has_custom4') ? true : false;
        $data['has_custom5'] = $request->has('has_custom5') ? true : false;

        // Insert Sidebar Menu
        $data = [
            'parent_id' => $request->input('parent_id', null),
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'text_sidebar' => $request->input('text_sidebar'),
            'icon' => $request->input('icon', null),
            'has_page' => $request->boolean('has_page'),
            'url_path' => $request->input('url_path', null),
            'slug' => $request->input('slug', null),
            'level_sidebar' => $request->input('level_sidebar'),
            'order_number' => $request->input('order_number', null),
            'created_by' => auth('web')->id(),
            'has_create' => $request->boolean('has_create'),
            'has_update' => $request->boolean('has_update'),
            'has_read' => $request->boolean('has_read'),
            'has_delete' => $request->boolean('has_delete'),
            'has_custom1' => $request->boolean('has_custom1'),
            'has_custom2' => $request->boolean('has_custom2'),
            'has_custom3' => $request->boolean('has_custom3'),
            'has_custom4' => $request->boolean('has_custom4'),
            'has_custom5' => $request->boolean('has_custom5'),
        ];

        // Insert Sidebar Menu
        Menu::create($data);

        return redirect()->route('menu.index.view')->with('success', 'Successfully added menu.');
    }
    public function editView(Request $request, $id)
    {
        $menu = Menu::where('id', $id)
            ->withTrashed()
            ->first();

        $parentMenus = Menu::where('has_page', true)
            ->whereIn('level_sidebar', [1, 2])
            ->orderBy('level_sidebar')
            ->orderBy('order_number')
            ->get(['id', 'name', 'level_sidebar', 'parent_id']);

        return view('admin.access-management.menu.edit', compact('menu', 'parentMenus'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('menus', 'name')->ignore($request['id']),
            ],
            'code' => 'required|string|max:255',
            'text_sidebar' => 'required|string|max:255',
            'level_sidebar' => 'required|integer',
            'order_number' => 'nullable|integer',
            'url_path' => 'nullable|string',
            'slug' => 'nullable|string',
            'parent_id' => 'nullable|exists:menus,id',
            'has_page' => 'required|in:0,1',
        ], [
            'name.required' => 'Menu name is required.',
            'name.string' => 'Menu name must be a string.',
            'name.max' => 'Menu name maximum 255 characters.',
            'name.unique' => 'This menu name is already registered.',
            'code.required' => 'Menu code is required.',
            'text_sidebar.required' => 'Sidebar text is required.',
            'level_sidebar.required' => 'Sidebar level is required.',
            'level_sidebar.integer' => 'Sidebar level must be a number.',
            'order_number.integer' => 'Order number must be a number.',
            'parent_id.exists' => 'Selected parent menu is invalid.',
            'has_page.in' => 'Has Page value is invalid.',
        ]);
        $data = [
            'has_create' => $request->has('has_create'),
            'has_update' => $request->has('has_update'),
            'has_read' => $request->has('has_read'),
            'has_delete' => $request->has('has_delete'),
            'has_custom1' => $request->has('has_custom1'),
            'has_custom2' => $request->has('has_custom2'),
            'has_custom3' => $request->has('has_custom3'),
            'has_custom4' => $request->has('has_custom4'),
            'has_custom5' => $request->has('has_custom5'),
        ];

        $menu = Menu::where('id', $request['id'])
            ->withTrashed()
            ->first();

        if ($menu->name !== $request['name']) {
            $menu->name = $request['name'];
        }
        \Log::info($request['has_page']);
        $menu->parent_id = $request['parent_id'];
        $menu->code = $request['code'];
        $menu->text_sidebar = $request['text_sidebar'];
        $menu->icon = $request['icon'];
        $menu->url_path = $request['url_path'];
        $menu->slug = $request['slug'];
        $menu->level_sidebar = $request['level_sidebar'];
        $menu->order_number = $request['order_number'];
        $menu->has_page = $request['has_page'];
        $menu->updated_by = auth('web')->id();

        // Set checkbox values
        foreach ($data as $key => $value) {
            $menu->$key = $value;
        }

        $menu->save();

        return redirect()->route('menu.index.view')->with('success', 'Successfully updated menu');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'menu_id_deleted' => 'required|string|exists:menus,id',
        ], [
            'menu_id_deleted.required' => 'Menu ID to be deleted is required.',
            'menu_id_deleted.string' => 'Menu ID must be a string.',
            'menu_id_deleted.exists' => 'Menu ID is invalid or not found.',
        ]);

        $menu = Menu::where('id', $request['menu_id_deleted'])
            ->first();

        $menu->updated_by = auth('web')->id();
        $menu->deleted_by = auth('web')->id();

        $menu->save();
        $menu->delete();

        return redirect()->route('menu.index.view')->with('success', 'Successfully deleted menu');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'menu_id_restored' => 'required|string|exists:menus,id',
        ], [
            'menu_id_restored.required' => 'Menu ID to be restored is required.',
            'menu_id_restored.string' => 'Menu ID must be a string.',
            'menu_id_restored.exists' => 'Menu ID is invalid or not found.',
        ]);

        $menu = Menu::where('id', $request['menu_id_restored'])
            ->withTrashed()
            ->first();

        $menu->updated_by = auth('web')->id();
        $menu->deleted_by = null;

        $menu->save();
        $menu->restore();

        return redirect()->route('menu.index.view')->with('success', 'Successfully restored menu');
    }
}
