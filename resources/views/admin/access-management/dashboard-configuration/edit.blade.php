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
    <div class="container-xxl flex-grow-1 container-p-y" style="padding-bottom: 70px !important;">

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

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <!-- Form -->
        <div class="row mb-3">
            <div class="col-md mb-4 mb-md-2">
                <div class="card accordion mt-3" id="accordionWithIcon">
                    <h5 class="card-header fw-bold" style="color: #212529">
                        Dashboard Configuration - {{ $role->name }}
                    </h5>
                    <form method="POST" action="{{ route('dashboard-configuration.update') }}" id="postForm">
                        @csrf
                        <input type="hidden" name="role_id" value="{{ $role->id }}">
                        <hr style="margin-bottom: 0.5rem; margin-top: 0;" />
                        
                        <div class="accordion-body p-4">
                            <p class="text-muted mb-4">
                                Pilih section dan widget yang ingin ditampilkan di dashboard untuk role <strong>{{ $role->name }}</strong>.
                                Section/widget yang tidak dicentang akan disembunyikan dari dashboard.
                            </p>

                            @foreach($dashboardSections as $sectionKey => $section)
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <div class="form-check">
                                            @php
                                                $sectionConfigKey = $sectionKey;
                                                $sectionConfig = $configurations->get($sectionConfigKey);
                                                $sectionVisible = $sectionConfig ? (bool)$sectionConfig->is_visible : false; // Default unchecked if not found
                                            @endphp
                                            <input class="form-check-input section-checkbox" 
                                                   type="checkbox" 
                                                   id="section_{{ $sectionKey }}"
                                                   data-section="{{ $sectionKey }}"
                                                   name="configurations[{{ $sectionKey }}]"
                                                   value="1"
                                                   {{ $sectionVisible ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="section_{{ $sectionKey }}">
                                                @if(isset($section['icon']))
                                                    <i class="{{ $section['icon'] }} me-2"></i>
                                                @endif
                                                {{ $section['name'] }}
                                            </label>
                                        </div>
                                    </div>
                                    @if(!empty($section['widgets']))
                                        <div class="card-body">
                                            <div class="row g-3">
                                                @foreach($section['widgets'] as $widgetKey => $widgetName)
                                                    @php
                                                        $widgetConfigKey = $sectionKey . '.' . $widgetKey;
                                                        $widgetConfig = $configurations->get($widgetConfigKey);
                                                        $widgetVisible = $widgetConfig ? (bool)$widgetConfig->is_visible : false; // Default unchecked if not found
                                                    @endphp
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input widget-checkbox" 
                                                                   type="checkbox" 
                                                                   id="widget_{{ $sectionKey }}_{{ $widgetKey }}"
                                                                   data-section="{{ $sectionKey }}"
                                                                   name="configurations[{{ $widgetConfigKey }}]"
                                                                   value="1"
                                                                   {{ $widgetVisible ? 'checked' : '' }}>
                                                            <label class="form-check-label" for="widget_{{ $sectionKey }}_{{ $widgetKey }}">
                                                                {{ $widgetName }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    
                                    {{-- Team Workload Developer Selection --}}
                                    @if($sectionKey === 'team_workload')
                                        <div class="card-body">
                                            <p class="text-muted small mb-3">Pilih developer yang dapat dilihat di dashboard untuk role ini:</p>
                                            <div class="row g-3">
                                                @foreach($employees as $employee)
                                                    @php
                                                        $isSelected = in_array($employee->id, $teamMemberConfigs ?? []);
                                                    @endphp
                                                    <div class="col-md-6">
                                                        <div class="form-check">
                                                            <input class="form-check-input team-member-checkbox" 
                                                                   type="checkbox" 
                                                                   id="team_member_{{ $employee->id }}"
                                                                   name="team_members[]"
                                                                   value="{{ $employee->id }}"
                                                                   {{ $isSelected ? 'checked' : '' }}>
                                                            <label class="form-check-label" for="team_member_{{ $employee->id }}">
                                                                {{ $employee->fullname }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!--/ Form -->
    </div>
    <!-- / Content -->

    <!-- Floating Footer -->
    <div class="floating-footer d-flex justify-content-end align-items-center">
        <div>
            <a href="{{ route('roles.index.view') }}" type="button" class="btn btn-outline-dark me-2">
                <span class="ti-xs ti ti-x me-1"></span>Cancel
            </a>
            <button type="submit" class="btn btn-primary me-2" id="btn-submit">
                <span class="ti-xs ti ti-device-floppy me-1"></span>Save
            </button>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/block-ui/block-ui.js') }}"></script>
    @endpush


    @push('page-js')
        <script>
            $(document).ready(function() {
                $('#btn-submit').on('click', function(e) {
                    $('#postForm').submit();
                });

                // Handle section checkbox - when unchecked, uncheck all widgets
                $('.section-checkbox').on('change', function() {
                    const section = $(this).data('section');
                    const isChecked = $(this).is(':checked');
                    const widgets = $(`.widget-checkbox[data-section="${section}"]`);
                    
                    widgets.prop('checked', isChecked);
                });

                // Handle widget checkbox - if any widget is checked, check the section
                $('.widget-checkbox').on('change', function() {
                    const section = $(this).data('section');
                    const sectionCheckbox = $(`#section_${section}`);
                    const widgets = $(`.widget-checkbox[data-section="${section}"]`);
                    const checkedWidgets = widgets.filter(':checked');
                    
                    if (checkedWidgets.length > 0) {
                        sectionCheckbox.prop('checked', true);
                    }
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

