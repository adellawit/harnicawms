<x-guest-layout>
    @section('title', 'Pendaftaran Diterima | ')

    <div class="d-flex col-12 align-items-center authentication-bg p-sm-5 p-4">
        <div class="w-100 mx-auto" style="max-width: 640px">
            <div class="card text-center">
                <div class="card-body">
                    <i class="ti ti-circle-check text-success" style="font-size: 56px"></i>
                    <h4 class="mt-3">Pendaftaran diterima</h4>
                    <p class="text-muted mb-1">Nomor pendaftaran Anda:</p>
                    <h5><code>{{ $application->application_number }}</code></h5>
                    <p class="text-muted">Tim distributor akan melakukan follow-up sesuai data yang dikirim.</p>
                    <a href="{{ route('partner.register') }}" class="btn btn-outline-primary">Daftar Partner Lain</a>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
