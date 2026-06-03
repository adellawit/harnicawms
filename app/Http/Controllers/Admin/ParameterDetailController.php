<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use App\Models\ParameterDetail;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class ParameterDetailController extends Controller
{
    public function indexView(Request $request, $parameterId)
    {
        $status = "";
        if ($request->filled('status')) {
            $status = $request['status'];
        }

        $isFilter = false;
        if ($status != "") {
            $isFilter = true;
        }

        $parameter = Parameter::findOrFail($parameterId);

        return view('admin.master-data.parameter-detail.index', compact('status', 'parameterId', 'isFilter', 'parameter'));
    }

    public function indexData(Request $request, $parameterId)
    {
        $data = ParameterDetail::select(
            'parameter_details.id',
            'parameter_details.parameter_id',
            'parameter_details.key',
            'parameter_details.value',
            'parameter_details.description',
            'parameter_details.created_at',
            'parameter_details.deleted_at',
            'parameters.name as parameter_name'
        )
            ->leftJoin('parameters', 'parameter_details.parameter_id', '=', 'parameters.id')
            ->where('parameter_details.parameter_id', $parameterId);

        if ($request['status'] == "active") {
        } else if ($request['status'] == "deleted") {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('parameter_details.key')
            ->orderBy('parameter_details.created_at', 'DESC');

        $data->get();

        $dt = new DataTables();

        return $dt->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value']) {
                    $query->where(function ($q) use ($search) {
                        $q->where('parameter_details.key', 'LIKE', "%{$search}%")
                            ->orWhere('parameter_details.value', 'LIKE', "%{$search}%")
                            ->orWhere('parameter_details.description', 'LIKE', "%{$search}%")
                            ->orWhere('parameter_details.created_at', 'LIKE', "%{$search}%")
                            ->orWhere('parameter_details.deleted_at', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request, $parameterId)
    {
        $parameter = Parameter::findOrFail($parameterId);
        return view('admin.master-data.parameter-detail.insert', compact('parameter', 'parameterId'));
    }

    public function insertData(Request $request, $parameterId)
    {
        $request->validate([
            'key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('parameter_details', 'key')->where('parameter_id', $parameterId),
            ],
            'value' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ], [
            'key.required' => 'Key is required.',
            'key.string' => 'Key must be a string.',
            'key.max' => 'Key maximum 255 characters.',
            'key.unique' => 'Key already exists for this parameter.',
            'value.string' => 'Value must be a string.',
            'value.max' => 'Value maximum 255 characters.',
            'description.string' => 'Description must be a string.',
            'description.max' => 'Description maximum 500 characters.',
        ]);

        $parameterDetail = ParameterDetail::create([
            'parameter_id' => $parameterId,
            'key' => $request['key'],
            'value' => $request['value'] ?? null,
            'description' => $request['description'] ?? null,
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('parameter.details.index.view', $parameterId)->with('success', 'Successfully added parameter detail');
    }

    public function editView(Request $request, $parameterId, $id)
    {
        $parameterDetail = ParameterDetail::where('id', $id)
            ->withTrashed()
            ->first();

        if (!$parameterDetail) {
            return redirect()->route('parameter.details.index.view', $parameterId)->with('warning', 'Parameter detail not found');
        }

        $parameter = Parameter::findOrFail($parameterId);

        return view('admin.master-data.parameter-detail.edit', compact('parameterDetail', 'parameter', 'parameterId'));
    }

    public function editData(Request $request, $parameterId)
    {
        $request->validate([
            'id' => 'required|exists:parameter_details,id',
            'key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('parameter_details', 'key')->where('parameter_id', $parameterId)->ignore($request->id),
            ],
            'value' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
        ], [
            'id.required' => 'ID is required.',
            'id.exists' => 'ID is invalid or not found.',
            'key.required' => 'Key is required.',
            'key.string' => 'Key must be a string.',
            'key.max' => 'Key maximum 255 characters.',
            'key.unique' => 'Key already exists for this parameter.',
            'value.string' => 'Value must be a string.',
            'value.max' => 'Value maximum 255 characters.',
            'description.string' => 'Description must be a string.',
            'description.max' => 'Description maximum 500 characters.',
        ]);

        $parameterDetail = ParameterDetail::where('id', $request['id'])
            ->withTrashed()
            ->first();

        $parameterDetail->key = $request['key'];
        $parameterDetail->value = $request['value'] ?? null;
        $parameterDetail->description = $request['description'] ?? null;
        $parameterDetail->updated_by = auth('web')->id();

        $parameterDetail->save();

        return redirect()->route('parameter.details.index.view', $parameterId)->with('success', 'Successfully updated parameter detail');
    }

    public function deleteData(Request $request, $parameterId)
    {
        $request->validate([
            'parameter_detail_id_deleted' => 'required|exists:parameter_details,id',
        ], [
            'parameter_detail_id_deleted.required' => 'ID Parameter detail to be deleted is required.',
            'parameter_detail_id_deleted.exists' => 'ID Parameter detail is invalid or not found.',
        ]);

        $parameterDetail = ParameterDetail::where('id', $request['parameter_detail_id_deleted'])
            ->first();

        $parameterDetail->updated_by = auth('web')->id();
        $parameterDetail->deleted_by = auth('web')->id();

        $parameterDetail->save();
        $parameterDetail->delete();

        return redirect()->route('parameter.details.index.view', $parameterId)->with('success', 'Successfully deleted parameter detail');
    }

    public function restoreData(Request $request, $parameterId)
    {
        $request->validate([
            'parameter_detail_id_restored' => 'required|exists:parameter_details,id',
        ], [
            'parameter_detail_id_restored.required' => 'ID Parameter detail to be restored is required.',
            'parameter_detail_id_restored.exists' => 'ID Parameter detail is invalid or not found.',
        ]);

        $parameterDetail = ParameterDetail::where('id', $request['parameter_detail_id_restored'])
            ->withTrashed()
            ->first();

        $parameterDetail->updated_by = auth('web')->id();
        $parameterDetail->deleted_by = null;

        $parameterDetail->save();
        $parameterDetail->restore();

        return redirect()->route('parameter.details.index.view', $parameterId)->with('success', 'Successfully restored parameter detail');
    }
}

