<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Invoice')</title>
    <style>
        :root {
            --ink: #1a1d23;
            --muted: #6b7280;
            --line: #d1d5db;
            --accent: #0f766e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: #e5e7eb;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            font-size: 13px;
            line-height: 1.45;
        }
        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            padding: 0.75rem 1rem;
            background: #111827;
        }
        .toolbar a,
        .toolbar button {
            appearance: none;
            border: 0;
            border-radius: 6px;
            padding: 0.45rem 0.9rem;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
        }
        .toolbar .btn-print { background: #0f766e; }
        .toolbar .btn-back { background: #374151; }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 1rem auto;
            padding: 16mm 14mm;
            background: #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,.12);
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
        }
        .brand-name {
            margin: 0 0 0.25rem;
            font-size: 22px;
            font-weight: 700;
            color: var(--accent);
        }
        .muted { color: var(--muted); }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: 0.04em;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .box {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 0.85rem 1rem;
        }
        .box h3 {
            margin: 0 0 0.5rem;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
        }
        .box p { margin: 0.15rem 0; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        table.items th,
        table.items td {
            border-bottom: 1px solid var(--line);
            padding: 0.55rem 0.4rem;
            vertical-align: top;
        }
        table.items th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            background: #f9fafb;
        }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .totals {
            width: 280px;
            margin-left: auto;
        }
        .totals tr td {
            padding: 0.35rem 0;
        }
        .totals tr.grand td {
            border-top: 2px solid var(--ink);
            padding-top: 0.55rem;
            font-size: 15px;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            padding: 0.15rem 0.45rem;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: #ecfdf5;
            color: #065f46;
        }
        .footer {
            margin-top: 2rem;
            padding-top: 0.75rem;
            border-top: 1px dashed var(--line);
            color: var(--muted);
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            @page { size: A4; margin: 12mm; }
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="toolbar no-print">
        <a class="btn-back" href="{{ $backUrl ?? url()->previous() }}">Kembali</a>
        <button type="button" class="btn-print" onclick="window.print()">{{ $printButtonLabel ?? 'Print' }}</button>
    </div>
    <div class="sheet">
        @yield('content')
    </div>
    <script>
        @if(!empty($autoPrint))
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 250);
        });
        @endif
    </script>
</body>
</html>
