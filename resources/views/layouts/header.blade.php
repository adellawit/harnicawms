<!-- Navbar -->

<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
    id="layout-navbar">

    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-2 me-xl-0 d-xl-none flex-shrink-0">
        <a class="nav-item nav-link px-0" href="javascript:void(0)">
            <i class="ti ti-menu-2 ti-sm"></i>
        </a>
    </div>

    @include('layouts.partials.navbar-breadcrumb')

    <div class="navbar-nav-right d-flex align-items-center flex-shrink-0 ms-auto" id="navbar-collapse">

        <ul class="navbar-nav flex-row align-items-center">

            <!-- Notification -->
            @php
                $user = auth()->user();
                $unreadCount = \App\Services\NotificationService::getUnreadCount($user);
            @endphp
            <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" aria-expanded="false" id="notification-dropdown-toggle">
                    <i class="ti ti-bell ti-md"></i>
                    <span class="badge bg-danger rounded-pill badge-notifications" id="notification-badge-count">{{ $unreadCount > 0 ? $unreadCount : '' }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end py-0" id="notification-dropdown-menu" style="min-width: 400px; margin-top: 0 !important;">
                    <li class="dropdown-menu-header border-bottom">
                        <div class="dropdown-header d-flex align-items-center py-3">
                            <h5 class="text-body mb-0 me-auto">Notification</h5>
                            <a href="javascript:void(0)" class="dropdown-notifications-all text-body" id="mark-all-read-btn"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="Mark all as read">
                                <i class="ti ti-mail-opened fs-4"></i>
                            </a>
                        </div>
                    </li>
                    <li class="dropdown-notifications-list scrollable-container" style="max-height: 300px; overflow-y: auto;">
                        <ul class="list-group list-group-flush" id="notification-list">
                            <li class="list-group-item list-group-item-action dropdown-notifications-item text-center py-4">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown-menu-footer border-top pb-2">
                        <a href="{{ route('notifications.list.view') }}"
                            class="dropdown-item text-center text-primary fw-medium py-2">
                            <i class="ti ti-arrow-right me-1"></i>View all notifications
                        </a>
                    </li>
                </ul>
            </li>
            <!--/ Notification -->

            <!-- User -->
            @php
                $user = auth()->user();
                $fullName = $user->first_name . ' ' . $user->last_name;
                $words = explode(' ', trim($fullName));
                $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                $hasCustomImage = $user->url_image && !str_contains($user->url_image, 'user-default');
                $avatarColors = ['primary', 'success', 'danger', 'warning', 'info'];
                $colorIndex = crc32($user->id) % count($avatarColors);
                $avatarColor = $avatarColors[$colorIndex];
                $currentBranch = $user->businessUnit;
                $switchableBranches = \App\Models\BusinessUnit::whereIn('id', $user->getSwitchableBusinessUnitIds())
                    ->where('type_code', 'BRANCH')
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get(['id', 'name']);
                $canSwitchBranch = $switchableBranches->count() > 1;
            @endphp
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                        @if($hasCustomImage)
                            <div class="image-crop-header rounded-circle border-primary">
                                <img src="{{ $user->url_image }}" alt="user-avatar" id="uploadedAvatar" />
                            </div>
                        @else
                            <span class="avatar-initial rounded-circle bg-label-{{ $avatarColor }}">{{ $initials }}</span>
                        @endif
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="/account">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar avatar-online">
                                        @if($hasCustomImage)
                                            <div class="image-crop-header rounded-circle border-primary">
                                                <img src="{{ $user->url_image }}" alt="user-avatar" id="uploadedAvatar" />
                                            </div>
                                        @else
                                            <span class="avatar-initial rounded-circle bg-label-{{ $avatarColor }}">{{ $initials }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <span class="fw-semibold d-block">{{ $fullName }}</span>
                                    <small class="text-muted">{{ $user->role->name }}</small>
                                    @if($currentBranch)
                                        <small class="text-primary d-block"><i class="ti ti-map-pin" style="font-size: 0.7rem;"></i> {{ $currentBranch->name }}</small>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <div class="dropdown-divider"></div>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.view') }}">
                            <i class="ti ti-user-check me-2 ti-sm"></i>
                            <span class="align-middle">My Account</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.change-password-view') }}">
                            <i class="ti ti-lock me-2 ti-sm"></i>
                            <span class="align-middle">Change Password</span>
                        </a>
                    </li>
                    @if($canSwitchBranch)
                    <li>
                        <div class="dropdown-divider"></div>
                    </li>
                    <li>
                        <h6 class="dropdown-header px-3 py-1 text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <i class="ti ti-map-pin me-1"></i>Change Branch
                        </h6>
                    </li>
                    <li style="max-height: 200px; overflow-y: auto;">
                        @foreach($switchableBranches as $branch)
                            @if($branch->id === $user->current_business_unit_id)
                                <span class="dropdown-item active d-flex align-items-center" style="font-size: 0.85rem;">
                                    <i class="ti ti-circle-check me-2 ti-sm"></i>
                                    <span class="align-middle">{{ $branch->name }}</span>
                                </span>
                            @else
                                <a class="dropdown-item d-flex align-items-center" href="javascript:void(0);"
                                   onclick="document.getElementById('switch-branch-{{ $loop->index }}').submit();"
                                   style="font-size: 0.85rem;">
                                    <i class="ti ti-circle me-2 ti-sm text-muted"></i>
                                    <span class="align-middle">{{ $branch->name }}</span>
                                </a>
                                <form id="switch-branch-{{ $loop->index }}" action="{{ route('profile.switch-branch') }}" method="POST" class="d-none">
                                    @csrf
                                    <input type="hidden" name="business_unit_id" value="{{ $branch->id }}" />
                                </form>
                            @endif
                        @endforeach
                    </li>
                    <li>
                        <a class="dropdown-item text-center text-primary" href="{{ route('profile.change-branch-view') }}" style="font-size: 0.8rem;">
                            <i class="ti ti-arrow-right me-1"></i> Lihat Semua
                        </a>
                    </li>
                    @endif
                    <li>
                        <div class="dropdown-divider"></div>
                    </li>
                    <li>
                        <a href="{{ route('logout') }}"
                            onclick="event.preventDefault();
                                                    document.getElementById('logout-form').submit();"
                            class="dropdown-item">
                            <i class="ti ti-logout me-2 ti-sm"></i>
                            <span class="align-middle">Logout</span>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </a>
                    </li>
                </ul>
            </li>
            <!--/ User -->

        </ul>
    </div>

</nav>

<!-- / Navbar -->
