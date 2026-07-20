{{-- Sidebar Menu --}}
@php
    $authUser        = auth()->user();
    $currentUnit     = optional($authUser)->businessUnit;
    $companyLabel    = config('app.name', 'Company');
    $branchLabel     = null;

    if ($currentUnit) {
        if ($currentUnit->type_code === 'BRANCH') {
            $companyUnit = $currentUnit->relationLoaded('parent')
                ? $currentUnit->parent
                : $currentUnit->parent()->first();
            $companyLabel = $companyUnit?->name ?? $currentUnit->name;
            $branchLabel = $currentUnit->name;
        } else {
            $companyLabel = $currentUnit->name;
        }
    }

    $switchableIds   = $authUser ? $authUser->getSwitchableBusinessUnitIds() : [];
    $switchableBranches = collect();

    if ($authUser && !empty($switchableIds)) {
        $switchableBranches = \App\Models\BusinessUnit::whereIn('id', $switchableIds)
            ->where('type_code', 'BRANCH')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    $canSwitchBranch = $switchableBranches->count() > 1;
@endphp

<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">

    {{-- Workspace brand (static, non-clickable) --}}
    <div class="app-brand demo workspace-switcher">
        <div class="workspace-card workspace-card--brand workspace-dropdown-wrap is-static" aria-disabled="true">
            <div class="workspace-logo workspace-logo--brand">
                <img src="{{ $appTheme['logo_url'] ?? asset('assets/img/harnica/logo.png') }}" alt="Harnica" class="workspace-brand-logo" data-brand-logo="{{ $appTheme['logo_url'] ?? asset('assets/img/harnica/logo.png') }}">
            </div>
            <div class="workspace-meta workspace-meta--brand">
                <span class="workspace-company">{{ $companyLabel }}</span>
                @if ($branchLabel)
                    <span class="workspace-branch">{{ $branchLabel }}</span>
                @endif
            </div>
        </div>

        {{-- Sidebar collapse toggle: hamburger on desktop, X on mobile --}}
        <a href="javascript:void(0);"
           class="layout-menu-toggle menu-link workspace-collapse-btn"
           aria-label="Toggle sidebar">
            <i class="ti ti-menu-2 d-none d-xl-block ti-sm align-middle"></i>
            <i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-2">
        @php
            $sidebars = session('sidebars', collect());

            $flattenMenus = function ($menus) use (&$flattenMenus) {
                $flat = collect();
                foreach ($menus as $menu) {
                    $flat->push($menu);
                    if ($menu->relationLoaded('children') && $menu->children->isNotEmpty()) {
                        $flat = $flat->merge($flattenMenus($menu->children));
                    }
                }

                return $flat;
            };

            $allMenuItems = $flattenMenus($sidebars);

            $checkMenuActive = function ($menu) use ($allMenuItems) {
                if (($menu->code ?? '') === 'training-academy' && ! request()->is('training/settings*')) {
                    if (request()->is('training/*') || request()->is('academy') || request()->is('academy/*')) {
                        return true;
                    }
                }

                if ($menu->route_name && Route::has($menu->route_name)) {
                    if (request()->routeIs($menu->route_name) || request()->routeIs($menu->route_name . '.*')) {
                        return true;
                    }
                }

                if ($menu->url_path) {
                    $path = trim($menu->url_path, '/');
                    if ($path === '') {
                        return false;
                    }

                    if (request()->is($path)) {
                        return true;
                    }

                    if (request()->is($path . '/*')) {
                        $currentPath = trim(request()->path(), '/');

                        $moreSpecificMatch = $allMenuItems
                            ->filter(fn ($other) => $other->id !== $menu->id && filled($other->url_path))
                            ->contains(function ($other) use ($path, $currentPath) {
                                $otherPath = trim($other->url_path, '/');
                                if ($otherPath === $path || ! str_starts_with($otherPath, $path . '/')) {
                                    return false;
                                }

                                return $currentPath === $otherPath || str_starts_with($currentPath, $otherPath . '/');
                            });

                        return ! $moreSpecificMatch;
                    }
                }

                return false;
            };

            $checkActiveDescendant = function ($menu) use (&$checkActiveDescendant, $checkMenuActive) {
                if (!$menu->relationLoaded('children') || $menu->children->isEmpty()) {
                    return false;
                }
                foreach ($menu->children as $child) {
                    if ($checkMenuActive($child)) return true;
                    if ($child->has_page && $checkActiveDescendant($child)) return true;
                }
                return false;
            };
        @endphp

        @if (!empty($sidebars) && $sidebars->count())
            @foreach ($sidebars->where('parent_id', null)->sortBy('order_number') as $sidebar)
                {{-- Section header (db-flagged labels) --}}
                @if (!empty($sidebar->is_label))
                    <li class="menu-header small text-uppercase">
                        <span class="menu-header-text">{{ $sidebar->name }}</span>
                    </li>
                    @continue
                @endif

                @php
                    $parentUrl = $sidebar->route_name && Route::has($sidebar->route_name)
                        ? route($sidebar->route_name)
                        : url($sidebar->url_path);
                    $isParentActive = $checkMenuActive($sidebar) || $checkActiveDescendant($sidebar);
                @endphp

                <li class="menu-item @if ($isParentActive) active {{ $sidebar->has_page ? 'open' : '' }} @endif">
                    @if ($sidebar->has_page)
                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                            <i class="menu-icon tf-icons {{ $sidebar->icon }}"></i>
                            <div data-i18n="{{ $sidebar->name }}">{{ $sidebar->name }}</div>
                        </a>
                        <ul class="menu-sub">
                            @foreach ($sidebar->children->sortBy('order_number') as $child)
                                @if (!empty($child->is_label))
                                    <li class="menu-header small text-uppercase">
                                        <span class="menu-header-text">{{ $child->name }}</span>
                                    </li>
                                    @continue
                                @endif

                                @php
                                    $childUrl = $child->route_name && Route::has($child->route_name)
                                        ? route($child->route_name)
                                        : url($child->url_path);
                                    $isChildActive    = $checkMenuActive($child) || $checkActiveDescendant($child);
                                    $hasGrandChildren = $child->has_page
                                        && $child->relationLoaded('children')
                                        && $child->children->count() > 0;
                                @endphp

                                @if ($hasGrandChildren)
                                    {{-- Level 2 sub-group with children (3-level nesting) --}}
                                    <li class="menu-item @if ($isChildActive) active open @endif">
                                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                                            <div data-i18n="{{ $child->name }}">{{ $child->name }}</div>
                                        </a>
                                        <ul class="menu-sub">
                                            @foreach ($child->children->sortBy('order_number') as $grandChild)
                                                @php
                                                    $grandChildUrl = $grandChild->route_name && Route::has($grandChild->route_name)
                                                        ? route($grandChild->route_name)
                                                        : url($grandChild->url_path);
                                                    $isGrandChildActive = $checkMenuActive($grandChild) || $checkActiveDescendant($grandChild);
                                                    $hasGreatGrandChildren = $grandChild->has_page
                                                        && $grandChild->relationLoaded('children')
                                                        && $grandChild->children->count() > 0;
                                                @endphp
                                                @if ($hasGreatGrandChildren)
                                                    <li class="menu-item @if ($isGrandChildActive) active open @endif">
                                                        <a href="javascript:void(0);" class="menu-link menu-toggle">
                                                            <div data-i18n="{{ $grandChild->name }}">{{ $grandChild->name }}</div>
                                                        </a>
                                                        <ul class="menu-sub">
                                                            @foreach ($grandChild->children->sortBy('order_number') as $greatGrandChild)
                                                                @php
                                                                    $greatGrandChildUrl = $greatGrandChild->route_name && Route::has($greatGrandChild->route_name)
                                                                        ? route($greatGrandChild->route_name)
                                                                        : url($greatGrandChild->url_path);
                                                                    $isGreatGrandChildActive = $checkMenuActive($greatGrandChild);
                                                                @endphp
                                                                <li class="menu-item @if ($isGreatGrandChildActive) active @endif">
                                                                    <a href="{{ $greatGrandChildUrl }}" class="menu-link">
                                                                        <div data-i18n="{{ $greatGrandChild->name }}">{{ $greatGrandChild->name }}</div>
                                                                    </a>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </li>
                                                @else
                                                    <li class="menu-item @if ($isGrandChildActive) active @endif">
                                                        <a href="{{ $grandChildUrl }}" class="menu-link">
                                                            <div data-i18n="{{ $grandChild->name }}">{{ $grandChild->name }}</div>
                                                        </a>
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
                                    </li>
                                @else
                                    {{-- Level 2 leaf item --}}
                                    <li class="menu-item @if ($isChildActive) active @endif">
                                        <a href="{{ $childUrl }}" class="menu-link">
                                            <div data-i18n="{{ $child->name }}">{{ $child->name }}</div>
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @else
                        <a href="{{ $parentUrl }}" class="menu-link">
                            <i class="menu-icon tf-icons {{ $sidebar->icon }}"></i>
                            <div data-i18n="{{ $sidebar->name }}">{{ $sidebar->name }}</div>
                        </a>
                    @endif
                </li>
            @endforeach
        @else
            <li class="menu-item">
                <div class="menu-link text-muted">No menu available</div>
            </li>
        @endif
    </ul>
</aside>

{{-- /Sidebar Menu --}}
