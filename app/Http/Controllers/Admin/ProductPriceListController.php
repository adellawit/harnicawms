<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductPriceList;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class ProductPriceListController extends Controller
{
    protected function getBranchId(): ?string
    {
        return auth('web')->user()->current_business_unit_id;
    }

    protected function getCompanyId(): ?string
    {
        return auth('web')->user()->getCompanyIdForProduct();
    }

    public function indexView(Request $request)
    {
        $status = $request->query('status', 'active');
        return view('admin.product.price-list.index', compact('status'));
    }

    public function indexData(Request $request)
    {
        $branchId = $this->getBranchId();
        $companyId = $this->getCompanyId();

        $data = ProductPriceList::select(
            'product.product_price_lists.id',
            'product.product_price_lists.code',
            'product.product_price_lists.name',
            'product.product_price_lists.description',
            'product.product_price_lists.channel_type',
            'product.product_price_lists.external_channel_code',
            'product.product_price_lists.sort_order',
            'product.product_price_lists.is_active',
            'product.product_price_lists.created_at',
            'product.product_price_lists.deleted_at'
        )->from('product.product_price_lists');

        $data = $data->forBusinessContext($companyId, $branchId);

        if ($request->status === 'active') {
            // default - only non-deleted
        } elseif ($request->status === 'deleted') {
            $data = $data->onlyTrashed();
        } else {
            $data = $data->withTrashed();
        }

        $data = $data->orderBy('sort_order')->orderBy('name');

        return (new DataTables)->eloquent($data)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                // Global search
                if ($request->has('search') && $request->search['value']) {
                    $search = $request->search['value'];
                    $query->where(function ($q) use ($search) {
                        $q->where('code', 'ilike', "%{$search}%")
                            ->orWhere('name', 'ilike', "%{$search}%")
                            ->orWhere('external_channel_code', 'ilike', "%{$search}%");
                    });
                }
            })
            ->make(true);
    }

    public function insertView(Request $request)
    {
        $branchId = $this->getBranchId();
        $companyId = $this->getCompanyId();

        return view('admin.product.price-list.insert', compact('branchId', 'companyId'));
    }

    public function insertData(Request $request)
    {
        $branchId = $this->getBranchId();
        $companyId = $this->getCompanyId();

        $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('product.product_price_lists', 'code')
                    ->where('company_id', $companyId)
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'channel_type' => 'nullable|in:pos,marketplace,delivery',
            'external_channel_code' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
            'name.required' => 'Name is required.',
        ]);

        ProductPriceList::create([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'channel_type' => $request->channel_type,
            'external_channel_code' => $request->external_channel_code,
            'sort_order' => $request->sort_order ?? 0,
            'created_by' => auth('web')->id(),
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.price-list.index.view')
            ->with('success', 'Price list added successfully');
    }

    public function editView(Request $request, $id)
    {
        $priceList = ProductPriceList::where('id', $id)->withTrashed()->firstOrFail();
        
        return view('admin.product.price-list.edit', compact('priceList'));
    }

    public function editData(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:product.product_price_lists,id',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('product.product_price_lists', 'code')->ignore($request->id),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'channel_type' => 'nullable|in:pos,marketplace,delivery',
            'external_channel_code' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ], [
            'code.required' => 'Code is required.',
            'code.unique' => 'Code already exists.',
            'name.required' => 'Name is required.',
        ]);

        $priceList = ProductPriceList::where('id', $request->id)->withTrashed()->firstOrFail();
        
        $priceList->update([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
            'channel_type' => $request->channel_type,
            'external_channel_code' => $request->external_channel_code,
            'sort_order' => $request->sort_order ?? 0,
            'updated_by' => auth('web')->id(),
        ]);

        return redirect()->route('product.price-list.index.view')
            ->with('success', 'Price list updated successfully');
    }

    public function deleteData(Request $request)
    {
        $request->validate([
            'price_list_id_deleted' => 'required|exists:product.product_price_lists,id',
        ]);

        $priceList = ProductPriceList::findOrFail($request->price_list_id_deleted);
        $priceList->update([
            'deleted_by' => auth('web')->id(),
        ]);
        $priceList->delete();

        return redirect()->route('product.price-list.index.view')
            ->with('success', 'Price list deleted successfully');
    }

    public function restoreData(Request $request)
    {
        $request->validate([
            'price_list_id_restored' => 'required|exists:product.product_price_lists,id',
        ]);

        $priceList = ProductPriceList::withTrashed()->findOrFail($request->price_list_id_restored);
        $priceList->update([
            'deleted_by' => null,
        ]);
        $priceList->restore();

        return redirect()->route('product.price-list.index.view')
            ->with('success', 'Price list restored successfully');
    }

    /**
     * Get active price lists for dropdown
     */
    public function getActiveLists(Request $request)
    {
        $branchId = $this->getBranchId();
        $companyId = $this->getCompanyId();

        $priceLists = ProductPriceList::whereNull('deleted_at')
            ->where('is_active', true)
            ->forBusinessContext($companyId, $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json($priceLists);
    }
}
