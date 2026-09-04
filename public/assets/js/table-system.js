/**
 * Table System — application-wide DataTables defaults.
 *
 * Every admin table inherits this layout, so individual pages no longer
 * declare their own `dom` string. Pair with assets/css/table-system.css.
 *
 * Layout produced:
 *   ┌ .dt-card-header ────────────────────────────────────────────┐
 *   │ .dt-card-header-title: module name    export/action buttons │
 *   ├ .dt-toolbar ──────────────────────────────────────────────┤
 *   │ search                                        length menu   │
 *   ├──────────────────────────────────────────────────────────┤
 *   │ table                                                      │
 *   ├ .dt-footer ─────────────────────────────────────────────┤
 *   │ record count                              pagination      │
 *   └──────────────────────────────────────────────────────────┘
 */
(function () {
    'use strict';

    if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.dataTable) {
        return;
    }

    var $ = window.jQuery;

    $.extend(true, $.fn.dataTable.defaults, {
        dom:
            '<"dt-card-header"<"dt-card-header-title">B>' +
            '<"dt-toolbar"' +
                '<"dt-toolbar-start"f>' +
                '<"dt-toolbar-end"l>' +
            '>' +
            'rt' +
            '<"dt-footer"ip>',

        // 7 was a leftover from the old template and is not a conventional step.
        lengthMenu: [10, 25, 50, 100],
        pageLength: 10,

        // Let the table fill the card at 100% width instead of DataTables
        // computing fixed pixel widths from header/content — that computed
        // width was routinely wider than the container and forced an
        // unnecessary horizontal scrollbar on tables with few columns.
        autoWidth: false,

        language: {
            search: '',
            searchPlaceholder: 'Cari…',
            lengthMenu: 'Tampilkan _MENU_',
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            infoFiltered: '(disaring dari _MAX_ data)',
            zeroRecords: 'Tidak ada data yang cocok dengan pencarian',
            emptyTable: 'Belum ada data',
            processing: 'Memuat…',
            paginate: {
                first: 'Awal',
                last: 'Akhir',
                next: '›',
                previous: '‹'
            }
        }
    });

    /**
     * The stock markup puts "Search:" in a label and leaves the input without a
     * placeholder or accessible name. Fix both on every table that initialises.
     */
    $(document).on('init.dt', function (event, settings) {
        var api = new $.fn.dataTable.Api(settings);
        var wrapper = $(api.table().container());

        wrapper.find('.dataTables_filter input[type="search"]').each(function () {
            var input = $(this);

            if (!input.attr('placeholder')) {
                input.attr('placeholder', 'Cari…');
            }

            input.attr('aria-label', 'Cari di tabel');
        });

        wrapper.find('.dataTables_length select').attr('aria-label', 'Jumlah baris per halaman');

        // The card header has no title of its own — every index page already
        // renders the module name as the active breadcrumb item via
        // <x-page-header>, so borrow that text instead of touching all 76 views.
        var titleEl = wrapper.find('.dt-card-header-title');

        if (titleEl.length && !titleEl.text().trim()) {
            var crumb = document.querySelector('#layout-breadcrumb-source .breadcrumb-item.active');

            if (crumb && crumb.textContent.trim()) {
                titleEl.text(crumb.textContent.trim());
            } else {
                // No breadcrumb found — hide the empty title slot instead of
                // leaving a blank gap next to the buttons.
                titleEl.remove();
            }
        }
    });
})();
