<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RIS #{{ $ris->risno }} - Print</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            margin: 0;
            padding: 0;
        }

        @media print {
            .no-print {
                display: none;
            }

            @page {
                size: 8.5in 13in;
                margin: 0.5in;
            }
        }

        .print-container {
            max-width: 8.5in;
            margin: 20px auto;
            border: 1px solid #ccc;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .print-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .print-header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin: 0;
        }

        .print-header p {
            margin: 5px 0;
        }

        .print-header .agency {
            font-size: 9pt;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            border: 1px solid #000;
            padding: 5px;
        }

        .header-row td {
            border: none;
        }

        .field-label {
            font-weight: bold;
            width: 1%;
            white-space: nowrap;
        }

        .field-value {
            border-bottom: 1px solid #000;
        }

        .req-header,
        .issue-header {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .signature-line {
            border-top: 1px solid #000;
            display: inline-block;
            width: 80%;
            text-align: center;
        }

        .signature-label {
            font-size: 9pt;
        }

        .print-controls {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #2563eb;
            color: white;
            padding: 10px;
            text-align: center;
            z-index: 100;
        }

        .print-btn {
            background: white;
            color: #2563eb;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            margin-right: 10px;
        }

        .close-btn {
            background: #f3f4f6;
            color: #1f2937;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="print-controls no-print">
        <button onclick="window.print();" class="print-btn">Print Document</button>
        <button onclick="window.close();" class="close-btn">Close</button>
    </div>

    <div class="print-container">
        <div class="print-header">
            <h1>REQUISITION AND ISSUE SLIP</h1>
            <p><strong>MARIANO MARCOS MEMORIAL HOSPITAL & MEDICAL CENTER</strong></p>
            <p>Batac, Ilocos Norte</p>
            <p class="agency">[Agency]</p>
        </div>

        <table cellspacing="0" cellpadding="0">
            <tr class="header-row">
                <td width="50%" style="border: none; padding-bottom: 10px;">
                    <table cellspacing="0" cellpadding="0" style="border: none;">
                        <tr>
                            <td class="field-label" style="border: none;">Division:</td>
                            <td class="field-value" style="border: none;">Medical Service</td>
                        </tr>
                    </table>
                </td>
                <td width="50%" style="border: none; padding-bottom: 10px;">
                    <table cellspacing="0" cellpadding="0" style="border: none;">
                        <tr>
                            <td class="field-label" style="border: none;">Responsibility Center Code:</td>
                            <td class="field-value" style="border: none;">{{ $ris->rcc }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="header-row">
                <td style="border: none; padding-bottom: 10px;">
                    <table cellspacing="0" cellpadding="0" style="border: none;">
                        <tr>
                            <td class="field-label" style="border: none;">Office:</td>
                            <td class="field-value" style="border: none;">{{ $ris->officeName }}</td>
                        </tr>
                    </table>
                </td>
                <td style="border: none; padding-bottom: 10px;">
                    <table cellspacing="0" cellpadding="0" style="border: none;">
                        <tr>
                            <td class="field-label" style="border: none;">RIS No:</td>
                            <td class="field-value" style="border: none;">{{ $ris->risno }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="header-row">
                <td style="border: none;"></td>
                <td style="border: none; padding-bottom: 10px;">
                    <table cellspacing="0" cellpadding="0" style="border: none;">
                        <tr>
                            <td class="field-label" style="border: none;">Date:</td>
                            <td class="field-value" style="border: none;">{{ $ris->formatted_risdate }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table cellspacing="0" cellpadding="0" style="margin-top: 20px;">
            <tr>
                <td colspan="4" class="req-header">Requisition</td>
                <td colspan="3" class="issue-header">Issuance</td>
            </tr>
            <tr>
                <th class="center" width="10%">Stock No.</th>
                <th class="center" width="8%">Unit</th>
                <th class="center" width="32%">Description</th>
                <th class="center" width="10%">Quantity</th>
                <th class="center" width="10%">Quantity</th>
                <th class="center" width="10%">Unit Value</th>
                <th class="center" width="20%">Remarks</th>
            </tr>

            @foreach ($risDetails as $detail)
                <tr>
                    <td class="center">{{ $detail->stockno }}</td>
                    <td class="center">{{ $detail->unit }}</td>
                    <td>{{ $detail->description }}</td>
                    <td class="center">{{ $detail->itmqty }}</td>
                    <td class="center">{{ $detail->itmqty }}</td>
                    <td class="right">
                        @if (count($detail->fundSources) > 0)
                            @foreach ($detail->fundSources as $fund)
                                {{ number_format($fund->unitprice, 2) }}
                            @endforeach
                        @else
                            &nbsp;
                        @endif
                    </td>
                    <td>
                        @if (count($detail->fundSources) > 0)
                            @foreach ($detail->fundSources as $fund)
                                CENDU Trust Fund
                            @endforeach
                        @else
                            &nbsp;
                        @endif
                    </td>
                </tr>
            @endforeach

            @php
                $remainingRows = max(0, 25 - count($risDetails));
            @endphp

            @for ($i = 0; $i < $remainingRows; $i++)
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor
        </table>

        <table cellspacing="0" cellpadding="0" style="margin-top: 20px;">
            <tr>
                <td width="15%" class="field-label">Purpose:</td>
                <td>{{ strtoupper($ris->purpose) }}</td>
            </tr>
        </table>

        <table cellspacing="0" cellpadding="0" style="margin-top: 20px;">
            <tr>
                <th width="25%" class="center">Requested by:</th>
                <th width="25%" class="center">Approved by:</th>
                <th width="25%" class="center">Issued by:</th>
                <th width="25%" class="center">Received by:</th>
            </tr>
            <tr>
                <td style="height: 70px; vertical-align: bottom;" class="center">
                    <div class="signature-line">{{ $ris->requested_by_name }}</div>
                </td>
                <td style="vertical-align: bottom;" class="center">
                    <div class="signature-line">{{ $ris->apprvdby }}</div>
                </td>
                <td style="vertical-align: bottom;" class="center">
                    <div class="signature-line">{{ $ris->issued_by_name }}</div>
                </td>
                <td style="vertical-align: bottom;" class="center">
                    <div class="signature-line">{{ $ris->receivedby }}</div>
                </td>
            </tr>
            <tr>
                <td class="center signature-label">Signature</td>
                <td class="center signature-label">Signature</td>
                <td class="center signature-label">Signature</td>
                <td class="center signature-label">Signature</td>
            </tr>
            <tr>
                <td class="center">{{ $ris->requested_by_desig }}</td>
                <td class="center">{{ $ris->apprvdby_desig }}</td>
                <td class="center">{{ $ris->issued_by_desig }}</td>
                <td class="center">{{ $ris->receivedby_desig }}</td>
            </tr>
            <tr>
                <td class="center signature-label">Designation</td>
                <td class="center signature-label">Designation</td>
                <td class="center signature-label">Designation</td>
                <td class="center signature-label">Designation</td>
            </tr>
            <tr>
                <td class="center">{{ $ris->formatted_requestdate }}</td>
                <td class="center">{{ $ris->formatted_approveddate }}</td>
                <td class="center">{{ $ris->formatted_issueddate }}</td>
                <td class="center">{{ $ris->formatted_receiveddate }}</td>
            </tr>
            <tr>
                <td class="center signature-label">Date</td>
                <td class="center signature-label">Date</td>
                <td class="center signature-label">Date</td>
                <td class="center signature-label">Date</td>
            </tr>
        </table>
    </div>
</body>

</html>
