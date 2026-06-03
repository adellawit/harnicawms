/**
 * Auto-format number inputs with Indonesian locale (dot = thousands, comma = decimal).
 * Add class "number-format" to <input type="text"> elements.
 *
 * Display : 50000 → "50.000"  |  50000.5 → "50.000,5"  |  1234567 → "1.234.567"
 * Submit  : strips formatting → server receives "50000" or "50000.5"
 *
 * Global utility:
 *   window.unformatNumber(displayStr) → raw numeric string  ("50.000,5" → "50000.5")
 */
$(function () {

    /**
     * Convert raw value (digits + optional dot for decimal) into Indonesian display.
     */
    function rawToDisplay(raw) {
        if (raw === '' || raw === '-') return raw;

        var isNeg = raw.charAt(0) === '-';
        if (isNeg) raw = raw.substring(1);

        var dotPos = raw.indexOf('.');
        var intPart = dotPos !== -1 ? raw.substring(0, dotPos) : raw;
        var decPart = dotPos !== -1 ? raw.substring(dotPos + 1) : null;

        intPart = intPart.replace(/^0+(\d)/, '$1');
        if (intPart === '') intPart = '0';

        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        var result = isNeg ? '-' + intPart : intPart;
        if (decPart !== null) result += ',' + decPart;
        return result;
    }

    /**
     * Convert Indonesian display string back to raw numeric value.
     * "50.000,5" → "50000.5",  "50.000" → "50000"
     */
    function displayToRaw(display) {
        if (!display || display === '-') return display || '';
        return String(display).replace(/\./g, '').replace(',', '.');
    }

    // Expose globally so AJAX handlers can use it
    window.unformatNumber = displayToRaw;

    /**
     * Convert server / initial value to raw (digits + optional dot for decimal).
     * Handles both standard ("50000.5") and Indonesian ("50.000,5") formatted values.
     */
    function fromServerFormat(val) {
        val = String(val).trim();
        if (val === '') return '';

        var hasComma = val.indexOf(',') !== -1;
        var hasDot = val.indexOf('.') !== -1;

        if (hasComma && hasDot) {
            // Indonesian: "50.000,5" → strip dots, replace comma with dot → "50000.5"
            return val.replace(/\./g, '').replace(',', '.');
        }
        if (hasComma && !hasDot) {
            // Comma as decimal only: "10,5" → "10.5"
            return val.replace(',', '.');
        }
        if (hasDot && !hasComma) {
            // Determine if dots are thousands separators (groups of 3)
            var abs = val.replace(/^-/, '');
            var parts = abs.split('.');
            var isThousands = parts.length > 1;
            for (var i = 1; i < parts.length; i++) {
                if (parts[i].length !== 3) { isThousands = false; break; }
            }
            if (isThousands) {
                return val.replace(/\./g, '');
            }
        }

        return val;
    }

    // ── Input: reformat on every keystroke ──────────────────────────────
    $(document).on('input', '.number-format', function () {
        var el = this;
        var pos = el.selectionStart;
        var oldVal = el.value;

        // Count significant (digit) chars before cursor
        var beforeCursor = oldVal.substring(0, pos);
        var sigBefore = beforeCursor.replace(/[^\d]/g, '').length;
        var commaInBefore = beforeCursor.indexOf(',') !== -1;

        // Strip everything except digits, comma (decimal), minus
        var clean = oldVal.replace(/[^\d,\-]/g, '');

        // Keep only first comma
        var commaCount = (clean.match(/,/g) || []).length;
        if (commaCount > 1) {
            var firstComma = clean.indexOf(',');
            clean = clean.substring(0, firstComma + 1) + clean.substring(firstComma + 1).replace(/,/g, '');
        }

        // Handle minus (only allow at start)
        var hasMinus = clean.charAt(0) === '-';
        clean = clean.replace(/-/g, '');
        if (hasMinus) clean = '-' + clean;

        // Convert to raw (comma → dot for internal decimal)
        var raw = clean.replace(',', '.');

        // Format for display
        var formatted = rawToDisplay(raw);
        el.value = formatted;

        // Restore cursor position based on significant digit count
        var newPos = 0;
        var count = 0;
        for (var i = 0; i < formatted.length; i++) {
            if (count >= sigBefore) break;
            newPos = i + 1;
            if (/\d/.test(formatted[i])) count++;
        }
        // If cursor was after comma, adjust past the comma/separators
        if (commaInBefore) {
            var cIdx = formatted.indexOf(',');
            if (cIdx !== -1 && newPos <= cIdx) newPos = cIdx + 1;
        }
        el.setSelectionRange(newPos, newPos);
    });

    // ── Paste: normalize pasted value then reformat ────────────────────
    $(document).on('paste', '.number-format', function (e) {
        e.preventDefault();
        var text = (e.originalEvent.clipboardData || window.clipboardData).getData('text');
        var raw = fromServerFormat(text.trim());

        // Format and set
        this.value = rawToDisplay(raw);
        var len = this.value.length;
        this.setSelectionRange(len, len);
    });

    // ── Page load: format pre-filled values ────────────────────────────
    $('.number-format').each(function () {
        if (this.value) {
            var raw = fromServerFormat(this.value);
            this.value = rawToDisplay(raw);
        }
    });

    // ── Submit: strip formatting → server gets standard number ─────────
    $(document).on('submit', 'form', function () {
        $(this).find('.number-format').each(function () {
            this.value = displayToRaw(this.value);
        });
    });

});
