<?php

namespace App\Services\Pharmacy;

use App\Models\Pharmacy\Dispensing\DrugOrder;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/** Preserve transaction-time schedule metadata independently of future enrollment changes. */
final class UdddsTransactionMetadata
{
    public const COLUMNS = [
        'order_type', 'is_uddds', 'uddds_start_date', 'uddds_end_date',
        'uddds_source_docointkey', 'uddds_interval_days',
    ];

    public static function snapshot($source, array $columns): array
    {
        $values = [];
        foreach (self::COLUMNS as $column) {
            if (!in_array($column, $columns, true)) continue;
            $value = is_array($source) ? ($source[$column] ?? null) : ($source->{$column} ?? null);
            if ($value instanceof DateTimeInterface) $value = $value->format('Y-m-d');
            if ($column === 'is_uddds' && $value !== null) $value = (int) (bool) $value;
            $values[$column] = $value;
        }
        return $values;
    }

    private static function availableColumns(string $table): array
    {
        if (!in_array($table, ['hrxoissue', 'hrxoreturn'], true)) {
            throw new InvalidArgumentException('Unsupported UDDDS transaction table.');
        }
        $available = array_map('strtolower', Schema::connection('hospital')->getColumnListing($table));
        return array_values(array_intersect(self::COLUMNS, $available));
    }

    public static function forIssue($order): array
    {
        return self::snapshot($order, self::availableColumns('hrxoissue'));
    }

    public static function forReturn($order): array
    {
        $columns = self::availableColumns('hrxoreturn');
        if (!$columns) return [];
        // A legacy issue row with no snapshot cannot reconstruct its historical schedule.
        // Keep nulls in a captured snapshot, including null legacy intervals (daily).
        $source = $order;
        if (self::availableColumns('hrxoissue')) {
            $issued = DB::selectOne(
                'SELECT TOP 1 * FROM hospital.dbo.hrxoissue WHERE docointkey = ? AND enccode = ? AND hpercode = ? AND dmdcomb = ? AND dmdctr = ? ORDER BY issuedte DESC, issuetme DESC',
                [$order->docointkey, $order->enccode, $order->hpercode, $order->dmdcomb, $order->dmdctr]
            );
            if ($issued && array_filter(self::snapshot($issued, self::COLUMNS), fn ($value) => $value !== null)) {
                $source = $issued;
            }
        }
        return self::snapshot($source, $columns);
    }

    public static function forNewTransaction($record, string $table): array
    {
        $columns = self::availableColumns($table);
        $missing = array_diff($columns, array_keys($record->getAttributes()));
        if (!$missing) return [];
        $order = DrugOrder::find($record->docointkey);
        if (!$order) return [];
        return array_intersect_key(
            $table === 'hrxoissue' ? self::snapshot($order, $columns) : self::forReturn($order),
            array_flip($missing)
        );
    }
}
