<x-app-layout>

    @section('title', 'Notification Configuration | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

    @push('page-css')
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
        </style>
    @endpush

    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'Notifications', 'active' => true]
            ]"
        />

        <div class="row">
            <div class="col-md-12">
                @if (session('success'))
                    <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
                @endif

                @if ($errors->any())
                    <x-alert type="danger" class="mb-3">
                        <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </x-alert>
                @endif

                <form method="POST" action="{{ route('notifications.update.config') }}" id="postForm">
                    @csrf
                    <div class="card mb-4">
                        <h5 class="card-header">Notification Configuration</h5>
                        <div class="card-body">
                            <p class="text-muted mb-4">
                                Configure notification recipients for each module. You can specify recipients by role, developer, or enable notifications for all users.
                            </p>

                            @php
                                $modules = [
                                    'client' => 'Project',
                                    'subscription' => 'Subscription',
                                    'payment' => 'Payment',
                                    'reimbursement' => 'Reimbursement',
                                    'mom_meeting' => 'MoM Meeting',
                                    'repository' => 'Repository',
                                    'task' => 'Task',
                                ];
                            @endphp

                            @foreach ($modules as $moduleKey => $moduleName)
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">{{ $moduleName }}</h6>
                                    </div>
                                    <div class="card-body">
                                        @php
                                            $moduleConfig = $config[$moduleKey]['created'] ?? [];
                                            $selectedRoles = $moduleConfig['roles'] ?? [];
                                            $selectedDevelopers = $moduleConfig['developers'] ?? [];
                                            $notifyAll = $moduleConfig['all'] ?? false;
                                            // For task, developers is always an array (no more 'assigned' string)
                                            if ($moduleKey === 'task' && $selectedDevelopers === 'assigned') {
                                                $selectedDevelopers = [];
                                            }
                                            if (!is_array($selectedDevelopers)) {
                                                $selectedDevelopers = [];
                                            }
                                        @endphp

                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <div class="form-check">
                                                    <input class="form-check-input notify-all-checkbox" 
                                                           type="checkbox" 
                                                           id="notify_all_{{ $moduleKey }}"
                                                           name="recipients[{{ $moduleKey }}][created][all]"
                                                           value="1"
                                                           data-module="{{ $moduleKey }}"
                                                           {{ $notifyAll ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="notify_all_{{ $moduleKey }}">
                                                        Notify All Users
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-3" id="recipients_{{ $moduleKey }}" style="{{ $notifyAll ? 'display: none;' : '' }}">
                                            <div class="col-md-6">
                                                <label class="form-label">Roles</label>
                                                <select class="select2 form-select roles-select" 
                                                        id="roles_{{ $moduleKey }}"
                                                        name="recipients[{{ $moduleKey }}][created][roles][]"
                                                        multiple
                                                        data-placeholder="Select Roles">
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}" 
                                                                {{ in_array($role->id, $selectedRoles) ? 'selected' : '' }}>
                                                            {{ $role->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label">Developers</label>
                                                @if ($moduleKey === 'task')
                                                    <label class="form-label small text-muted mb-2 d-block">Notify Assigned Developer</label>
                                                @endif
                                                <select class="select2 form-select developers-select" 
                                                        id="developers_{{ $moduleKey }}"
                                                        name="recipients[{{ $moduleKey }}][created][developers][]"
                                                        multiple
                                                        data-placeholder="Select Developers">
                                                    @foreach ($employees as $employee)
                                                        <option value="{{ $employee->id }}" 
                                                                {{ (is_array($selectedDevelopers) && in_array($employee->id, $selectedDevelopers)) ? 'selected' : '' }}>
                                                            {{ $employee->fullname }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- / Content -->

    <!-- Floating Footer -->
    <div class="floating-footer d-flex justify-content-end align-items-center">
        <div>
            <a href="{{ route('dashboard') }}" type="button" class="btn btn-outline-dark me-2"><span
                    class="ti-xs ti ti-x me-1"></span>Cancel</a>
            <button type="submit" class="btn btn-primary me-2" id="btn-submit" form="postForm"><span
                    class="ti-xs ti ti-device-floppy me-1"></span>Save</button>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush


    @push('page-js')
        <script>
            $(document).ready(function() {
                // Initialize Select2
                $('.select2').select2({
                    placeholder: function() {
                        return $(this).data('placeholder');
                    }
                });

                // Handle "Notify All" checkbox
                $('.notify-all-checkbox').on('change', function() {
                    const module = $(this).data('module');
                    const isChecked = $(this).is(':checked');
                    const recipientsDiv = $('#recipients_' + module);
                    
                    if (isChecked) {
                        recipientsDiv.hide();
                        // Clear selections
                        $('#roles_' + module).val(null).trigger('change');
                        $('#developers_' + module).val(null).trigger('change');
                    } else {
                        recipientsDiv.show();
                    }
                });

                $('#btn-submit').on('click', function(e) {
                    $('#postForm').submit();
                });

                $('#postForm').submit(function(e) {
                    $("#postForm").block({
                        message: '<div class="spinner-border text-primary" role="status"></div>',
                        timeout: 1e3,
                        css: {
                            backgroundColor: "transparent",
                            border: "0"
                        },
                        overlayCSS: {
                            backgroundColor: "#fff",
                            opacity: .8
                        }
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>

