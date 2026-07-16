@php
    if (old('overheads') !== null) {
        $overheadRows = collect(old('overheads'))->values()->all();
    } elseif (! empty($existingOverheads)) {
        $overheadRows = collect($existingOverheads)->map(fn ($row) => [
            'description' => $row->description,
            'amount' => (float) $row->amount,
        ])->values()->all();
    } else {
        $overheadRows = [];
    }
@endphp

<div class="card mb-4" id="overheadCard">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="card-title mb-0">Overhead</h5>
            <small class="text-muted">Optional production overhead costs</small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddOverhead">
            <i class="ti ti-plus me-1"></i> Add Overhead
        </button>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:55%">Description</th>
                    <th class="text-end" style="width:30%">Amount (Rp)</th>
                    <th style="width:15%" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody id="overheadRows">
                @forelse ($overheadRows as $index => $row)
                    <tr class="overhead-row">
                        <td>
                            <input
                                type="text"
                                name="overheads[{{ $index }}][description]"
                                class="form-control overhead-description"
                                placeholder="e.g. Labor, electricity"
                                value="{{ $row['description'] ?? '' }}"
                            >
                        </td>
                        <td>
                            <input
                                type="number"
                                step="any"
                                min="0"
                                name="overheads[{{ $index }}][amount]"
                                class="form-control text-end overhead-amount"
                                placeholder="0"
                                value="{{ isset($row['amount']) ? (float) $row['amount'] : '' }}"
                            >
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-icon btn-label-danger btn-remove-overhead" title="Remove">
                                <i class="ti ti-trash"></i>
                            </button>
                        </td>
                    </tr>
                @empty
                @endforelse
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td class="text-end fw-semibold">Total Overhead</td>
                    <td class="text-end fw-bold text-primary" id="overheadTotal">Rp 0</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div id="overheadEmptyHint" class="card-body text-center text-muted py-4 @if(count($overheadRows)) d-none @endif">
        <small>No overhead added. Click <strong>Add Overhead</strong> to include extra costs.</small>
    </div>
</div>

@once
    @push('page-js')
        <script>
            (function () {
                let overheadIndex = document.querySelectorAll('#overheadRows .overhead-row').length;

                function formatRp(n) {
                    return 'Rp ' + Number(n || 0).toLocaleString(undefined, {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2,
                    });
                }

                function reindexOverheadRows() {
                    document.querySelectorAll('#overheadRows .overhead-row').forEach(function (row, index) {
                        const desc = row.querySelector('.overhead-description');
                        const amount = row.querySelector('.overhead-amount');
                        if (desc) desc.name = 'overheads[' + index + '][description]';
                        if (amount) amount.name = 'overheads[' + index + '][amount]';
                    });
                    overheadIndex = document.querySelectorAll('#overheadRows .overhead-row').length;
                }

                function updateOverheadTotal() {
                    let total = 0;
                    document.querySelectorAll('#overheadRows .overhead-amount').forEach(function (input) {
                        total += parseFloat(input.value || '0') || 0;
                    });
                    const totalEl = document.getElementById('overheadTotal');
                    if (totalEl) {
                        totalEl.textContent = formatRp(total);
                    }
                    const emptyHint = document.getElementById('overheadEmptyHint');
                    const hasRows = document.querySelectorAll('#overheadRows .overhead-row').length > 0;
                    emptyHint?.classList.toggle('d-none', hasRows);
                }

                function addOverheadRow(description, amount) {
                    const tbody = document.getElementById('overheadRows');
                    if (!tbody) return;

                    const tr = document.createElement('tr');
                    tr.className = 'overhead-row';
                    tr.innerHTML =
                        '<td><input type="text" name="overheads[' + overheadIndex + '][description]" class="form-control overhead-description" placeholder="e.g. Labor, electricity" value="' + (description || '') + '"></td>' +
                        '<td><input type="number" step="any" min="0" name="overheads[' + overheadIndex + '][amount]" class="form-control text-end overhead-amount" placeholder="0" value="' + (amount ?? '') + '"></td>' +
                        '<td class="text-center"><button type="button" class="btn btn-sm btn-icon btn-label-danger btn-remove-overhead" title="Remove"><i class="ti ti-trash"></i></button></td>';
                    tbody.appendChild(tr);
                    overheadIndex++;
                    updateOverheadTotal();
                }

                document.getElementById('btnAddOverhead')?.addEventListener('click', function () {
                    addOverheadRow('', '');
                    const lastDesc = document.querySelector('#overheadRows .overhead-row:last-child .overhead-description');
                    lastDesc?.focus();
                });

                document.getElementById('overheadRows')?.addEventListener('click', function (e) {
                    const btn = e.target.closest('.btn-remove-overhead');
                    if (!btn) return;
                    btn.closest('.overhead-row')?.remove();
                    reindexOverheadRows();
                    updateOverheadTotal();
                });

                document.getElementById('overheadRows')?.addEventListener('input', function (e) {
                    if (e.target.classList.contains('overhead-amount')) {
                        updateOverheadTotal();
                    }
                });

                updateOverheadTotal();
            })();
        </script>
    @endpush
@endonce
