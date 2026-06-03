<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class ParameterController extends Controller
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

        return view('admin.master-data.parameter.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $data = Parameter::select(
            'parameters.id',
            'parameters.name',
            'parameters.description',
            'parameters.created_at',
            'parameters.deleted_at',
        );

        if ($request['status'] == "active") {
        } else if ($request['status'] == "deleted") {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('parameters.created_at', 'DESC');

        $data->get();

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value']) {
                    $query->where(function ($q) use ($search) {
                        $q->where('parameters.name', 'LIKE', "%{$search}%")
                            ->orWhere('parameters.description', 'LIKE', "%{$search}%")
                            ->orWhere('parameters.created_at', 'LIKE', "%{$search}%")
                            ->orWhere('parameters.deleted_at', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        return view('admin.master-data.parameter.insert');
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:parameters,name',
            'description' => 'nullable|string|max:500',
        ], [
            'name.required' => 'Parameter name is required.',
            'name.string' => 'Parameter name must be a string.',
            'name.max' => 'Parameter name maximum 255 characters.',
            'name.unique' => 'Parameter name already exists.',
            'description.string' => 'Description must be a string.',
            'description.max' => 'Description maximum 500 characters.',
        ]);

        $parameter = Parameter::create([
            'name' => $request['name'],
            'description' => $request['description'] ?? null,
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('parameter.index.view')->with('success', 'Successfully added parameter');
    }

    public function editView(Request $request, $id)
    {
        $parameter = Parameter::where('id', $id)
            ->withTrashed()
            ->first();

        if (!$parameter) {
            return redirect()->route('parameter.index.view')->with('warning', 'Parameter not found');
        }

        return view('admin.master-data.parameter.edit', compact('parameter'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:parameters,id',
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('parameters', 'name')->ignore($request->id),
            ],
            'description' => 'nullable|string|max:500',
        ], [
            'id.required' => 'ID is required.',
            'id.integer' => 'ID must be a number.',
            'id.exists' => 'ID is invalid or not found.',

            'name.required' => 'Parameter name is required.',
            'name.string' => 'Parameter name must be a string.',
            'name.max' => 'Parameter name maximum 255 characters.',
            'name.unique' => 'Parameter name already exists.',
            'description.string' => 'Description must be a string.',
            'description.max' => 'Description maximum 500 characters.',
        ]);

        $parameter = Parameter::where('id', $request['id'])
            ->withTrashed()
            ->first();

        $parameter->name = $request['name'];
        $parameter->description = $request['description'] ?? null;
        $parameter->updated_by = auth('web')->id();

        $parameter->save();

        return redirect()->route('parameter.index.view')->with('success', 'Successfully updated parameter');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'parameter_id_deleted' => 'required|integer|exists:parameters,id',
        ], [
            'parameter_id_deleted.required' => 'ID Parameter to be deleted is required.',
            'parameter_id_deleted.integer' => 'ID Parameter must be a number.',
            'parameter_id_deleted.exists' => 'ID Parameter is invalid or not found.',
        ]);

        $parameter = Parameter::where('id', $request['parameter_id_deleted'])
            ->first();

        $parameter->updated_by = auth('web')->id();
        $parameter->deleted_by = auth('web')->id();

        $parameter->save();
        $parameter->delete();

        return redirect()->route('parameter.index.view')->with('success', 'Successfully deleted parameter');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'parameter_id_restored' => 'required|integer|exists:parameters,id',
        ], [
            'parameter_id_restored.required' => 'ID Parameter to be restored is required.',
            'parameter_id_restored.integer' => 'ID Parameter must be a number.',
            'parameter_id_restored.exists' => 'ID Parameter is invalid or not found.',
        ]);

        $parameter = Parameter::where('id', $request['parameter_id_restored'])
            ->withTrashed()
            ->first();

        $parameter->updated_by = auth('web')->id();
        $parameter->deleted_by = null;

        $parameter->save();
        $parameter->restore();

        return redirect()->route('parameter.index.view')->with('success', 'Successfully restored parameter');
    }
}

