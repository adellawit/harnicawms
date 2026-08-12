@php
    $requireSignature = $requireSignature ?? true;
    $requireSignedForm = $requireSignedForm ?? true;
@endphp

<div class="signature-section"
     data-require-signature="{{ $requireSignature ? '1' : '0' }}"
     data-require-signed-form="{{ $requireSignedForm ? '1' : '0' }}">
    <div class="signature-pad-wrapper mb-4">
        <label class="form-label">
            Tanda Tangan @if ($requireSignature)<span class="text-danger">*</span>@endif
            <span class="info-tip" tabindex="0" data-tip="Gambar tanda tangan langsung di area ini menggunakan mouse atau jari (layar sentuh).">?</span>
        </label>
        @if ($existingSignature ?? null)
            <p class="small text-muted mb-2">
                File tersimpan:
                <a href="{{ Storage::url($existingSignature->file_path) }}" target="_blank" rel="noopener">{{ $existingSignature->original_name }}</a>
                — gambar ulang hanya jika ingin mengganti.
            </p>
        @else
            <p class="text-muted small mb-2">Gambar tanda tangan digital di area bawah (mouse atau jari).</p>
        @endif
        <div class="pr-signature-canvas-wrap position-relative" style="touch-action: none;">
            <canvas id="signature-pad" class="w-100 d-block" style="height: 180px; cursor: crosshair;"></canvas>
        </div>
        <input type="hidden" name="signature_data" id="signatureData" value="{{ old('signature_data') }}">
        <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="signatureClearBtn">
                <i class="ti ti-refresh me-1"></i> Ulangi Tanda Tangan
            </button>
        </div>
        @error('signature_data')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        <div id="signature-error" class="text-danger small mt-1 d-none">Tanda tangan digital wajib diisi.</div>
    </div>

    <div class="signed-form-upload-wrapper mb-2">
        <label class="form-label" for="signedFormUpload">
            Upload Formulir @if ($requireSignedForm)<span class="text-danger">*</span>@else<span class="text-muted">(opsional)</span>@endif
            <span class="info-tip" tabindex="0" data-tip="Opsional. Unggah formulir yang sudah diisi & ditandatangani. Format PDF/JPG/PNG, maks. 5MB.">?</span>
        </label>
        @if ($existingSignedForm ?? null)
            <p class="small text-muted mb-2">
                File tersimpan:
                <a href="{{ Storage::url($existingSignedForm->file_path) }}" target="_blank" rel="noopener">{{ $existingSignedForm->original_name }}</a>
                — unggah ulang hanya jika ingin mengganti.
            </p>
        @endif
        <input type="file" name="signed_form" id="signedFormUpload" class="form-control" accept=".pdf,.jpg,.jpeg,.png" @if ($requireSignedForm) required @endif>
        <small class="text-muted">Opsional — unggah formulir PDF/JPG/PNG yang sudah diisi. Maks. 5MB.</small>
        <div id="signed-form-filename" class="small text-success mt-1 d-none"></div>
        @error('signed_form')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
</div>

@once
    @push('page-css')
    <style>
        #signature-pad { touch-action: none; }
    </style>
    @endpush

    @push('page-js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const section = document.querySelector('.signature-section');
            const canvas = document.getElementById('signature-pad');
            const hiddenInput = document.getElementById('signatureData');
            const clearBtn = document.getElementById('signatureClearBtn');
            const fileInput = document.getElementById('signedFormUpload');
            const fileNameEl = document.getElementById('signed-form-filename');
            const errorEl = document.getElementById('signature-error');
            const form = canvas?.closest('form');
            const requireSignature = section?.dataset.requireSignature === '1';

            if (!canvas || !hiddenInput) return;

            const ctx = canvas.getContext('2d');
            let drawing = false;
            let hasStroke = false;

            function hasDigitalSignature() {
                return hasStroke && !!hiddenInput.value;
            }

            function resizeCanvas() {
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                const rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;
                ctx.setTransform(1, 0, 0, 1, 0, 0);
                ctx.scale(ratio, ratio);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#1a1a1a';
            }

            function getPoint(event) {
                const rect = canvas.getBoundingClientRect();
                const source = event.touches ? event.touches[0] : event;
                return {
                    x: source.clientX - rect.left,
                    y: source.clientY - rect.top,
                };
            }

            function startDraw(event) {
                event.preventDefault();
                drawing = true;
                hasStroke = true;
                errorEl?.classList.add('d-none');
                const point = getPoint(event);
                ctx.beginPath();
                ctx.moveTo(point.x, point.y);
            }

            function draw(event) {
                if (!drawing) return;
                event.preventDefault();
                const point = getPoint(event);
                ctx.lineTo(point.x, point.y);
                ctx.stroke();
            }

            function endDraw(event) {
                if (!drawing) return;
                event.preventDefault();
                drawing = false;
                if (hasStroke) {
                    hiddenInput.value = canvas.toDataURL('image/png');
                }
            }

            function clearSignature() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hiddenInput.value = '';
                hasStroke = false;
            }

            resizeCanvas();
            window.addEventListener('resize', function () {
                const data = hasStroke ? canvas.toDataURL('image/png') : null;
                resizeCanvas();
                if (data) {
                    const img = new Image();
                    img.onload = function () {
                        ctx.drawImage(img, 0, 0, canvas.getBoundingClientRect().width, canvas.getBoundingClientRect().height);
                    };
                    img.src = data;
                }
            });

            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', endDraw);
            canvas.addEventListener('mouseleave', endDraw);
            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', endDraw, { passive: false });

            clearBtn?.addEventListener('click', clearSignature);

            fileInput?.addEventListener('change', function () {
                if (this.files?.length) {
                    fileNameEl.textContent = 'File dipilih: ' + this.files[0].name;
                    fileNameEl.classList.remove('d-none');
                } else {
                    fileNameEl.classList.add('d-none');
                    fileNameEl.textContent = '';
                }
            });

            form?.addEventListener('submit', function (event) {
                if (requireSignature && !hasDigitalSignature()) {
                    event.preventDefault();
                    errorEl?.classList.remove('d-none');
                    canvas.closest('.signature-pad-wrapper')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            if (hiddenInput.value) {
                hasStroke = true;
                const img = new Image();
                img.onload = function () {
                    ctx.drawImage(img, 0, 0, canvas.getBoundingClientRect().width, canvas.getBoundingClientRect().height);
                };
                img.src = hiddenInput.value;
            }
        });
    </script>
    @endpush
@endonce
