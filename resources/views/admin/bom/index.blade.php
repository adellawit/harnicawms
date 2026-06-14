<x-app-layout>
    @section('title', 'Bill of Materials | ')

    <div class="container-xxl flex-grow-1 container-p-y">
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Produksi'],
                ['label' => 'Bill of Materials', 'active' => true],
            ]"
        />

        @if (session('success'))<x-alert type="success" class="mb-3">{{ session('success') }}</x-alert>@endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Resep Produksi (BOM)</h5>
                <a href="{{ route('bom.create') }}" class="btn btn-primary btn-sm"><i class="ti ti-plus me-1"></i> Buat BOM</a>
            </div>
            <div class="table-responsive text-nowrap">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nama Resep</th>
                            <th>Produk Jadi</th>
                            <th class="text-end">Output</th>
                            <th class="text-center">Jml Bahan</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($boms as $bom)
                            <tr>
                                <td>{{ $bom->name }}</td>
                                <td>{{ $bom->variant?->display_name ?? $bom->product?->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($bom->output_quantity, 2), '0'), '.') }}</td>
                                <td class="text-center">{{ $bom->items->count() }}</td>
                                <td>@if($bom->is_active)<span class="badge bg-label-success">Aktif</span>@else<span class="badge bg-label-secondary">Nonaktif</span>@endif</td>
                                <td class="text-end">
                                    <a href="{{ route('bom.show', $bom->id) }}" class="btn btn-sm btn-icon btn-outline-primary"><i class="ti ti-eye"></i></a>
                                    <form method="POST" action="{{ route('bom.destroy', $bom->id) }}" class="d-inline" onsubmit="return confirm('Hapus BOM ini?')">
                                        @csrf
                                        <button class="btn btn-sm btn-icon btn-outline-danger"><i class="ti ti-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada resep. Klik "Buat BOM".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
