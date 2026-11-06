<?php

namespace App\Services\Pharmacy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PrescriptionPrintService
{
    public function prepare(iterable $items, bool $deductIssued = true): Collection
    {
        $items = collect($items);
        $issued = $items->isEmpty() ? collect() : DB::connection('webapp')
            ->table('prescription_data_issued')
            ->whereIn('presc_data_id', $items->pluck('id'))
            ->selectRaw('presc_data_id, SUM(qtyissued) AS total_issued')
            ->groupBy('presc_data_id')->pluck('total_issued', 'presc_data_id');

        return $items->map(function ($item) use ($issued, $deductIssued) {
            $row = (array) $item;
            $row['total_issued'] = (float) ($issued[$row['id']] ?? 0);
            $row = app(PrescriptionReactivationService::class)->enrichItem($row);
            $row['print_qty'] = $this->quantity($row['computed_total_qty'], $row['qty_issued'], $deductIssued);
            $row['deduct_issued'] = $deductIssued;

            return (object) $row;
        });
    }

    public function quantity(?float $total, float $issued, bool $deductIssued): ?float
    {
        return $total === null ? null : max(0, $total - ($deductIssued ? max(0, $issued) : 0));
    }
}
