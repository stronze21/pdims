<?php

namespace App\Services\Pharmacy\Reports;

final class ReportMath
{
    /** Quantities have two decimal places in the pharmacy ledger. */
    public static function units(string|int|float|null $value): int
    {
        return (int) round((float) ($value ?? 0) * 100);
    }

    public static function reconcile(array $row): array
    {
        $opening = $row['opening'] ?? null;
        $closing = $row['closing'] ?? null;
        $net = 0;
        foreach (['receipts' => 1, 'transfer_in' => 1, 'transfer_out' => -1, 'patient_issues' => -1,
            'patient_returns' => 1, 'ward_issues' => -1, 'ward_returns' => 1, 'pullouts' => -1, 'adjustments' => 1] as $key => $sign) {
            $net += self::units($row[$key] ?? 0) * $sign;
        }
        $expected = $opening === null ? null : self::units($opening) + $net;
        $difference = $expected === null || $closing === null ? null : self::units($closing) - $expected;
        $row['expected'] = $expected === null ? null : $expected / 100;
        $row['difference'] = $difference === null ? null : $difference / 100;
        $row['status'] = $opening === null ? 'Missing opening snapshot' : ($closing === null ? 'Missing closing snapshot'
            : ($difference !== 0 ? 'Unexplained difference — review' : 'Balanced — legacy history requires review'));

        return $row;
    }
}
