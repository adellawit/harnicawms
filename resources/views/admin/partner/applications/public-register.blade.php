<x-guest-layout>
    @section('title', 'Daftar Partner | ')

    <div class="d-flex col-12 align-items-center authentication-bg p-sm-5 p-4">
        <div class="w-100 mx-auto" style="max-width: 760px">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-2">Pendaftaran Partner</h4>
                    <p class="text-muted">Daftar sebagai calon Agent atau Reseller. Tim kami akan melakukan follow-up setelah data dikirim.</p>

                    @if ($errors->any())
                        <x-alert type="danger" class="mb-3"><ul class="m-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></x-alert>
                    @endif

                    <form method="POST" action="{{ route('partner.register.store') }}" enctype="multipart/form-data">
                        @csrf
                        @include('admin.partner.applications._form')
                        <button class="btn btn-primary w-100 mt-3">Kirim Pendaftaran</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
