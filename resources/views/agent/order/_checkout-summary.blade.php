<div class="d-flex justify-content-between small mb-1">
    <span>Subtotal</span>
    <span id="checkoutSubtotal">Rp {{ number_format($summary['subtotal'], 0, ',', '.') }}</span>
</div>
@if ($summary['tax_enabled'])
    <div class="d-flex justify-content-between small text-muted mb-2">
        <span>PPN ({{ $summary['tax_rate'] }}%)</span>
        <span id="checkoutTax">Rp {{ number_format($summary['tax_amount'], 0, ',', '.') }}</span>
    </div>
@endif
<div class="d-flex justify-content-between small text-muted mb-2">
    <span>Ongkos Kirim</span>
    <span id="checkoutShipping">Rp {{ number_format($shippingAmount ?? 0, 0, ',', '.') }}</span>
</div>
<div class="d-flex justify-content-between fw-bold fs-5">
    <span>Total</span>
    <span class="text-primary" id="checkoutTotal">Rp {{ number_format($summary['total'] + ($shippingAmount ?? 0), 0, ',', '.') }}</span>
</div>
