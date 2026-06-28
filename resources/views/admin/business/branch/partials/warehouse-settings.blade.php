@php
    $ownedWarehouse = $ownedWarehouse ?? null;
    $warehouseTypes = $warehouseTypes ?? [];
    $assignableWarehouses = $assignableWarehouses ?? collect();
    $assignedWarehouseIds = $assignedWarehouseIds ?? old('assigned_warehouse_ids', []);
    $defaultWarehouseId = $defaultWarehouseId ?? old('default_warehouse_id');
    $warehouseSetup = old('warehouse_setup', $ownedWarehouse ? '1' : '1');
@endphp

<div class="accordion-item">
    <h2 class="accordion-header">
        <button type="button" class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseWarehouse">
            <i class="ti ti-building-warehouse me-2"></i> Warehouse Settings
        </button>
    </h2>
    <div id="collapseWarehouse" class="accordion-collapse collapse" data-bs-parent="#branchAccordion">
        <div class="accordion-body">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="warehouse_setup" id="warehouse_setup" value="1"
                    @checked($warehouseSetup)>
                <label class="form-check-label" for="warehouse_setup">
                    Setup default warehouse for this branch
                </label>
            </div>

            @if ($ownedWarehouse)
                <input type="hidden" name="warehouse_id" value="{{ $ownedWarehouse->id }}">
            @endif

            <div id="warehouseSetupFields">
                <p class="text-muted small mb-3">
                    Default warehouse is used for inventory, stock movement, and WMS operations on this branch.
                </p>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Warehouse Code</label>
                        @if ($ownedWarehouse)
                            <input type="text" class="form-control bg-light" value="{{ $ownedWarehouse->code }}" readonly disabled>
                        @else
                            <input type="text" class="form-control bg-light" value="Auto-generated on save" readonly disabled>
                        @endif
                        <small class="text-muted">Warehouse code is generated automatically by the system.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Warehouse Name</label>
                        <input type="text" name="warehouse_name" id="warehouse_name" class="form-control"
                            placeholder="e.g. Gudang Cabang Jakarta"
                            value="{{ old('warehouse_name', $ownedWarehouse?->name) }}">
                        @error('warehouse_name')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Warehouse Type</label>
                        <select name="warehouse_type_code" class="select2 form-select">
                            <option value="">Select type</option>
                            @foreach ($warehouseTypes as $code => $label)
                                <option value="{{ $code }}"
                                    @selected(old('warehouse_type_code', $ownedWarehouse?->warehouse_type_code ?? 'GENERAL') === $code)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('warehouse_type_code')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="warehouse_is_inventory_active"
                                id="warehouse_is_inventory_active" value="1"
                                @checked(old('warehouse_is_inventory_active', $ownedWarehouse?->is_inventory_active ?? true))>
                            <label class="form-check-label" for="warehouse_is_inventory_active">
                                Inventory Active
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="warehouse_is_active"
                                id="warehouse_is_active" value="1"
                                @checked(old('warehouse_is_active', $ownedWarehouse?->is_active ?? true))>
                            <label class="form-check-label" for="warehouse_is_active">
                                Warehouse Active
                            </label>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <p class="text-muted small mb-3">
                    Optionally link shared/central warehouses from the same company. Select which warehouse is the default for this branch.
                </p>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Shared Warehouses</label>
                        <select name="assigned_warehouse_ids[]" id="assigned_warehouse_ids" class="select2 form-select" multiple>
                            @foreach ($assignableWarehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" data-company="{{ $warehouse->company_id }}"
                                    @selected(in_array($warehouse->id, (array) $assignedWarehouseIds, true))>
                                    {{ $warehouse->name }} ({{ $warehouse->code }})
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_warehouse_ids')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Default Warehouse</label>
                        <select name="default_warehouse_id" id="default_warehouse_id" class="select2 form-select">
                            <option value="">—</option>
                            @if ($ownedWarehouse)
                                <option value="{{ $ownedWarehouse->id }}" data-owned="1"
                                    @selected($defaultWarehouseId === $ownedWarehouse->id)>
                                    {{ $ownedWarehouse->name }} (Owned)
                                </option>
                            @endif
                            @foreach ($assignableWarehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" data-company="{{ $warehouse->company_id }}"
                                    @selected($defaultWarehouseId === $warehouse->id)>
                                    {{ $warehouse->name }} (Shared)
                                </option>
                            @endforeach
                        </select>
                        @error('default_warehouse_id')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
