<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CashFlowCategory;
use App\Models\Accounting\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CashFlowCategoryController extends Controller
{
    public function indexView(Request $request)
    {
        $status = $request->get('status', 'active');
        $search = trim((string) $request->get('q', ''));
        $isFilter = $status !== 'active' || $search !== '';

        $query = CashFlowCategory::query();

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status === 'all') {
            $query->withTrashed();
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ILIKE', "%{$search}%")
                    ->orWhere('name', 'ILIKE', "%{$search}%");
            });
        }

        $categories = $query->orderBy('sort_order')->orderBy('code')->get();

        return view('admin.finance.cash-flow-category.index', compact(
            'categories',
            'status',
            'search',
            'isFilter'
        ));
    }

    public function insertView()
    {
        return view('admin.finance.cash-flow-category.insert');
    }

    public function insertData(Request $request)
    {
        $validated = $this->validatePayload($request);

        CashFlowCategory::create(array_merge($validated, [
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]));

        return redirect()
            ->route('finance.cash-flow-category.index.view')
            ->with('success', 'Cash Flow Category berhasil ditambahkan.');
    }

    public function editView(string $id)
    {
        $category = CashFlowCategory::withTrashed()->findOrFail($id);

        return view('admin.finance.cash-flow-category.edit', compact('category'));
    }

    public function editData(Request $request)
    {
        $category = CashFlowCategory::withTrashed()->findOrFail($request->input('id'));
        $validated = $this->validatePayload($request, $category->id);

        $category->update(array_merge($validated, [
            'updated_by' => auth('web')->id(),
        ]));

        return redirect()
            ->route('finance.cash-flow-category.index.view')
            ->with('success', 'Cash Flow Category berhasil diperbarui.');
    }

    public function deleteData(Request $request)
    {
        $request->validate(['cash_flow_category_id_deleted' => 'required|uuid']);

        $category = CashFlowCategory::findOrFail($request->input('cash_flow_category_id_deleted'));

        $inUse = ChartOfAccount::query()
            ->where('cash_flow_category_id', $category->id)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'category' => 'Kategori tidak dapat dihapus karena masih dipakai di Chart of Accounts.',
            ]);
        }

        $category->deleted_by = auth('web')->id();
        $category->save();
        $category->delete();

        return redirect()
            ->route('finance.cash-flow-category.index.view')
            ->with('success', 'Cash Flow Category berhasil dihapus.');
    }

    public function restoreData(Request $request)
    {
        $request->validate(['cash_flow_category_id_restored' => 'required|uuid']);

        $category = CashFlowCategory::onlyTrashed()->findOrFail($request->input('cash_flow_category_id_restored'));
        $category->restore();
        $category->deleted_by = null;
        $category->updated_by = auth('web')->id();
        $category->save();

        return redirect()
            ->route('finance.cash-flow-category.index.view')
            ->with('success', 'Cash Flow Category berhasil direstore.');
    }

    private function validatePayload(Request $request, ?string $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('accounting.cash_flow_categories', 'code')
                    ->where(fn ($q) => $q->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable',
        ], [
            'code.regex' => 'Kode hanya boleh huruf kecil, angka, underscore, atau dash.',
        ]);

        $validated['code'] = strtolower(trim($validated['code']));
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['description'] = $validated['description'] ?? null;

        return $validated;
    }
}
