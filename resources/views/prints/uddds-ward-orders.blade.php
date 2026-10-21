<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Issued UDDDS orders - {{ $date }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        body { font: 12px Arial, sans-serif; color: #000; margin: 20px; }
        .toolbar { margin-bottom: 20px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 6px; }
        .date { text-align: center; margin: 0 0 16px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 7px; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        th:first-child { width: 30%; } th:last-child { width: 12%; }
        td:last-child { text-align: right; }
        .patient-start td { border-top-width: 2px; }
        thead { display: table-header-group; } tr { break-inside: avoid; }
        .ward + .ward { break-before: page; margin-top: 32px; }
        @media print { body { margin: 0; } .toolbar { display: none; } .ward + .ward { margin-top: 0; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print issued UDDDS orders</button></div>
    @forelse ($wards as $wardName => $patients)
        <section class="ward">
            <h1>{{ $wardName }}</h1>
            <p class="date">Issued UDDDS orders &middot; {{ $date }}</p>
            <table>
                <thead><tr><th>NAME OF PATIENT</th><th>NAME OF MEDICINE</th><th>QTY</th></tr></thead>
                <tbody>
                    @foreach ($patients as $patient)
                        @foreach ($patient['items'] as $line)
                            <tr class="{{ $loop->first ? 'patient-start' : '' }}">
                                <td>{{ $loop->first ? $patient['name'] : '' }}</td>
                                <td>{{ $line['medicine'] }}</td>
                                <td>{{ rtrim(rtrim(number_format($line['qty'], 4, '.', ''), '0'), '.') }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p>No issued UDDDS orders found for the selected ward and service date.</p>
    @endforelse
</body>
</html>
