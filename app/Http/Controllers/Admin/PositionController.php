<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class PositionController extends Controller
{
    public function indexView(Request $request)
    {
        $status = "";
        if ($request->filled('status')) {
            $status = $request['status'];
        }

        $isFilter = false;
        if (($status != "")) {
            $isFilter = true;
        }

        return view('admin.human-resources.position.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $data = Position::select(
            'positions.id',
            'positions.name',
            'positions.created_at',
            'positions.deleted_at',
        );

        if ($request['status'] == "active") {
        } else if ($request['status'] == "deleted") {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('positions.created_at', 'DESC');

        $data->get();

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value']) {
                    $query->where(function ($q) use ($search) {
                        $q->where('positions.name', 'LIKE', "%{$search}%")
                            ->orWhere('positions.created_at', 'LIKE', "%{$search}%")
                            ->orWhere('positions.deleted_at', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        return view('admin.human-resources.position.insert');
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:positions,name',
        ], [
            'name.required' => 'Position name is required.',
            'name.string' => 'Position must be a string.',
            'name.max' => 'Position maximum 255 characters.',
            'name.unique' => 'Position name already exists, please use another name.',
        ]);

        //--------------- INSERT POSITION ---------------
        $position = Position::create([
            'name' => $request['name'],
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('position.index.view')->with('success', 'Successfully added position');
    }

    public function editView(Request $request, $id)
    {
        $position = Position::where('id', $id)
            ->withTrashed()
            ->first();

        return view('admin.human-resources.position.edit', compact('position'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|string|exists:positions,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'name')->ignore($request->id),
            ],
        ], [
            'id.required' => 'ID is required.',
            'id.string' => 'ID must be a string.',
            'id.exists' => 'Invalid ID or data not found.',

            'name.required' => 'Position name is required.',
            'name.string' => 'Position must be a string.',
            'name.max' => 'Position maximum 255 characters.',
            'name.unique' => 'Position name already exists, please use another name.',
        ]);

        $position = Position::where('id', $request['id'])
            ->withTrashed()
            ->first();

        $position->name = $request['name'];
        $position->updated_by = auth('web')->id();

        $position->save();

        return redirect()->route('position.index.view')->with('success', 'Successfully updated position');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'position_id_deleted' => 'required|string|exists:positions,id',
        ], [
            'position_id_deleted.required' => 'Position ID to be deleted is required.',
            'position_id_deleted.string' => 'Position ID must be a string.',
            'position_id_deleted.exists' => 'Invalid position ID or data not found.',
        ]);

        $position = Position::where('id', $request['position_id_deleted'])
            ->first();

        $position->updated_by = auth('web')->id();
        $position->deleted_by = auth('web')->id();

        $position->save();
        $position->delete();

        return redirect()->route('position.index.view')->with('success', 'Successfully deleted position');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'position_id_restored' => 'required|string|exists:positions,id',
        ], [
            'position_id_restored.required' => 'Position ID to be restored is required.',
            'position_id_restored.string' => 'Position ID must be a string.',
            'position_id_restored.exists' => 'Invalid position ID or data not found.',
        ]);

        $position = Position::where('id', $request['position_id_restored'])
            ->withTrashed()
            ->first();

        $position->updated_by = auth('web')->id();
        $position->deleted_by = null;

        $position->save();
        $position->restore();

        return redirect()->route('position.index.view')->with('success', 'Successfully restored position');
    }
}
