<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner\Agent;
use App\Models\Partner\Reseller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Support\WmsContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index()
    {
        $companyId = optional(WmsContext::distributor())->id;

        $promotions = Promotion::with(['buyProduct', 'buyVariant', 'getProduct', 'getVariant'])
            ->when($companyId, fn ($q) => $q->where(fn ($qq) => $qq->whereNull('company_id')->orWhere('company_id', $companyId)))
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('code')
            ->get();

        return view('admin.promotions.index', compact('promotions'));
    }

    public function create()
    {
        return view('admin.promotions.create', array_merge($this->formData(), [
            'previewCode' => Promotion::generateCode(optional(WmsContext::distributor())->id),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $companyId = optional(WmsContext::distributor())->id;
        $data['company_id'] = $companyId;
        $data['code'] = Promotion::generateCode($companyId);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        $data['is_active'] = $request->boolean('is_active', true);

        $promo = Promotion::create($data);

        return redirect()
            ->route('promotions.show', $promo->id)
            ->with('success', 'Promotion created.');
    }

    public function show(string $id)
    {
        $promo = Promotion::with([
            'buyProduct', 'buyVariant', 'getProduct', 'getVariant', 'getUnit',
        ])->findOrFail($id);

        return view('admin.promotions.show', compact('promo'));
    }

    public function edit(string $id)
    {
        $promo = Promotion::findOrFail($id);

        return view('admin.promotions.edit', array_merge($this->formData(), compact('promo')));
    }

    public function update(Request $request, string $id)
    {
        $promo = Promotion::findOrFail($id);
        $data = $this->validated($request, $promo->id);
        unset($data['code']);
        $data['updated_by'] = Auth::id();
        $data['is_active'] = $request->boolean('is_active');

        $promo->update($data);

        return redirect()
            ->route('promotions.show', $promo->id)
            ->with('success', 'Promotion updated.');
    }

    public function destroy(string $id)
    {
        $promo = Promotion::findOrFail($id);
        $promo->update(['deleted_by' => Auth::id()]);
        $promo->delete();

        return redirect()
            ->route('promotions.index')
            ->with('success', 'Promotion deleted.');
    }

    protected function formData(): array
    {
        $products = Product::query()
            ->saleItems()
            ->whereNull('deleted_at')
            ->with(['variants' => fn ($q) => $q->whereNull('deleted_at')->orderBy('sku')])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'default_unit_id']);

        $variants = ProductVariant::query()
            ->with('product:id,name,sku')
            ->whereNull('deleted_at')
            ->orderBy('sku')
            ->get()
            ->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'product_id' => $v->product_id,
                'label' => ($v->display_name ?? $v->sku).' — '.($v->product?->name ?? ''),
            ]);

        return [
            'products' => $products,
            'variants' => $variants,
            'agents' => $this->partnerAgents(),
            'resellers' => $this->partnerResellers(),
            'warehouseTypes' => [
                'MARKETING' => 'Marketing warehouse',
                'FG' => 'Product warehouse (FG)',
                'ORDER' => 'Same as sales order warehouse',
            ],
        ];
    }

    protected function validated(Request $request, ?string $ignoreId = null): array
    {
        $promotionType = $request->input('promotion_type', 'product');

        $data = $request->validate([
            'promotion_type' => ['required', 'in:product,marketing'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'string'],
            'ends_at' => ['nullable', 'string'],
            'trigger_level' => ['required', 'in:line'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'buy_min_qty' => ['nullable', 'required_if:promotion_type,product', 'numeric', 'min:0.000001'],
            'buy_product_id' => [
                'nullable',
                Rule::requiredIf(fn () => $promotionType === 'product' && ! $request->filled('buy_variant_id')),
                'string',
                'exists:product.products,id',
            ],
            'buy_variant_id' => [
                'nullable',
                Rule::requiredIf(fn () => $promotionType === 'product' && ! $request->filled('buy_product_id')),
                'string',
                'exists:product.product_variants,id',
            ],
            'get_qty' => ['nullable', 'required_if:promotion_type,product', 'numeric', 'min:0.000001'],
            'get_product_mode' => ['nullable', 'required_if:promotion_type,product', 'in:same,specific'],
            'get_product_id' => ['nullable', 'required_if:get_product_mode,specific', 'string', 'exists:product.products,id'],
            'get_variant_id' => ['nullable', 'string', 'exists:product.product_variants,id'],
            'get_unit_id' => ['nullable', 'string', 'exists:product.product_units,id'],
            'free_warehouse_type' => ['nullable', 'required_if:promotion_type,product', 'in:MARKETING,FG,ORDER'],
            'max_applications_per_line' => ['nullable', 'integer', 'min:1'],
            'target_type' => ['nullable', 'required_if:promotion_type,marketing', 'in:agent,reseller,both'],
            'target_agent_id' => ['nullable', 'uuid', 'exists:partner.agents,id'],
            'target_reseller_id' => ['nullable', 'uuid', 'exists:partner.resellers,id'],
            'reactivates_reseller' => ['nullable', 'boolean'],
            'min_purchase_type' => ['nullable', 'required_if:promotion_type,marketing', 'in:amount,qty'],
            'min_purchase_value' => ['nullable', 'required_if:promotion_type,marketing', 'numeric', 'min:0.000001'],
            'discount_type' => ['nullable', 'required_if:promotion_type,marketing', 'in:percent,nominal'],
            'discount_value' => ['nullable', 'required_if:promotion_type,marketing', 'numeric', 'min:0.000001'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['priority'] = (int) ($request->input('priority') ?: 100);

        if ($promotionType === 'marketing') {
            $data['buy_min_qty'] = null;
            $data['buy_product_id'] = null;
            $data['buy_variant_id'] = null;
            $data['get_qty'] = null;
            $data['get_product_mode'] = null;
            $data['get_product_id'] = null;
            $data['get_variant_id'] = null;
            $data['get_unit_id'] = null;
            $data['free_warehouse_type'] = null;
            $data['max_applications_per_line'] = $request->input('max_applications_per_line') ?: null;
            $data['reactivates_reseller'] = $request->boolean('reactivates_reseller');
            $data['target_agent_id'] = $request->input('target_agent_id') ?: null;
            $data['target_reseller_id'] = $request->input('target_reseller_id') ?: null;

            if ($data['target_type'] === 'agent') {
                $data['target_reseller_id'] = null;
            } elseif ($data['target_type'] === 'reseller') {
                $data['target_agent_id'] = null;
            }

            if ($data['discount_type'] === 'percent' && (float) $data['discount_value'] > 100) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'discount_value' => 'Discount percent cannot exceed 100.',
                ]);
            }
        } else {
            $data['target_type'] = null;
            $data['target_agent_id'] = null;
            $data['target_reseller_id'] = null;
            $data['reactivates_reseller'] = false;
            $data['min_purchase_type'] = null;
            $data['min_purchase_value'] = null;
            $data['discount_type'] = null;
            $data['discount_value'] = null;
            $data['buy_product_id'] = $request->input('buy_product_id') ?: null;
            $data['buy_variant_id'] = $request->input('buy_variant_id') ?: null;
            $data['get_product_id'] = $request->input('get_product_mode') === 'specific'
                ? ($request->input('get_product_id') ?: null)
                : null;
            $data['get_variant_id'] = $request->input('get_product_mode') === 'specific'
                ? ($request->input('get_variant_id') ?: null)
                : null;
        }

        $rawStarts = trim((string) $request->input('starts_at'));
        $rawEnds = trim((string) $request->input('ends_at'));
        $data['starts_at'] = $this->parseDateInput($rawStarts);
        $data['ends_at'] = $this->parseDateInput($rawEnds, endOfDay: true);

        if ($rawStarts !== '' && $data['starts_at'] === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'starts_at' => 'Invalid date format. Use dd/mm/yyyy.',
            ]);
        }
        if ($rawEnds !== '' && $data['ends_at'] === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ends_at' => 'Invalid date format. Use dd/mm/yyyy.',
            ]);
        }

        if ($data['starts_at'] && $data['ends_at'] && $data['ends_at']->lt($data['starts_at'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ends_at' => 'End date must be on or after start date.',
            ]);
        }

        return $data;
    }

    protected function parseDateInput(?string $value, bool $endOfDay = false): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                return $endOfDay ? $date->endOfDay() : $date->startOfDay();
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            $date = Carbon::parse($value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function partnerAgents()
    {
        $companyId = optional(WmsContext::distributor())->id;

        return Agent::query()
            ->active()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    protected function partnerResellers()
    {
        $companyId = optional(WmsContext::distributor())->id;

        return Reseller::query()
            ->whereNull('deleted_at')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
