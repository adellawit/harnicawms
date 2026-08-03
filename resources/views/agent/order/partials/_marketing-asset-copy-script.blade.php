@once
    @push('scripts')
        <script>
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn-copy-asset');
                if (!btn) return;
                navigator.clipboard.writeText(btn.dataset.text || '').then(() => {
                    const old = btn.innerHTML;
                    btn.innerHTML = '<i class="ti ti-check me-1"></i>Tersalin';
                    setTimeout(() => { btn.innerHTML = old; }, 1500);
                });
            });
        </script>
    @endpush
@endonce
