<x-app-layout>
    @section('title', 'Change Branch | ')

    @push('page-css')
        <style>
            .branch-card {
                cursor: pointer;
                transition: all 0.2s ease;
                border: 2px solid transparent;
            }
            .branch-card:hover {
                border-color: #7367f0;
                box-shadow: 0 0.25rem 1rem rgba(115, 103, 240, 0.18);
            }
            .branch-card.active-branch {
                border-color: #7367f0;
                background-color: rgba(115, 103, 240, 0.06);
            }
        </style>
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Change Branch', 'active' => true]
            ]"
        />

        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        @if($holding || $company)
        <div class="row mb-4">
            @if($holding)
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge bg-label-primary me-2">HOLDING</span>
                            <h6 class="mb-0">{{ $holding->name }}</h6>
                        </div>
                        @if($holding->code)
                            <small class="text-muted">Kode: {{ $holding->code }}</small>
                        @endif
                    </div>
                </div>
            </div>
            @endif
            @if($company)
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge bg-label-info me-2">COMPANY</span>
                            <h6 class="mb-0">{{ $company->name }}</h6>
                        </div>
                        @if($company->code)
                            <small class="text-muted">Kode: {{ $company->code }}</small>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="ti ti-switch-horizontal me-2"></i>Pilih Branch
                </h5>
                @if($currentBranch)
                    <p class="text-muted mb-0 mt-1">
                        Lokasi aktif saat ini: <strong class="text-primary">{{ $currentBranch->name }}</strong>
                    </p>
                @endif
            </div>
            <div class="card-body">
                @if($branches->count() > 0)
                    <div class="row g-3">
                        @foreach($branches as $branch)
                            <div class="col-md-4 col-sm-6">
                                <div class="card branch-card h-100 {{ $branch->id === $user->current_business_unit_id ? 'active-branch' : '' }}"
                                     onclick="{{ $branch->id !== $user->current_business_unit_id ? "document.getElementById('switch-form-{$loop->index}').submit();" : '' }}">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-start">
                                            <div class="avatar avatar-sm me-3 flex-shrink-0 mt-1">
                                                @if($branch->id === $user->current_business_unit_id)
                                                    <span class="avatar-initial rounded bg-primary"><i class="ti ti-check ti-xs"></i></span>
                                                @else
                                                    <span class="avatar-initial rounded bg-label-secondary"><i class="ti ti-building-store ti-xs"></i></span>
                                                @endif
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">{{ $branch->name }}</h6>
                                                @if($branch->id === $user->current_business_unit_id)
                                                    <span class="badge bg-primary">Aktif</span>
                                                @else
                                                    <small class="text-muted">Klik untuk pindah</small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @if($branch->id !== $user->current_business_unit_id)
                                    <form id="switch-form-{{ $loop->index }}" action="{{ route('profile.switch-branch') }}" method="POST" class="d-none">
                                        @csrf
                                        <input type="hidden" name="business_unit_id" value="{{ $branch->id }}" />
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="ti ti-building-store ti-lg text-muted mb-2 d-block"></i>
                        <p class="text-muted mb-0">Tidak ada branch yang tersedia untuk dipilih.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
