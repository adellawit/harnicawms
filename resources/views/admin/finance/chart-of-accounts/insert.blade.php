<x-app-layout>
    @section('title', 'Tambah COA | ')
    @push('vendor-css')
        <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
        @include('admin.finance.chart-of-accounts._styles')
    @endpush

    <div class="container-xxl flex-grow-1 container-p-y fin-report" style="padding-bottom: 70px !important;">
        <x-page-header :breadcrumbs="[
            ['label' => 'Home', 'url' => route('dashboard')],
            ['label' => 'Accounting'],
            ['label' => 'Chart of Accounts', 'url' => route('finance.chart-of-accounts.index.view')],
            ['label' => 'Tambah', 'active' => true],
        ]" />

        @if ($errors->any())
            <x-alert type="danger" class="mb-3">
                <ul class="m-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-alert>
        @endif

        <div class="card fin-toolbar mb-3">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="fin-kpi-icon bg-label-primary text-primary"><i class="ti ti-plus"></i></div>
                <div>
                    <div class="text-muted small mb-0">Tambah akun baru ke COA</div>
                    <div class="fin-company">New Chart of Account</div>
                </div>
            </div>
        </div>

        <div class="card fin-section accent-primary">
            <div class="fin-section-head">
                <div>
                    <h5 class="fin-section-title">Account details</h5>
                    <div class="fin-section-sub">Isi data akun · kode bisa digenerate otomatis</div>
                </div>
            </div>
            <form method="POST" action="{{ route('finance.chart-of-accounts.insert.data') }}" id="postForm">
                @csrf
                <div class="card-body">
                    @include('admin.finance.chart-of-accounts._form')
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <a href="{{ route('finance.chart-of-accounts.index.view') }}" class="btn btn-label-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    @push('vendor-js')
        <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
    @endpush
    @push('page-js')
        <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
        <script>
            function loadParents(companyId, selected) {
                var $parent = $('#parent_id');
                $parent.empty().append('<option value="">— Tanpa parent —</option>');
                if (!companyId) return;
                $.getJSON('{{ route('finance.chart-of-accounts.parents') }}', { company_id: companyId }, function(res) {
                    (res.data || []).forEach(function(row) {
                        var opt = $('<option>').val(row.id).text(row.label);
                        if (selected && selected == row.id) opt.prop('selected', true);
                        $parent.append(opt);
                    });
                    $parent.trigger('change');
                });
            }
            function suggestCode(force) {
                var $code = $('#code');
                if (!force && $.trim($code.val()) !== '') return;
                var companyId = $('#company_id').val();
                if (!companyId) return;
                $.getJSON('{{ route('finance.chart-of-accounts.suggest-code') }}', {
                    company_id: companyId,
                    parent_id: $('#parent_id').val() || '',
                    account_type: $('#account_type').val() || ''
                }, function(res) {
                    if (res.code && (force || $.trim($code.val()) === '')) {
                        $code.val(res.code);
                        $code.attr('placeholder', 'Saran: ' + res.code);
                    }
                });
            }
            $(document).ready(function() {
                $('#company_id').on('change', function() {
                    loadParents($(this).val(), null);
                    suggestCode(false);
                });
                $('#parent_id, #account_type').on('change', function() {
                    suggestCode(false);
                });
                if ($('#company_id').val()) {
                    loadParents($('#company_id').val(), '{{ old('parent_id') }}');
                    @if(! old('code'))
                    suggestCode(true);
                    @endif
                }
            });
        </script>
    @endpush
</x-app-layout>
