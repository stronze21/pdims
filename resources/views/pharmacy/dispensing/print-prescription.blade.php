<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription Form</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px; font: 10pt/1.3 Arial, sans-serif; color: #000; }
        .toolbar, .form { max-width: 165mm; margin: 0 auto; }
        .toolbar { text-align: right; margin-bottom: 12px; }
        .form { border-top: 1px solid; padding-top: 16px; overflow-wrap: anywhere; }
        .form + .form { margin-top: 24px; }
        header { text-align: center; margin-bottom: 8px; }
        header p, h1 { margin: 0 0 2px; }
        h1 { font-size: 14pt; }
        .form-code { font-size: 9pt; }
        .field { display: flex; align-items: baseline; gap: 5px; margin-bottom: 4px; }
        .line { display: block; flex: 1; min-width: 0; min-height: 1.4em; border-bottom: 1px solid; }
        .field strong { flex-shrink: 0; }
        .license { width: 45%; margin-left: auto; }
        .patient { display: grid; grid-template-columns: minmax(0, 1fr) 12% 12%; gap: 10px; margin-bottom: 6px; }
        .patient div:not(:first-child) { text-align: center; }
        .location { display: grid; grid-template-columns: minmax(0, 1fr) 32%; gap: 16px; }
        .rx { border-top: 3px double; padding-top: 8px; display: flex; gap: 8px; min-height: 48mm; }
        .rx-mark { font: bold 23pt/1 Georgia, serif; }
        .columns { flex: 1; min-width: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        ol { margin: 0; padding-left: 2em; }
        li { padding-left: 2px; margin-bottom: 6px; break-inside: avoid; }
        .quantity { white-space: nowrap; }
        .directions { font-size: 9pt; }
        .note { white-space: pre-line; }
        .signature, .processing { break-inside: avoid; }
        .signature { display: grid; grid-template-columns: minmax(0, 1fr) 42%; gap: 16px; align-items: end; }
        .generics { margin: 0 0 4px; font: bold 15pt Georgia, serif; }
        .credentials .field { display: grid; grid-template-columns: minmax(0, 1fr) 65px; }
        .processing { border-top: 3px double; padding-top: 5px; display: grid; grid-template-columns: 60% minmax(0, 1fr); gap: 0 12px; }
        .received { grid-column: 2; grid-row: 2; }
        .costed { grid-column: 1; grid-row: 2; }

        @if ($opdTraining ?? false)
            @media screen and (prefers-reduced-motion: no-preference) {
                .form { animation: training-form-arrival 220ms ease-out both; }
                @keyframes training-form-arrival {
                    from { opacity: .6; }
                    to { opacity: 1; }
                }
            }
        @endif

        @page { margin: 10mm; }

        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            .form + .form { break-before: page; margin-top: 0; }
        }
    </style>
</head>

<body>
    @php
        $patientName = $patient
            ? trim($patient->patlast . ', ' . $patient->patfirst . ' ' . $patient->patmiddle . ' ' . $patient->patsuffix)
            : '';
        $date = now();
        $address = $patient?->primaryAddress ? $patient->getFullAddress() : '';
        $pages = $items->values()->chunk(10);
    @endphp

    <div class="toolbar">
        @if ($opdTraining ?? false)
            <a href="{{ route($prescriptionSource ? 'prescriptions.opd-walkthrough.'.$prescriptionSource : 'prescriptions.opd-walkthrough') }}">Restart walkthrough</a>
            · <a href="{{ route('prescriptions.queue.controller2') }}">Exit walkthrough</a>
            · <button type="button" disabled>Print</button>
        @else
            <button type="button" onclick="window.print()">Print</button>
        @endif
    </div>

    @foreach ($pages->isEmpty() ? [collect()] : $pages as $page)
        @php
            $firstNumber = $loop->index * 10 + 1;
            $columns = [$page->take(6), $page->skip(6)];
        @endphp

        <main class="form">
            <header>
                @if ($opdTraining ?? false)
                    <p><strong>TRAINING SAMPLE — SYNTHETIC PATIENT RECORD</strong></p>
                @endif
                <p class="form-code">MMMHMC-M-PHM/QP-002 Form 1 Rev. 1 – Prescription Form</p>
                <p><strong>MARIANO MARCOS MEMORIAL HOSP. &amp; M.C.</strong></p>
                <p><strong>BATAC CITY, ILOCOS NORTE</strong></p>
                <h1>PRESCRIPTION FORM</h1>
            </header>

            <div class="field"><strong>PRINT:</strong><span class="line"></span><strong>MD</strong></div>
            <div class="field license"><span class="line"></span><strong>LICENSE NO.</strong></div>

            <div class="patient">
                <div><span class="line">{{ $patientName }}</span><strong>NAME OF PATIENT</strong></div>
                <div><span class="line">{{ $patient?->patbdate?->age }}</span><strong>AGE</strong></div>
                <div><span class="line">{{ $patient?->patsex }}</span><strong>SEX</strong></div>
            </div>

            <div class="field"><strong>ADDRESS:</strong><span class="line">{{ $address }}</span></div>
            <div class="location">
                <div class="field"><strong>WARD/RM. NO.</strong><span class="line">{{ $wardRoom }}</span></div>
                <div class="field"><strong>DATE:</strong><span class="line">{{ $date->format('m/d/Y') }}</span></div>
            </div>

            <section class="rx" aria-label="Prescription items">
                <span class="rx-mark" aria-hidden="true">℞</span>
                <div class="columns">
                    @foreach ($columns as $column)
                        @if ($column->isNotEmpty())
                            <ol start="{{ $firstNumber + $loop->index * 6 }}">
                                @foreach ($column as $item)
                                    <li>
                                        <strong>{{ $item->drug_concat }}</strong>
                                        @if ($item->print_qty !== null)
                                            <strong class="quantity">#{{ $item->print_qty }}</strong>
                                        @endif
                                        <div class="directions">
                                            {{ $item->deduct_issued ? 'Remaining qty' : 'Full prescribed qty' }}
                                            @if ($item->print_qty === null)
                                                unavailable
                                            @endif
                                            · Issued {{ $item->qty_issued }}
                                            · {{ $item->order_type ?: 'Basic' }}
                                        </div>
                                        <div class="directions">
                                            Qty per dose {{ $item->qty_per_administration }}
                                            @if ($item->schedule_text)
                                                · {{ $item->schedule_text }}
                                            @endif
                                            @if ($item->days_to_cover)
                                                · {{ $item->days_to_cover }} day(s)
                                            @endif
                                        </div>
                                        @if ($item->addtl_remarks)
                                            <div class="directions note"><strong>Note:</strong> {{ $item->addtl_remarks }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    @endforeach
                </div>
            </section>

            <footer>
                <div class="signature">
                    <p class="generics">“mag generics tayo”</p>
                    <div class="credentials">
                        <div class="field"><span class="line"></span><strong>MD</strong></div>
                        <div class="field"><span class="line"></span><strong>PTR NO.</strong></div>
                        <div class="field"><span class="line"></span><strong>S2 NO.</strong></div>
                    </div>
                </div>
                <div class="processing">
                    <div class="field"><strong>FILLED BY:</strong><span class="line"></span></div>
                    <div class="field costed"><strong>COSTED BY:</strong><span class="line"></span></div>
                    <div class="field received"><strong>RCV'D BY:</strong><span class="line"></span></div>
                </div>
            </footer>
        </main>
    @endforeach
</body>

</html>
