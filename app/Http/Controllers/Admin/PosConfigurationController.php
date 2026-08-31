<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\PosWarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosConfigurationController extends Controller
{
    public function __construct(
        protected PosWarehouseService $posWarehouses,
    ) {}

    public function indexView(): View
    {
        $user = auth('web')->user();
        $branchId = $user?->current_business_unit_id;
        $companyId = $user?->getCompanyIdForProduct();

        return view('admin.settings.pos-configuration.index', [
            'warehouses' => $this->posWarehouses->configurableWarehouses($branchId, $companyId),
            'unitModes' => [
                PosWarehouseService::UNIT_LARGE_ONLY => 'Besar saja',
                PosWarehouseService::UNIT_FREE => 'Bebas (kecil / besar)',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouses' => ['required', 'array'],
            'warehouses.*.is_pos_active' => ['nullable'],
            'warehouses.*.pos_require_serial_scan' => ['nullable'],
            'warehouses.*.pos_unit_mode' => ['required', 'in:large_only,free'],
        ]);

        $user = auth('web')->user();
        $branchId = $user?->current_business_unit_id;
        $companyId = $user?->getCompanyIdForProduct();
        $allowedIds = $this->posWarehouses
            ->configurableWarehouses($branchId, $companyId)
            ->pluck('id')
            ->all();

        foreach ($validated['warehouses'] as $warehouseId => $row) {
            if (! in_array($warehouseId, $allowedIds, true)) {
                continue;
            }

            Warehouse::query()
                ->whereKey($warehouseId)
                ->update([
                    'is_pos_active' => $this->toBool($row['is_pos_active'] ?? false),
                    'pos_require_serial_scan' => $this->toBool($row['pos_require_serial_scan'] ?? false),
                    'pos_unit_mode' => $row['pos_unit_mode'],
                    'updated_by' => $user?->id,
                ]);
        }

        return redirect()
            ->route('settings.pos-configuration.index.view')
            ->with('success', 'Konfigurasi POS berhasil disimpan.');
    }

    protected function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
