<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Jenis Partner <span class="text-danger">*</span></label>
        <select name="partner_type" class="form-select" required>
            <option value="">-- Pilih --</option>
            <option value="AGENT" @selected(old('partner_type') === 'AGENT')>Agent</option>
            <option value="RESELLER" @selected(old('partner_type') === 'RESELLER')>Reseller</option>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Jumlah Pembelian Produk</label>
        <input type="number" min="0" step="any" name="requested_purchase_quantity" class="form-control" value="{{ old('requested_purchase_quantity', 0) }}" placeholder="Contoh: 100">
        <small class="text-muted">Isi estimasi quantity produk, bukan nominal rupiah.</small>
    </div>
    <div class="col-md-6">
        <label class="form-label">Nama <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">No. Telepon <span class="text-danger">*</span></label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Kota</label>
        <input type="text" name="city" class="form-control" value="{{ old('city') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Provinsi</label>
        <input type="text" name="province" class="form-control" value="{{ old('province') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Kode Pos</label>
        <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code') }}">
    </div>
    <div class="col-12">
        <label class="form-label">Alamat</label>
        <textarea name="address" rows="2" class="form-control">{{ old('address') }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Upload Persyaratan</label>
        <input type="file" name="documents[]" class="form-control" multiple>
        <small class="text-muted">Maksimal 5MB per file.</small>
    </div>
    <div class="col-12">
        <label class="form-label">Catatan</label>
        <textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea>
    </div>
</div>
