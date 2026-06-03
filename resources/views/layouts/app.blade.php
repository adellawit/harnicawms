<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light-style layout-navbar-fixed layout-menu-fixed "
    dir="ltr" data-theme="theme-semi-dark" data-assets-path="{{ asset('assets/') }}"
    data-template="vertical-menu-template-semi-dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">

    <title>@yield('title', '') {{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/wms/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/fontawesome.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/tabler-icons.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/flag-icons.css') }}" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/core.css') }}" class="template-customizer-core-css" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/rtl/theme-default.css') }}"
        class="template-customizer-theme-css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/typeahead-js/typeahead.css') }}" />

    <!-- Bootstrap CSS -->
{{--    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">--}}

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Vendor -->
    @stack('vendor-css')

    <!-- Page CSS -->
    @stack('page-css')

    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}" />

    <!-- Helpers -->
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    {{-- <script src="{{ asset('assets/vendor/js/template-customizer.js') }}"></script> --}}
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="{{ asset('assets/js/config.js') }}"></script>

    <!-- Scripts -->
    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
</head>

<body>

    @if(session('impersonator_id'))
    <div class="alert alert-info alert-dismissible mb-0 rounded-0 d-flex align-items-center justify-content-center" style="z-index: 9999; position: relative;">
        <i class="ti ti-info-circle me-2"></i>
        <span>Anda sedang login sebagai <strong>{{ auth('web')->user()->first_name }} {{ auth('web')->user()->last_name }}</strong></span>
        <form method="POST" action="{{ route('users.stop-impersonation') }}" class="d-inline ms-3">
            @csrf
            <button type="submit" class="btn btn-sm btn-dark">
                <i class="ti ti-arrow-back me-1"></i>Kembali ke Akun Saya
            </button>
        </form>
    </div>
    @endif

    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar  ">
        <div class="layout-container">

            @include('layouts.navigation')

            <!-- Layout container -->
            <div class="layout-page">

                @include('layouts.header')

                <!-- Content wrapper -->
                <div class="content-wrapper">

                    {{ $slot }}

                    @include('layouts.footer')

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->

            </div>
            <!-- / Layout page -->

        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>


        <!-- Drag Target Area To SlideIn Menu On Small Screens -->
        <div class="drag-target"></div>
    </div>

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>

    <script src="{{ asset('assets/vendor/libs/hammer/hammer.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/i18n/i18n.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>

    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    @stack('vendor-js')

    <!-- Main JS -->
    <script src="{{ asset('assets/js/main.js') }}"></script>
    <script src="{{ asset('assets/js/number-format.js') }}"></script>
    <script>
    (function mountNavbarBreadcrumb() {
        function apply() {
            var source = document.getElementById('layout-breadcrumb-source');
            var mount = document.getElementById('layout-breadcrumb-mount');
            if (!mount) return;
            if (!source) {
                mount.innerHTML = '';
                return;
            }
            mount.innerHTML = '';
            var nav = source.cloneNode(true);
            nav.removeAttribute('id');
            nav.classList.remove('d-none');
            nav.setAttribute('aria-label', 'breadcrumb');
            mount.appendChild(nav);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', apply);
        } else {
            apply();
        }
    })();
    </script>
    @stack('main-js')

    <!-- Page JS -->
    @stack('page-js')

    <!-- Bootstrap JS Bundle (Optional, for interactivity) -->
{{--    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>--}}

    <!-- Fix DataTable Dropdown - Appear Outside Table -->
    <script>
    $(document).on('show.bs.dropdown', '.dataTable .dropdown', function() {
        var $toggle = $(this).find('[data-bs-toggle="dropdown"]');
        var $menu = $(this).find('.dropdown-menu');
        var offset = $toggle.offset();

        $('body').append($menu.detach());
        $menu.css({
            'position': 'fixed',
            'top': offset.top + $toggle.outerHeight(),
            'left': offset.left - $menu.outerWidth() + $toggle.outerWidth(),
            'display': 'block'
        });
        $menu.addClass('show');
        $(this).data('dropdown-menu', $menu);
    });

    $(document).on('hide.bs.dropdown', '.dataTable .dropdown', function() {
        var $menu = $(this).data('dropdown-menu');
        if ($menu) {
            $menu.css({'display': 'none'});
            $(this).append($menu.detach());
            $menu.removeClass('show');
        }
    });
    </script>

    <!-- Additional Scripts -->
{{--    @stack('scripts')--}}

    <!-- Notification Dropdown Script -->
    <script>
    $(document).ready(function() {
        const notificationDropdown = $('#notification-dropdown-toggle');
        const notificationList = $('#notification-list');
        const notificationBadge = $('#notification-badge-count');
        const markAllReadBtn = $('#mark-all-read-btn');

        // Load notifications when dropdown is opened
        notificationDropdown.on('shown.bs.dropdown', function() {
            loadNotifications();
        });

        // Load notifications function
        function loadNotifications() {
            $.ajax({
                url: '{{ route("notifications.api.list") }}',
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        renderNotifications(response.notifications);
                        updateUnreadCount();
                    }
                },
                error: function() {
                    notificationList.html('<li class="list-group-item text-center py-4 text-muted">Failed to load notifications</li>');
                }
            });
        }

        // Render notifications
        function renderNotifications(notifications) {
            if (notifications.length === 0) {
                notificationList.html('<li class="list-group-item text-center py-4 text-muted">No notifications</li>');
                return;
            }

            let html = '';
            notifications.forEach(function(notification) {
                const typeClass = notification.type === 'error' ? 'bg-danger' :
                                  notification.type === 'warning' ? 'bg-warning' :
                                  notification.type === 'success' ? 'bg-success' : 'bg-primary';
                const unreadClass = !notification.is_read ? 'mark-as-read' : '';
                const unreadDot = !notification.is_read ? '<span class="badge badge-dot bg-danger"></span>' : '';
                const notificationUrl = notification.url || '#';

                html += `
                    <li class="list-group-item list-group-item-action dropdown-notifications-item ${unreadClass}" data-id="${notification.id}" data-read="${notification.is_read}">
                        <a href="${notificationUrl}" class="text-decoration-none text-body notification-item-link">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar">
                                        <span class="avatar-initial rounded-circle ${typeClass}">
                                            <i class="ti ti-${notification.type === 'error' ? 'alert-circle' : notification.type === 'warning' ? 'alert-triangle' : notification.type === 'success' ? 'check' : 'info-circle'}"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${notification.title}</h6>
                                    <p class="mb-0">${notification.message}</p>
                                    <small class="text-muted">${notification.created_at}</small>
                                </div>
                                <div class="flex-shrink-0 dropdown-notifications-actions">
                                    ${unreadDot}
                                </div>
                            </div>
                        </a>
                    </li>
                `;
            });
            notificationList.html(html);
        }

        // Mark notification as read
        function markAsRead(notificationId, element) {
            $.ajax({
                url: '{{ route("notifications.api.mark-read") }}',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    id: notificationId
                },
                success: function(response) {
                    if (response.success) {
                        element.removeClass('mark-as-read');
                        element.find('.badge-dot').remove();
                        element.attr('data-read', 'true');
                        updateUnreadCount();
                    }
                }
            });
        }

        // Handle notification item click
        $(document).on('click', '.notification-item-link', function(e) {
            const item = $(this).closest('.dropdown-notifications-item');
            const notificationId = item.data('id');
            const isRead = item.data('read') === true || item.data('read') === 'true';
            const url = $(this).attr('href');

            // If notification is unread, mark it as read
            if (!isRead && notificationId) {
                e.preventDefault();
                markAsRead(notificationId, item);
                // Redirect after marking as read
                if (url && url !== '#') {
                    setTimeout(function() {
                        window.location.href = url;
                    }, 300);
                }
            }
        });

        // Mark all as read
        markAllReadBtn.on('click', function(e) {
            e.preventDefault();
            $.ajax({
                url: '{{ route("notifications.api.mark-all-read") }}',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        loadNotifications();
                    }
                }
            });
        });

        // Update unread count badge
        function updateUnreadCount() {
            $.ajax({
                url: '{{ route("notifications.api.unread-count") }}',
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        const currentCount = notificationBadge.text().trim();
                        const newCount = response.count > 0 ? response.count.toString() : '';

                        // Only update if count has changed to prevent blink
                        // Normalize: treat empty string and '0' as the same (no unread)
                        const currentNum = currentCount === '' ? 0 : parseInt(currentCount) || 0;
                        const newNum = response.count || 0;

                        if (currentNum !== newNum) {
                            if (newNum > 0) {
                                notificationBadge.text(newNum).show();
                            } else {
                                notificationBadge.text('').hide();
                            }
                        }
                    }
                }
            });
        }

        // Skip update on page load - server already rendered the correct count
        // updateUnreadCount();

        // Auto-refresh unread count every 30 seconds
        setInterval(function() {
            updateUnreadCount();
        }, 30000);
    });
    </script>

</body>

{{-- <body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        @include('layouts.navigation')

        <!-- Page Heading -->
        @if (isset($header))
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>
    </div>
</body> --}}

</html>
