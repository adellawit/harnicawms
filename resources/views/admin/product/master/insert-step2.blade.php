<x-app-layout>

    @section('title', 'Add Product | ')

    @push('page-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
        </style>
    @endpush

    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product', 'url' => route('product.index.view')],
                ['label' => 'Add Product', 'active' => true]
            ]"
        />

        <!-- Progress Steps -->
        <div class="card mb-3">
            <div class="card-body">
                <ul class="list-group list-group-flush list-group-horizontal d-flex justify-content-center mb-0">
                    <li class="list-group-item d-flex align-items-center border-end-0 pe-0">
                        <span class="badge bg-label-success rounded-circle me-2"><i class="ti ti-check"></i></span>
                        <span class="text-muted">Product Info</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center border-end-0 pe-0 ps-2">
                        <span class="badge bg-label-primary rounded-circle me-2">2</span>
                        <span class="fw-bold">Unit Conversions</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center ps-2 opacity-50">
                        <span class="badge bg-label-secondary rounded-circle me-2">3</span>
                        <span class="text-muted">Variants & Prices</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Step 2 Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Step 2: Unit Conversions</h5>
                <small class="text-muted">
                    @if(! empty($conversions))
                        Continue the conversion chain from <strong>{{ $selectedUnit->name ?? 'current unit' }}</strong>
                    @else
                        Start from default unit <strong>{{ $defaultUnit->name ?? 'default unit' }}</strong>.
                        Boleh dilewati jika produk hanya punya 1 satuan (mis. Label/Dus — Pcs saja).
                    @endif
                </small>
            </div>
            <div class="card-body">
                @if(session('success'))
                    <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
                @endif

                @if ($errors->any())
                    <x-alert type="danger" class="mb-3">
                        <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </x-alert>
                @endif

                @if(session('error'))
                    <x-alert type="danger" class="mb-3">{{ session('error') }}</x-alert>
                @endif

                <div class="alert alert-info mb-3">
                    <i class="ti ti-info-circle me-1"></i>
                    <strong>Unit Conversions:</strong> Define how your product units relate to each other (e.g., 1 Karton = 30 Pack, 1 Pack = 10 Box → total 300 Box per Karton). Each step is <em>per langkah</em>, bukan total dari Karton.
                    Jika produk hanya memakai 1 satuan (mis. Pcs), lewati langkah ini dan klik <em>Next</em>.
                </div>

                <form method="POST" action="{{ route('product.insert.data.step2') }}" class="row g-3">
                    @csrf

                    <div class="col-md-6">
                        <label for="from_unit_id" class="form-label">From Unit <span class="text-danger">*</span></label>
                        <select id="from_unit_id" name="from_unit_id" class="select2 form-select @error('from_unit_id') is-invalid @enderror" disabled>
                            @foreach ($units as $unit)
                                <option value="{{ $unit->id }}" {{ (old('from_unit_id') ?? $selectedUnit?->id ?? '') == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->symbol }})</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="from_unit_id" value="{{ old('from_unit_id') ?? $selectedUnit?->id ?? '' }}" id="from_unit_id_hidden" />
                        @error('from_unit_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="to_unit_id" class="form-label">To Unit <span class="text-danger">*</span></label>
                        <select id="to_unit_id" name="to_unit_id" class="select2 form-select @error('to_unit_id') is-invalid @enderror">
                            <option value="">Select Unit</option>
                            @foreach ($units as $unit)
                                @if($unit->id === ($selectedUnit?->id ?? '') || in_array($unit->id, $usedUnitIds ?? [], true))
                                    @continue
                                @endif
                                <option value="{{ $unit->id }}" data-unit-name="{{ $unit->name }}" data-unit-symbol="{{ $unit->symbol }}" {{ old('to_unit_id') == $unit->id ? 'selected' : '' }}>{{ $unit->name }} ({{ $unit->symbol }})</option>
                            @endforeach
                        </select>
                        @error('to_unit_id') <span class="invalid-feedback">{{ $message }}</span> @enderror
                        <small class="text-muted">Units already in the chain are not available.</small>
                    </div>

                    <div class="col-md-6">
                        <label for="conversion_factor" class="form-label">Conversion Factor <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('conversion_factor') is-invalid @enderror" id="conversion_factor" name="conversion_factor" value="{{ old('conversion_factor') }}" placeholder="e.g., 10">
                        <div class="form-text" id="conversion_factor_help">1 {{ $selectedUnit->name ?? 'Unit' }} = {conversion_factor} {to unit}</div>
                        @if(! empty($conversions))
                            @php
                                $chainFactor = 1;
                                foreach ($conversions as $conv) {
                                    $chainFactor *= (float) ($conv['conversion_factor'] ?? 1);
                                }
                            @endphp
                            <small class="text-warning d-block mt-1">
                                <i class="ti ti-alert-triangle me-1"></i>
                                Factor berikut = per langkah (bukan total dari {{ $defaultUnit->name ?? 'satuan default' }}).
                                Saat ini: 1 {{ $defaultUnit->name ?? 'default' }} = {{ rtrim(rtrim(number_format($chainFactor, 6, ',', '.'), '0'), ',') }}
                                {{ $units->firstWhere('id', end($conversions)['to_unit_id'])?->name ?? 'satuan terkecil' }}.
                            </small>
                        @endif
                        @error('conversion_factor') <span class="invalid-feedback">{{ $message }}</span> @enderror
                    </div>

                    <!-- Saved Conversions List -->
                    @if(!empty($conversions))
                        <div class="col-12 mt-4">
                            <h6>Saved Conversions:</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>Level</th>
                                            <th>From Unit</th>
                                            <th>To Unit</th>
                                            <th>Conversion Factor</th>
                                            <th style="width:100px">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($conversions as $index => $conv)
                                            @php
                                                $fromUnit = $units->firstWhere('id', $conv['from_unit_id']);
                                                $toUnit = $units->firstWhere('id', $conv['to_unit_id']);
                                            @endphp
                                            <tr data-index="{{ $index }}">
                                                <td>{{ $conv['conversion_level'] ?? ($index + 1) }}</td>
                                                <td>{{ $fromUnit->name ?? '-' }}</td>
                                                <td>{{ $toUnit->name ?? '-' }}</td>
                                                <td>{{ $conv['conversion_factor'] }}</td>
                                                <td>
                                                    <div class="d-inline-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-icon btn-outline-warning btn-edit-conv" data-index="{{ $index }}" data-from="{{ $conv['from_unit_id'] }}" data-to="{{ $conv['to_unit_id'] }}" data-factor="{{ $conv['conversion_factor'] }}">
                                                            <i class="ti ti-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-remove-conv" data-index="{{ $index }}">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="col-12 mt-4 text-end">
                        <a href="{{ route('product.insert.view.step1') }}" class="btn btn-label-secondary">Back</a>
                        <button type="button" id="btn-add-more" class="btn btn-secondary me-2">
                            <i class="ti ti-plus me-1"></i> Add More
                        </button>
                        <button type="button" id="btn-next-step" class="btn btn-primary">
                            @if(empty($conversions))
                                Next (Skip — Satuan Tunggal)
                            @else
                                Next: Variants & Prices
                            @endif
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Conversion Modal --}}
        <div class="modal fade" id="editConversionModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="editConversionForm">
                        @csrf
                        <input type="hidden" name="index" id="edit_conv_index" />
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Unit Conversion</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">From Unit <span class="text-danger">*</span></label>
                                <select name="from_unit_id" id="edit_from_unit" class="select2 form-select" disabled>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->symbol }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">To Unit <span class="text-danger">*</span></label>
                                <select name="to_unit_id" id="edit_to_unit" class="select2 form-select" required>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}" data-unit-name="{{ $unit->name }}" data-unit-symbol="{{ $unit->symbol }}">{{ $unit->name }} ({{ $unit->symbol }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Conversion Factor <span class="text-danger">*</span></label>
                                <input type="text" name="conversion_factor" id="edit_conv_factor" class="form-control" required />
                                <div class="form-text">1 from unit = factor × to unit</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    <!-- / Content -->

    @push('page-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            const fromUnitId = '{{ $selectedUnit?->id ?? '' }}';
            const fromUnitName = '{{ $selectedUnit->name ?? 'Unit' }}';
            const usedUnitIds = @json($usedUnitIds ?? []);
            const defaultUnitId = '{{ $tempProduct['default_unit_id'] ?? '' }}';
            const savedConversions = @json($conversions ?? []);

            function getUsedUnitIdsExceptIndex(exceptIndex) {
                const chain = [defaultUnitId];
                let currentUnitId = defaultUnitId;

                savedConversions.forEach(function(conv, index) {
                    if (index === exceptIndex) {
                        return;
                    }
                    if (conv.from_unit_id === currentUnitId) {
                        currentUnitId = conv.to_unit_id;
                        chain.push(currentUnitId);
                    }
                });

                return chain;
            }

            function refreshEditToUnitOptions(fromUnitId, exceptIndex, selectedToUnitId) {
                const blockedIds = getUsedUnitIdsExceptIndex(exceptIndex);
                const $select = $('#edit_to_unit');

                $select.find('option').each(function() {
                    const optionId = $(this).val();
                    if (!optionId) {
                        return;
                    }

                    const isBlocked = blockedIds.includes(optionId) || optionId === fromUnitId;
                    const isCurrentSelection = optionId === selectedToUnitId;
                    $(this).prop('disabled', isBlocked && !isCurrentSelection);
                });

                $select.trigger('change.select2');
            }

            $(document).ready(function() {
                // Update help text when To Unit changes
                $('#to_unit_id').on('change', function() {
                    updateHelpText();
                });

                // Add More button click
                $('#btn-add-more').click(function() {
                    // Validate before submitting
                    const toUnit = $('#to_unit_id').val();
                    const factor = $('#conversion_factor').val();

                    if (!toUnit) {
                        alert('Please select a To Unit.');
                        return;
                    }
                    if (usedUnitIds.includes(toUnit)) {
                        alert('To Unit is already used in the conversion chain.');
                        return;
                    }
                    if (toUnit === fromUnitId) {
                        alert('To Unit must be different from From Unit.');
                        return;
                    }
                    if (!factor) {
                        alert('Please enter a Conversion Factor.');
                        return;
                    }

                    // Create hidden input and submit form
                    const form = $('form[action="{{ route("product.insert.data.step2") }}"]');
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'add_more',
                        value: '1'
                    }).appendTo(form);
                    form.submit();
                });

                // Next Step: conversions optional (single-unit products like Label/Dus Pcs)
                $('#btn-next-step').click(function() {
                    window.location.href = '{{ route("product.insert.view.step3") }}';
                });

                // Edit button click
                $('.btn-edit-conv').click(function() {
                    const btn = $(this);
                    const editIndex = parseInt(btn.data('index'), 10);
                    const editFromUnitId = btn.data('from');
                    const editToUnitId = btn.data('to');

                    $('#edit_conv_index').val(editIndex);
                    $('#edit_from_unit').val(editFromUnitId);
                    $('#edit_to_unit').val(editToUnitId);
                    $('#edit_conv_factor').val(btn.data('factor'));
                    refreshEditToUnitOptions(editFromUnitId, editIndex, editToUnitId);
                    $('#edit_from_unit').trigger('change.select2');
                    $('#edit_to_unit').trigger('change.select2');
                    const modal = new bootstrap.Modal('#editConversionModal');
                    modal.show();
                });

                // Remove button click
                $('.btn-remove-conv').click(function() {
                    const index = $(this).data('index');
                    if (confirm('Remove this conversion?')) {
                        removeConversion(index);
                    }
                });

                // Edit form submit
                $('#editConversionForm').on('submit', function(e) {
                    e.preventDefault();
                    updateConversion();
                });
            });

            function updateHelpText() {
                const selectedOption = $('#to_unit_id option:selected');
                const toUnitSymbol = selectedOption.data('unit-symbol') || '';
                $('#conversion_factor_help').text('1 ' + fromUnitName + ' = {conversion_factor} ' + toUnitSymbol);
            }

            function removeConversion(index) {
                fetch('{{ route('product.conversion.remove-temp') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ index: index })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error removing conversion');
                });
            }

            function updateConversion() {
                const formData = new FormData(document.getElementById('editConversionForm'));
                const data = {
                    index: formData.get('index'),
                    from_unit_id: $('#edit_from_unit').val(),
                    to_unit_id: $('#edit_to_unit').val(),
                    conversion_factor: formData.get('conversion_factor')
                };

                fetch('{{ route('product.conversion.update-temp') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(async response => {
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Error updating conversion');
                    }

                    bootstrap.Modal.getInstance('#editConversionModal').hide();
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(error.message || 'Error updating conversion');
                });
            }
        </script>
    @endpush

</x-app-layout>
