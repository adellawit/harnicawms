<x-app-layout>

    @section('title', 'Notifications | ')

    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
    @endpush

    @push('page-css')
        <style>
            .breadcrumb-item a:hover {
                color: #212529 !important;
            }
            .notification-item {
                transition: background-color 0.2s;
            }
            .notification-item:hover {
                background-color: #f8f9fa;
            }
            .notification-unread {
                background-color: #f0f4ff;
                border-left: 3px solid #696cff;
            }
            .notification-read {
                opacity: 0.8;
            }
        </style>
    @endpush

    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">

        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Notifications', 'active' => true]
            ]"
        />

        @if (session('success'))
            <x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>
        @endif

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('notifications.list.view') }}" id="filterForm">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Status</label>
                            <select class="select2 form-select" name="filter_read" id="filter_read" data-allow-clear="true" data-placeholder="All Status">
                                <option value="">All</option>
                                <option value="unread" {{ $filterRead == 'unread' ? 'selected' : '' }}>Unread</option>
                                <option value="read" {{ $filterRead == 'read' ? 'selected' : '' }}>Read</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter by Type</label>
                            <select class="select2 form-select" name="filter_type" id="filter_type" data-allow-clear="true" data-placeholder="All Types">
                                <option value="">All Types</option>
                                <option value="info" {{ $filterType == 'info' ? 'selected' : '' }}>Info</option>
                                <option value="success" {{ $filterType == 'success' ? 'selected' : '' }}>Success</option>
                                <option value="warning" {{ $filterType == 'warning' ? 'selected' : '' }}>Warning</option>
                                <option value="error" {{ $filterType == 'error' ? 'selected' : '' }}>Error</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="ti ti-filter me-1"></i>Filter
                            </button>
                            <a href="{{ route('notifications.list.view') }}" class="btn btn-secondary">
                                <i class="ti ti-refresh me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- / Filters -->

        <!-- Notification List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">My Notifications</h5>
                <div>
                    <form action="{{ route('notifications.api.mark-all-read') }}" method="POST" class="d-inline" id="markAllReadForm">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="ti ti-mail-opened me-1"></i>Mark All as Read
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                @if($notifications->count() > 0)
                    <div class="list-group list-group-flush">
                        @foreach($notifications as $notification)
                            @php
                                $typeClass = $notification->type === 'error' ? 'danger' : 
                                             ($notification->type === 'warning' ? 'warning' : 
                                             ($notification->type === 'success' ? 'success' : 'info'));
                                $typeIcon = $notification->type === 'error' ? 'alert-circle' : 
                                           ($notification->type === 'warning' ? 'alert-triangle' : 
                                           ($notification->type === 'success' ? 'check' : 'info-circle'));
                            @endphp
                            <div class="list-group-item notification-item {{ !$notification->is_read ? 'notification-unread' : 'notification-read' }}">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 me-3">
                                        <div class="avatar">
                                            <span class="avatar-initial rounded-circle bg-{{ $typeClass }}">
                                                <i class="ti ti-{{ $typeIcon }}"></i>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0">{{ $notification->title }}</h6>
                                            <div class="d-flex align-items-center">
                                                @if(!$notification->is_read)
                                                    <span class="badge bg-label-warning me-2">Unread</span>
                                                @else
                                                    <span class="badge bg-label-success me-2">Read</span>
                                                @endif
                                                <small class="text-muted" title="{{ $notification->created_at->format('Y-m-d H:i:s') }}">
                                                    {{ $notification->created_at->diffForHumans() }}
                                                </small>
                                            </div>
                                        </div>
                                        <p class="mb-2 text-body">{{ $notification->message }}</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                @if($notification->module)
                                                    <span class="badge bg-label-secondary">
                                                        {{ ucfirst(str_replace('_', ' ', $notification->module)) }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div>
                                                @if($notification->url)
                                                    <a href="{{ $notification->url }}" class="btn btn-sm btn-primary me-1">
                                                        <i class="ti ti-eye me-1"></i>View
                                                    </a>
                                                @endif
                                                @if(!$notification->is_read)
                                                    <form action="{{ route('notifications.api.mark-read') }}" method="POST" class="d-inline mark-read-form">
                                                        @csrf
                                                        <input type="hidden" name="id" value="{{ $notification->id }}">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="ti ti-check me-1"></i>Mark as Read
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="ti ti-bell-off ti-lg text-muted mb-3" style="font-size: 3rem;"></i>
                        <p class="text-muted mb-0">No notifications found</p>
                    </div>
                @endif
            </div>
            @if($notifications->hasPages())
                <div class="card-footer">
                    {{ $notifications->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
        <!-- / Notification List -->

    </div>
    <!-- / Content -->

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush

    @push('page-js')
        <script>
            $(document).ready(function() {
                // Initialize Select2
                $('.select2').select2({
                    allowClear: true,
                    placeholder: function() {
                        return $(this).data('placeholder');
                    }
                });

                // Handle mark as read form submission via AJAX
                $('.mark-read-form').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    var item = form.closest('.notification-item');
                    
                    $.ajax({
                        url: form.attr('action'),
                        method: 'POST',
                        data: form.serialize(),
                        success: function(response) {
                            if (response.success) {
                                item.removeClass('notification-unread').addClass('notification-read');
                                item.find('.badge.bg-label-warning').removeClass('bg-label-warning').addClass('bg-label-success').text('Read');
                                form.remove();
                            }
                        }
                    });
                });

                // Handle mark all as read
                $('#markAllReadForm').on('submit', function(e) {
                    e.preventDefault();
                    var form = $(this);
                    
                    $.ajax({
                        url: form.attr('action'),
                        method: 'POST',
                        data: form.serialize(),
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            }
                        }
                    });
                });
            });
        </script>
    @endpush

</x-app-layout>
