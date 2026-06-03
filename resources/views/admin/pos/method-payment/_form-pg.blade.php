@php
    $settlementType = old('settlement_type', $settlementType ?? 'manual');
    $channelCatalogByGroup = $channelCatalogByGroup ?? [];
@endphp

<div class="col-12">
    <label class="form-label" for="settlement_type">Tipe Settlement <span class="text-danger">*</span></label>
    <select id="settlement_type" name="settlement_type" class="form-select" required>
        <option value="manual" @selected($settlementType === 'manual')>Manual — tanpa Payment Gateway</option>
        <option value="pg_group" @selected($settlementType === 'pg_group')>Payment Gateway — grup (Transfer / QRIS / E-Wallet)</option>
        <option value="pg_channel" @selected($settlementType === 'pg_channel')>Payment Gateway — channel (BCA, OVO, dll)</option>
    </select>
    <small class="text-muted d-block mt-1">
        Grup PG dipakai sebagai kategori di POS. Channel PG adalah metode spesifik dari Xendit.
        @if(empty($xenditConfigured))
            <span class="text-warning">Xendit belum dikonfigurasi di .env.</span>
        @endif
    </small>
</div>

<div id="pgGroupHint" class="col-12" style="display: none;">
    <x-alert type="info" class="mb-0 py-2">
        Gunakan kode grup: <strong>TRANSFER</strong>, <strong>QRIS</strong>, atau <strong>EWALLET</strong> (sesuai katalog Xendit).
    </x-alert>
</div>

<div id="pgChannelFields" class="col-12" style="display: none;">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="payment_group_code">Grup PG <span class="text-danger">*</span></label>
            <select id="payment_group_code" name="payment_group_code" class="form-select">
                <option value="">-- Pilih grup --</option>
                @foreach($pgGroupCodes ?? [] as $groupCode)
                    <option value="{{ $groupCode }}" @selected(old('payment_group_code', $paymentGroupCode ?? '') === $groupCode)>{{ $groupCode }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="gateway_channel_code">Channel Xendit <span class="text-danger">*</span></label>
            <select id="gateway_channel_code" name="gateway_channel_code" class="form-select">
                <option value="">-- Pilih channel --</option>
            </select>
            <small class="text-muted">Daftar channel mengikuti grup yang dipilih.</small>
        </div>
    </div>
</div>

@once
@push('page-js')
<script>
(function() {
    var catalog = @json($channelCatalogByGroup);
    var oldGroup = @json(old('payment_group_code', $paymentGroupCode ?? ''));
    var oldChannel = @json(old('gateway_channel_code', $gatewayChannelCode ?? ''));

    function fillChannels(group) {
        var $sel = $('#gateway_channel_code');
        $sel.empty().append('<option value="">-- Pilih channel --</option>');
        (catalog[group] || []).forEach(function(ch) {
            var opt = $('<option></option>').val(ch.code).text(ch.label + ' (' + ch.code + ')');
            if (ch.code === oldChannel) opt.prop('selected', true);
            $sel.append(opt);
        });
    }

    function toggleSettlement() {
        var type = $('#settlement_type').val();
        var isPg = type === 'pg_group' || type === 'pg_channel';
        var isChannel = type === 'pg_channel';

        $('#pgGroupHint').toggle(type === 'pg_group');
        $('#pgChannelFields').toggle(isChannel);

        $('#payment_group_code').prop('required', isChannel);
        $('#gateway_channel_code').prop('required', isChannel);

        if (isChannel && $('#payment_group_code').val()) {
            fillChannels($('#payment_group_code').val());
        }
    }

    $(function() {
        $('#settlement_type').on('change', toggleSettlement);
        $('#payment_group_code').on('change', function() {
            fillChannels($(this).val());
            var ch = $(this).val();
            if (ch && $('#code').val() === '') {
                $('#code').val('PG_' + ($('#gateway_channel_code').val() || 'CHANNEL'));
            }
        });
        $('#gateway_channel_code').on('change', function() {
            var ch = $(this).val();
            if (ch && ($('#code').val() === '' || $('#code').val().indexOf('PG_') === 0)) {
                $('#code').val('PG_' + ch);
            }
            var label = $(this).find('option:selected').text();
            if (ch && $('#name').val() === '') {
                $('#name').val(label.split(' (')[0]);
            }
        });
        toggleSettlement();
        if (oldGroup) {
            $('#payment_group_code').val(oldGroup);
            fillChannels(oldGroup);
        }
    });
})();
</script>
@endpush
@endonce
