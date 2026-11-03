<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $metadata['title'] }}</title>
    <style>
        body { font: 12px/1.5 Arial, sans-serif; color: #172033; margin: 24px; }
        h1 { font-size: 22px; margin: 0 0 12px; } p { margin: 5px 0; }
        header { margin-bottom: 20px; } table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th, td { padding: 6px; border: 1px solid #cbd5e1; vertical-align: top; }
        th, tfoot { background: #f1f5f9; } .text-right { text-align: right; font-variant-numeric: tabular-nums; }
        .text-left { text-align: left; } .no-print { margin-bottom: 16px; }
        button { padding: 10px 18px; cursor: pointer; } section { margin-top: 20px; }
        @page { size: {{ $report === 'reconciliation' ? 'A3' : 'A4' }} landscape; margin: 10mm; }
        @media print { body { margin: 0; } .no-print { display: none; } thead { display: table-header-group; }
            tfoot { display: table-row-group; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="no-print"><button type="button" onclick="window.print()">Print report</button></div>
    <header>
        <h1>{{ $metadata['title'] }}</h1>
        <p>{{ $metadata['location'] }} &middot; {{ $metadata['fund'] }}</p>
        <p>{{ $metadata['period'] }} &middot; {{ $metadata['medicine'] }}</p>
        <p>{{ $metadata['grouping'] ?? 'Separate medicines' }}</p>
        <p>Generated {{ $metadata['generated'] }}. All matching rows are included.</p>
    </header>
    @include('pharmacy.reports.table')
    <section aria-label="Report notes">
        <h2>Source notes</h2>
        @foreach ($notes as $note)
            <p>{{ $note }}</p>
        @endforeach
    </section>
</body>
</html>
