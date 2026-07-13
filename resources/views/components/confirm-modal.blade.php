@props([
    'id',
    'title',
    'action' => '#',
    'method' => 'POST',
    'confirmText' => 'Submit',
    'cancelText' => 'Cancel',
    'confirmClass' => 'btn-primary',
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}">
                @csrf
                @if(strtoupper($method) !== 'POST')
                    @method($method)
                @endif
                <div class="modal-header">
                    <h5 class="modal-title">{{ $title }}</h5>
                    <button type="button" class="modal-close-btn" data-bs-dismiss="modal" aria-label="Tutup">
                        <i class="ti ti-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    {{ $slot }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-dark" data-bs-dismiss="modal">{{ $cancelText }}</button>
                    <button type="submit" class="btn {{ $confirmClass }}">{{ $confirmText }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
