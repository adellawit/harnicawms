<x-app-layout>

    @section('title', 'Import Data | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}" />
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
                ['label' => 'Import Data', 'active' => true]
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

        <div class="row">
            <!-- Employee Import Card -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="ti ti-users me-2"></i>Import Employee</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" action="{{ route('users.import.data') }}" id="formEmployee" class="import-form">
                            @csrf
                            <div class="mb-3">
                                <label for="file_employee" class="form-label">Select Excel File<span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="file_employee" name="file" required accept=".xlsx,.xls,.csv">
                                <div class="form-text">Supported formats: .xlsx, .xls, .csv (Max: 5MB)</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('users.export.data') }}" class="btn btn-success btn-sm">
                                    <i class="ti ti-download me-1"></i>Download Template
                                </a>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="ti ti-upload me-1"></i>Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Client Import Card -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="ti ti-building me-2"></i>Import Project</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" action="{{ route('client.import.data') }}" id="formClient" class="import-form">
                            @csrf
                            <div class="mb-3">
                                <label for="file_client" class="form-label">Select Excel File<span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="file_client" name="file" required accept=".xlsx,.xls,.csv">
                                <div class="form-text">Supported formats: .xlsx, .xls, .csv (Max: 5MB)</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('client.download.template') }}" class="btn btn-success btn-sm">
                                    <i class="ti ti-download me-1"></i>Download Template
                                </a>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="ti ti-upload me-1"></i>Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Task Import Card -->
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="ti ti-file-check me-2"></i>Import Task</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" action="{{ route('task.import.data') }}" id="formTask" class="import-form">
                            @csrf
                            <div class="mb-3">
                                <label for="file_task" class="form-label">Select Excel File<span class="text-danger">*</span></label>
                                <input class="form-control" type="file" id="file_task" name="file" required accept=".xlsx,.xls,.csv">
                                <div class="form-text">Supported formats: .xlsx, .xls, .csv (Max: 5MB)</div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('task.download.template') }}" class="btn btn-success btn-sm">
                                    <i class="ti ti-download me-1"></i>Download Template
                                </a>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="ti ti-upload me-1"></i>Import
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- / Content -->

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush


    @push('page-js')
        <script>
            $(document).ready(function() {
                // Handle form submissions
                $('.import-form').on('submit', function(e) {
                    const form = $(this);
                    form.block({
                        message: '<div class="spinner-border text-primary" role="status"></div><p class="mt-2">Importing data...</p>',
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

