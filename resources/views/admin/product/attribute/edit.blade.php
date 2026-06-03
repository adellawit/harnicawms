@php /** @var \App\Models\AttributeDefinition $definition */ @endphp
<x-app-layout>
    @section('title', 'Edit Attribute - ' . $definition->name . ' | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Product'],
                ['label' => 'Attribute', 'url' => route('product.attribute.index.view')],
                ['label' => $definition->name, 'active' => true],
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        @php
            $hasUpdatePermission = session('permissions.Attribute.is_update', false) == 1;
            $hasDeletePermission = session('permissions.Attribute.is_delete', false) == 1;
        @endphp

        {{-- Definition Form --}}
        <div class="card mb-4">
            <h5 class="card-header fw-bold">Attribute Definition</h5>
            <form method="POST" action="{{ route('product.attribute.edit.data') }}" id="postForm">
                @csrf
                <input type="hidden" name="id" value="{{ $definition->id }}" />
                <hr style="margin: 0.5rem 0;" />
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $definition->name) }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
                            <input type="text" id="code" name="code" class="form-control" value="{{ old('code', $definition->code) }}" required />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="type">Type <span class="text-danger">*</span></label>
                            <select id="type" name="type" class="form-select" required>
                                <option value="select" {{ old('type', $definition->type) == 'select' ? 'selected' : '' }}>Select</option>
                                <option value="text" {{ old('type', $definition->type) == 'text' ? 'selected' : '' }}>Text</option>
                                <option value="number" {{ old('type', $definition->type) == 'number' ? 'selected' : '' }}>Number</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sort_order">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order" class="form-control" value="{{ old('sort_order', $definition->sort_order ?? 0) }}" min="0" />
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $definition->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </form>
            <div class="card-footer">
                <button type="button" class="btn btn-primary" id="btn-submit">Save Definition</button>
            </div>
        </div>

        {{-- Attribute Values (for type=select) --}}
        <div class="card">
            <h5 class="card-header fw-bold d-flex justify-content-between align-items-center">
                <span>Attribute Values</span>
                @if($hasUpdatePermission && !$definition->deleted_at)
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addValueModal">
                    <i class="ti ti-plus me-1"></i> Add Value
                </button>
                @endif
            </h5>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Value</th>
                                <th>Code</th>
                                <th>Sort</th>
                                @if($hasUpdatePermission && !$definition->deleted_at)<th style="width:100px">Action</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($definition->attributeValues as $i => $val)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $val->value }}</td>
                                <td>{{ $val->code ?? '-' }}</td>
                                <td>{{ $val->sort_order ?? 0 }}</td>
                                @if($hasUpdatePermission && !$definition->deleted_at)
                                <td>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-warning btn-edit-value"
                                        data-id="{{ $val->id }}"
                                        data-value="{{ $val->value }}"
                                        data-code="{{ $val->code }}"
                                        data-sort="{{ $val->sort_order ?? 0 }}">
                                        <i class="ti ti-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('product.attribute.delete-value') }}" class="d-inline" onsubmit="return confirm('Delete this value?');">
                                        @csrf
                                        <input type="hidden" name="value_id" value="{{ $val->id }}" />
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger"><i class="ti ti-trash"></i></button>
                                    </form>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ ($hasUpdatePermission && !$definition->deleted_at) ? 5 : 4 }}" class="text-center text-muted">
                                    @if($definition->type === 'select')
                                        No values yet. Click "Add Value" to add options.
                                    @else
                                        Values are entered per product for text/number attributes.
                                    @endif
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if($hasUpdatePermission && !$definition->deleted_at && $definition->type === 'select')
    {{-- Add Value Modal --}}
    <div class="modal fade" id="addValueModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('product.attribute.add-value') }}">
                    @csrf
                    <input type="hidden" name="attribute_definition_id" value="{{ $definition->id }}" />
                    <div class="modal-header">
                        <h5 class="modal-title">Add Attribute Value</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Value <span class="text-danger">*</span></label>
                            <input type="text" name="value" class="form-control" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" class="form-control" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Edit Value Modal --}}
    <div class="modal fade" id="editValueModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('product.attribute.edit-value') }}">
                    @csrf
                    <input type="hidden" name="value_id" id="edit_value_id" />
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Attribute Value</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Value <span class="text-danger">*</span></label>
                            <input type="text" name="value" id="edit_value_value" class="form-control" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" id="edit_value_code" class="form-control" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" id="edit_value_sort" class="form-control" min="0" />
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
    @endif

    <div class="floating-footer d-flex justify-content-end align-items-center">
        <a href="{{ route('product.attribute.index.view') }}" class="btn btn-outline-dark">Back to List</a>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush
    @push('page-js')
        <script>
            $(function() {
                $('#btn-submit').click(function() { $('#postForm').submit(); });
                $('#postForm').submit(function() {
                    $(this).block({ message: '<div class="spinner-border text-primary"></div>', timeout: 1000, css: { backgroundColor: "transparent", border: 0 }, overlayCSS: { backgroundColor: "#fff", opacity: .8 } });
                });
                $('.btn-edit-value').on('click', function() {
                    var el = $(this);
                    $('#edit_value_id').val(el.data('id'));
                    $('#edit_value_value').val(el.data('value'));
                    $('#edit_value_code').val(el.data('code'));
                    $('#edit_value_sort').val(el.data('sort'));
                    $('#editValueModal').modal('show');
                });
            });
        </script>
    @endpush
</x-app-layout>
