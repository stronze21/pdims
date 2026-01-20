<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescription - {{ $queue->queue_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 20pt;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 10pt;
            margin: 2px 0;
        }

        .patient-info {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }

        .patient-info div {
            margin-bottom: 5px;
        }

        .patient-info strong {
            display: inline-block;
            width: 120px;
        }

        .prescription-items {
            margin-top: 20px;
        }

        .prescription-items h3 {
            margin-bottom: 10px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }

        .item {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            page-break-inside: avoid;
        }

        .item-header {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 11pt;
        }

        .item-details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 5px;
            font-size: 10pt;
        }

        .item-remarks {
            margin-top: 5px;
            font-style: italic;
            color: #666;
            font-size: 10pt;
        }

        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .signature-line {
            margin-top: 40px;
            border-top: 1px solid #000;
            width: 200px;
            text-align: center;
            padding-top: 5px;
        }

        @media print {
            body {
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #4F46E5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14pt;
        }

        .print-button:hover {
            background-color: #4338CA;
        }
    </style>
</head>

<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print</button>

    <div class="header">
        <h1>PRESCRIPTION</h1>
        <p>Mariano Marcos Memorial Hospital and Medical Center</p>
        <p>Date: {{ now()->format('F d, Y h:i A') }}</p>
    </div>

    <div class="patient-info">
        <div>
            <div><strong>Queue Number:</strong> {{ $queue->queue_number }}</div>
            @if ($queue->patient)
                <div><strong>Patient Name:</strong> {{ $queue->patient->patlast }}, {{ $queue->patient->patfirst }}
                </div>
                <div><strong>Hospital #:</strong> {{ $queue->patient->hpercode }}</div>
            @endif
        </div>
        <div>
            <div><strong>Date Queued:</strong> {{ $queue->queued_at->format('m/d/Y h:i A') }}</div>
            <div><strong>Priority:</strong> {{ strtoupper($queue->priority) }}</div>
        </div>
    </div>

    <div class="prescription-items">
        <h3>Prescription Items ({{ count($items) }} item{{ count($items) > 1 ? 's' : '' }})</h3>

        @foreach ($items as $index => $item)
            <div class="item">
                <div class="item-header">
                    {{ $index + 1 }}. {{ $item->drug_concat }}
                </div>
                <div class="item-details">
                    <div><strong>Quantity:</strong> {{ $item->qty }}</div>
                    <div><strong>Frequency:</strong> {{ $item->frequency ?: 'N/A' }}</div>
                    <div><strong>Duration:</strong> {{ $item->duration ?: 'N/A' }}</div>
                    <div><strong>Type:</strong> {{ $item->order_type }}</div>
                </div>
                @if ($item->remark || $item->addtl_remarks)
                    <div class="item-remarks">
                        @if ($item->remark)
                            <div>{{ $item->remark }}</div>
                        @endif
                        @if ($item->addtl_remarks)
                            <div><strong>Note:</strong> {{ $item->addtl_remarks }}</div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="footer">
        <div>
            <div class="signature-line">
                Prepared by
            </div>
        </div>
        <div>
            <div class="signature-line">
                Pharmacist
            </div>
        </div>
        <div>
            <div class="signature-line">
                Patient / Representative
            </div>
        </div>
    </div>

    <script>
        // Auto print on load (optional)
        // window.onload = function() { window.print(); };
    </script>
</body>

</html>
