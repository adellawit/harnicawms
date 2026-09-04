<x-app-layout>

    @section('title', 'Dashboard Configuration | ')

    @push('vendor-css')
    @endpush

    @push('page-css')
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
                ['label' => 'Settings', 'url' => 'javascript:void(0);'],
                ['label' => 'Dashboard Configuration', 'active' => true]
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        <!-- Table -->
        <div class="card">
            <h4 class="card-header" style="color: #212529">Configure Dashboard</h5>
            <div class="card-datatable text-nowrap">
                <table class="table table-bordered" id="roleListTable">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Role Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- / Table -->

    </div>
    <!-- / Content -->

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/datatables/jquery.dataTables.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive/datatables.responsive.js') }}"></script>
        <script src="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.js') }}"></script>
    @endpush


    @push('page-js')
        <script>
            $(document).ready(function() {
                var roleListTable = $('#roleListTable').DataTable({
                    processing: true,
                    serverSide: true,
                    paging: true,
                     
                    ajax: {
                        url: "{{ route('roles.index.data') }}",
                        type: "POST",
                        data: function(d) {
                            d._token = "{{ csrf_token() }}";
                            d.status = 'active'; // Only show active roles
                        }
                    },
                    language: {
                        lengthMenu: "Tampilkan _MENU_",
                        zeroRecords: "Tidak ada data yang cocok dengan pencarian",
                        info: "Menampilkan _START_–_END_ dari _TOTAL_ data",
                        infoEmpty: "Tidak ada data",
                        infoFiltered: "(disaring dari _MAX_ data)",
                        search: "",
                        paginate: {
                            first: "Awal",
                            last: "Akhir",
                            next: "›",
                            previous: "‹"
                        }
                    },
                    columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                    }, {
                        data: 'name',
                        orderable: true,
                        searchable: true,
                    }, {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return '<a href="{{ url("access-management/dashboard-configuration") }}/' + row.id + '" class="btn btn-sm btn-primary">' +
                                   '<i class="ti ti-settings me-1"></i>Configure</a>';
                        }
                    }],
                    displayLength: 10,
                    lengthMenu: [10, 25, 50, 100]
                });
            });
        </script>
    @endpush

</x-app-layout>

