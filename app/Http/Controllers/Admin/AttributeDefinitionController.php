<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttributeDefinition;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class AttributeDefinitionController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->filled('status') ? $request->status : '';
        $isFilter = $status !== '';

        return view('admin.product.attribute.index', compact('status', 'isFilter'));
    }

    public function indexData(Request $request)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $data = AttributeDefinition::select(
            'attribute_definitions.id',
            'attribute_definitions.name',
            'attribute_definitions.code',
            'attribute_definitions.type',
            'attribute_definitions.sort_order',
            'attribute_definitions.created_at',
            'attribute_definitions.deleted_at'
        )->from('product.attribute_definitions as attribute_definitions');

        if ($companyId) {
            $data = $data->where('attribute_definitions.company_id', $companyId);
        }

        if ($request->status === 'active') {
            // default
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('attribute_definitions.sort_order', 'ASC')
            ->orderBy('attribute_definitions.created_at', 'DESC');

        /** @var \Illuminate\Database\Eloquent\Builder $data */
        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($search = $request->get('search')['value'] ?? null) {
                    $query->where(function ($q) use ($search) {
                        $q->where('attribute_definitions.name', 'LIKE', "%{$search}%")
                            ->orWhere('attribute_definitions.code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->toJson();
    }

    public function insertView(Request $request)
    {
        return view('admin.product.attribute.insert');
    }

    public function insertData(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', Rule::unique('product.attribute_definitions', 'code')],
            'type' => 'required|in:select,text,number',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Name is required.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
        ]);

        $user = auth('web')->user();

        AttributeDefinition::create([
            'company_id' => $user->getCompanyIdForProduct(),
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'description' => $request->description,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.attribute.index.view')->with('success', 'Attribute definition added successfully');
    }

    public function editView(Request $request, $id)
    {
        $companyId = auth('web')->user()->getCompanyIdForProduct();

        $definition = AttributeDefinition::where('id', $id)->withTrashed()->firstOrFail();
        if ($companyId && $definition->company_id !== $companyId) {
            abort(403);
        }
        $definition->load('attributeValues');

        return view('admin.product.attribute.edit', compact('definition'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.attribute_definitions,id',
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:100', Rule::unique('product.attribute_definitions', 'code')->ignore($request->id)],
            'type' => 'required|in:select,text,number',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'name.required' => 'Name is required.',
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
        ]);

        $definition = AttributeDefinition::where('id', $request->id)->withTrashed()->firstOrFail();
        $definition->update([
            'name' => $request->name,
            'code' => $request->code,
            'type' => $request->type,
            'description' => $request->description,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.attribute.edit.view', $definition->id)->with('success', 'Attribute definition updated successfully');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'attribute_definition_id_deleted' => 'required|exists:product.attribute_definitions,id',
        ]);

        $definition = AttributeDefinition::findOrFail($request->attribute_definition_id_deleted);
        $definition->updated_by = auth('web')->id();
        $definition->deleted_by = auth('web')->id();
        $definition->save();
        $definition->delete();

        return redirect()->route('product.attribute.index.view')->with('success', 'Attribute definition deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'attribute_definition_id_restored' => 'required|exists:product.attribute_definitions,id',
        ]);

        $definition = AttributeDefinition::withTrashed()->findOrFail($request->attribute_definition_id_restored);
        $definition->updated_by = auth('web')->id();
        $definition->deleted_by = null;
        $definition->save();
        $definition->restore();

        return redirect()->route('product.attribute.index.view')->with('success', 'Attribute definition restored successfully');
    }

    public function addValue(Request $request)
    {
        $request->validate([
            'attribute_definition_id' => 'required|exists:product.attribute_definitions,id',
            'value' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $definition = AttributeDefinition::findOrFail($request->attribute_definition_id);
        $user = auth('web')->user();

        AttributeValue::create([
            'attribute_definition_id' => $definition->id,
            'value' => $request->value,
            'code' => $request->code ?: null,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('product.attribute.edit.view', $definition->id)->with('success', 'Value added successfully');
    }

    public function editValue(Request $request)
    {
        $request->validate([
            'value_id' => 'required|exists:product.attribute_values,id',
            'value' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $attrValue = AttributeValue::findOrFail($request->value_id);
        $attrValue->update([
            'value' => $request->value,
            'code' => $request->code ?: null,
            'sort_order' => (int) ($request->sort_order ?? 0),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.attribute.edit.view', $attrValue->attribute_definition_id)->with('success', 'Value updated successfully');
    }

    public function deleteValue(Request $request)
    {
        $request->validate([
            'value_id' => 'required|exists:product.attribute_values,id',
        ]);

        $attrValue = AttributeValue::findOrFail($request->value_id);
        $defId = $attrValue->attribute_definition_id;
        $attrValue->updated_by = auth('web')->id();
        $attrValue->deleted_by = auth('web')->id();
        $attrValue->save();
        $attrValue->delete();

        return redirect()->route('product.attribute.edit.view', $defId)->with('success', 'Value deleted successfully');
    }
}
