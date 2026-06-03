@extends('layouts.customer')

@section('title', 'My Account | ')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card border-0 shadow-sm overflow-hidden">
                <div class="card-body text-center pt-4 pb-3 border-bottom bg-light">
                    <div class="shop-account-avatar mx-auto mb-3" aria-hidden="true">
                        {{ $customer->initials() }}
                    </div>
                    <h4 class="mb-1">{{ $customer->name }}</h4>
                    <p class="text-muted small mb-0">{{ $customer->email }}</p>
                </div>
                <div class="card-body p-0">
                    <dl class="shop-account-dl mb-0">
                        <div class="shop-account-row">
                            <dt>Kode pelanggan</dt>
                            <dd>{{ $customer->code }}</dd>
                        </div>
                        @if ($customer->customerGroup)
                            <div class="shop-account-row">
                                <dt>Grup</dt>
                                <dd>{{ $customer->customerGroup->name }}</dd>
                            </div>
                        @endif
                        @if ($branch)
                            <div class="shop-account-row">
                                <dt>Cabang</dt>
                                <dd>{{ $branch->brand_name ?: $branch->name }}</dd>
                            </div>
                        @endif
                        @if ($companyName ?? null)
                            <div class="shop-account-row">
                                <dt>Perusahaan</dt>
                                <dd>{{ $companyName }}</dd>
                            </div>
                        @endif
                        @if ($customer->phone || $customer->mobile)
                            <div class="shop-account-row">
                                <dt>Telepon</dt>
                                <dd>{{ $customer->phone ?: $customer->mobile }}</dd>
                            </div>
                        @endif
                        @if ($customer->address)
                            <div class="shop-account-row">
                                <dt>Alamat</dt>
                                <dd>{{ $customer->address }}{{ $customer->city ? ', '.$customer->city : '' }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
                <div class="card-footer bg-white border-top p-3">
                    <form method="POST" action="{{ route('customer.logout') }}" class="mb-0">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="ti ti-logout me-1"></i> Keluar
                        </button>
                    </form>
                </div>
            </div>

            <a href="{{ route('customer.shop') }}" class="btn btn-link btn-sm mt-3 ps-0">
                <i class="ti ti-arrow-left"></i> Kembali ke katalog
            </a>
        </div>
    </div>
@endsection
