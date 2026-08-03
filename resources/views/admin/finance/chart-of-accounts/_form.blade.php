@php
    $isEdit = isset($account);
    $formAction = $isEdit
        ? route('finance.chart-of-accounts.edit.data')
        : route('finance.chart-of-accounts.insert.data');
@endphp

<input type="hidden" name="id" value="{{ $isEdit ? $account->id : '' }}">

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="company_id">Company <span class="text-danger">*</span></label>
        <select name="company_id" id="company_id" class="form-select select2" required @disabled($isEdit && $account->trashed())>
            <option value="">Pilih company</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected(old('company_id', $isEdit ? $account->company_id : '') == $c->id)>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="code">Kode</label>
        <input type="text" name="code" id="code" class="form-control" maxlength="50"
            value="{{ old('code', $isEdit ? $account->code : '') }}"
            placeholder="Otomatis jika kosong">
        <div class="form-text">Kosongkan untuk generate otomatis dari parent / tipe akun.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="normal_balance">Saldo Normal <span class="text-danger">*</span></label>
        <select name="normal_balance" id="normal_balance" class="form-select select2" required>
            @foreach($normalBalances as $code => $label)
                <option value="{{ $code }}" @selected(old('normal_balance', $isEdit ? $account->normal_balance : 'debit') === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="name">Nama Akun <span class="text-danger">*</span></label>
        <input type="text" name="name" id="name" class="form-control" maxlength="255" required
            value="{{ old('name', $isEdit ? $account->name : '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="account_type">Tipe Akun <span class="text-danger">*</span></label>
        <select name="account_type" id="account_type" class="form-select select2" required>
            @foreach($accountTypes as $code => $label)
                <option value="{{ $code }}" @selected(old('account_type', $isEdit ? $account->account_type : '') === $code)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="parent_id">Parent (Header)</label>
        <select name="parent_id" id="parent_id" class="form-select select2" data-allow-clear="true">
            <option value="">— Tanpa parent —</option>
            @foreach($parents as $p)
                <option value="{{ $p->id }}" @selected(old('parent_id', $isEdit ? $account->parent_id : '') == $p->id)>
                    {{ $p->displayLabel() }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Hanya akun header yang bisa jadi parent.</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="cash_flow_category_id">Cash Flow Kategori</label>
        <select name="cash_flow_category_id" id="cash_flow_category_id" class="form-select select2" data-allow-clear="true">
            <option value="">— Pilih —</option>
            @foreach($cashFlowCategories as $cf)
                <option value="{{ $cf->id }}" @selected(old('cash_flow_category_id', $isEdit ? $account->cash_flow_category_id : '') == $cf->id)>
                    {{ $cf->displayLabel() }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <div class="form-check mt-4">
            <input type="hidden" name="is_header" value="0">
            <input class="form-check-input" type="checkbox" name="is_header" id="is_header" value="1"
                @checked(old('is_header', $isEdit ? $account->is_header : false))>
            <label class="form-check-label" for="is_header">Akun Header</label>
        </div>
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <div class="form-check mt-4">
            <input type="hidden" name="is_contra_account" value="0">
            <input class="form-check-input" type="checkbox" name="is_contra_account" id="is_contra_account" value="1"
                @checked(old('is_contra_account', $isEdit ? $account->is_contra_account : false))>
            <label class="form-check-label" for="is_contra_account">Contra Account</label>
        </div>
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <div class="form-check mt-4">
            <input type="hidden" name="is_cash_bank" value="0">
            <input class="form-check-input" type="checkbox" name="is_cash_bank" id="is_cash_bank" value="1"
                @checked(old('is_cash_bank', $isEdit ? $account->is_cash_bank : false))>
            <label class="form-check-label" for="is_cash_bank">Cash / Bank</label>
        </div>
    </div>
    <div class="col-md-3 d-flex align-items-center">
        <div class="form-check mt-4">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                @checked(old('is_active', $isEdit ? $account->is_active : true))>
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>
