<?php

namespace App\Services\Pharmacy;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PrescriptionPrintService
{
    public function wardRoom(?string $encounterCode): string
    {
        if (! $encounterCode) {
            return '';
        }

        $location = DB::connection('hospital')->table('hospital.dbo.hpatroom as assignment')
            ->leftJoin('hospital.dbo.hward as ward', 'assignment.wardcode', '=', 'ward.wardcode')
            ->leftJoin('hospital.dbo.hroom as room', 'assignment.rmintkey', '=', 'room.rmintkey')
            ->where('assignment.enccode', $encounterCode)
            ->orderByDesc('assignment.hprdate')
            ->first(['ward.wardname', 'room.rmname']);

        return implode(' / ', array_filter([
            trim($location->wardname ?? ''),
            trim($location->rmname ?? ''),
        ]));
    }

    public function prepare(iterable $items, bool $deductIssued = true): Collection
    {
        $items = collect($items);
        if ($items->isEmpty()) {
            return $items;
        }

        $connection = DB::connection('webapp');
        $ids = $items->pluck('id');
        $logged = $connection->table('webapp.dbo.prescription_data_issued')
            ->whereIn('presc_data_id', $ids)
            ->select('presc_data_id', 'docointkey')
            ->selectRaw('SUM(qtyissued) AS qtyissued')
            ->groupBy('presc_data_id', 'docointkey');

        // HRXO reflects returns. Keep logged quantities for transactions absent from HRXO.
        $transactions = $connection->query()->fromSub($logged, 'logged')
            ->leftJoin('hospital.dbo.hrxo as hrxo', 'hrxo.docointkey', '=', 'logged.docointkey')
            ->select('logged.presc_data_id')
            ->selectRaw('CASE WHEN hrxo.docointkey IS NULL THEN logged.qtyissued ELSE COALESCE(hrxo.qtyissued, 0) END AS qtyissued')
            ->unionAll(
                $connection->table('hospital.dbo.hrxo as hrxo')
                    ->whereIn('hrxo.prescription_data_id', $ids)
                    ->whereNotExists(function ($query) {
                        $query->selectRaw('1')->from('webapp.dbo.prescription_data_issued as issue')
                            ->whereColumn('issue.docointkey', 'hrxo.docointkey')
                            ->whereColumn('issue.presc_data_id', 'hrxo.prescription_data_id');
                    })
                    ->select('hrxo.prescription_data_id as presc_data_id', 'hrxo.qtyissued')
            );

        $issued = $connection->query()->fromSub($transactions, 'transactions')
            ->select('presc_data_id')
            ->selectRaw('SUM(qtyissued) AS total_issued')
            ->groupBy('presc_data_id')
            ->pluck('total_issued', 'presc_data_id');

        return $items->map(function ($item) use ($issued, $deductIssued) {
            return $this->formatItem($item, (float) ($issued[$item->id] ?? 0), $deductIssued);
        });
    }

    private function formatItem(object $item, float $issued, bool $deductIssued): object
    {
        $ordered = is_numeric($item->qty) ? (float) $item->qty : null;
        $dose = strtoupper(trim((string) $item->entry_source)) === 'EMR2'
            && is_numeric($item->qty_per_intake) && $item->qty_per_intake > 0
            ? (float) $item->qty_per_intake
            : null;
        $issued = max(0, $issued);

        return (object) array_merge((array) $item, [
            'ordered_qty' => $ordered,
            'qty_per_administration' => $dose,
            'qty_issued' => $issued,
            'print_qty' => $this->quantity($ordered, $issued, $deductIssued),
            'deduct_issued' => $deductIssued,
            'schedule_text' => trim((string) $item->remark),
            'days_to_cover' => app(PrescriptionReactivationService::class)->durationDays($item),
        ]);
    }

    public function quantity(?float $total, float $issued, bool $deductIssued): ?float
    {
        return $total === null ? null : max(0, $total - ($deductIssued ? max(0, $issued) : 0));
    }
}
