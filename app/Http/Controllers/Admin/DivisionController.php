<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class DivisionController extends Controller
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

        return view('admin.human-resources.division.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $data = Division::select(
            'divisions.id',
            'divisions.name',
            'divisions.created_at',
            'divisions.deleted_at',
        );

        if ($request['status'] == "active") {
        } else if ($request['status'] == "deleted") {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('divisions.created_at', 'DESC');

        $data->get();

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value']) {
                    $query->where(function ($q) use ($search) {
                        $q->where('divisions.name', 'LIKE', "%{$search}%")
                            ->orWhere('divisions.created_at', 'LIKE', "%{$search}%")
                            ->orWhere('divisions.deleted_at', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        return view('admin.human-resources.division.insert');
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:divisions,name',
        ], [
            'name.required' => 'Division name is required.',
            'name.string' => 'Division must be a string.',
            'name.max' => 'Division maximum 255 characters.',
            'name.unique' => 'Division name already exists, please use another name.',
        ]);

        //--------------- INSERT DIVISION ---------------
        $division = Division::create([
            'name' => $request['name'],
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('division.index.view')->with('success', 'Successfully added division');
    }

    public function editView(Request $request, $id)
    {
        $division = Division::where('id', $id)
            ->withTrashed()
            ->first();

        return view('admin.human-resources.division.edit', compact('division'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|string|exists:divisions,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('divisions', 'name')->ignore($request->id),
            ],
        ], [
            'id.required' => 'ID is required.',
            'id.string' => 'ID must be a string.',
            'id.exists' => 'Invalid ID or data not found.',

            'name.required' => 'Division name is required.',
            'name.string' => 'Division must be a string.',
            'name.max' => 'Division maximum 255 characters.',
            'name.unique' => 'Division name already exists, please use another name.',
        ]);

        $division = Division::where('id', $request['id'])
            ->withTrashed()
            ->first();

        $division->name = $request['name'];
        $division->updated_by = auth('web')->id();

        $division->save();

        return redirect()->route('division.index.view')->with('success', 'Successfully updated division');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'division_id_deleted' => 'required|string|exists:divisions,id',
        ], [
            'division_id_deleted.required' => 'Division ID to be deleted is required.',
            'division_id_deleted.string' => 'Division ID must be a string.',
            'division_id_deleted.exists' => 'Invalid division ID or data not found.',
        ]);

        $division = Division::where('id', $request['division_id_deleted'])
            ->first();

        $division->updated_by = auth('web')->id();
        $division->deleted_by = auth('web')->id();

        $division->save();
        $division->delete();

        return redirect()->route('division.index.view')->with('success', 'Successfully deleted division');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'division_id_restored' => 'required|string|exists:divisions,id',
        ], [
            'division_id_restored.required' => 'Division ID to be restored is required.',
            'division_id_restored.string' => 'Division ID must be a string.',
            'division_id_restored.exists' => 'Invalid division ID or data not found.',
        ]);

        $division = Division::where('id', $request['division_id_restored'])
            ->withTrashed()
            ->first();

        $division->updated_by = auth('web')->id();
        $division->deleted_by = null;

        $division->save();
        $division->restore();

        return redirect()->route('division.index.view')->with('success', 'Successfully restored division');
    }
}
