@if (($summary['item_count'] ?? 0) > 0)
    <div class="d-flex justify-content-between small mb-1">
        <span>Subtotal ({{ $summary['item_count'] }} item)</span>
        <span>Rp {{ number_format($summary['subtotal'], 0, ',', '.') }}</span>
    </div>
    @if ($summary['tax_enabled'] ?? false)
        <div class="d-flex justify-content-between small text-muted mb-1">
            <span>PPN ({{ $summary['tax_rate'] }}%)</span>
            <span>Rp {{ number_format($summary['tax_amount'], 0, ',', '.') }}</span>
        </div>
    @endif
    <div class="d-flex justify-content-between small text-muted mb-2">
        <span>Estimasi ongkir</span>
        <span>Rp 0</span>
    </div>
    <div class="d-flex justify-content-between fw-bold mb-3">
        <span>Total</span>
        <span class="text-primary">Rp {{ number_format($summary['total'], 0, ',', '.') }}</span>
    </div>
    <a href="{{ route('agent-order.checkout') }}" class="btn btn-primary w-100"><i class="ti ti-arrow-right me-1"></i>Lanjut Checkout</a>
@endif
