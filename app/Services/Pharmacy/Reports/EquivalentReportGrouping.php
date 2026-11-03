<?php

namespace App\Services\Pharmacy\Reports;

use App\Services\Pharmacy\DrugDescription;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EquivalentReportGrouping
{
    public static function enabled(array $filters): bool
    {
        return (string) ($filters['group_equivalents'] ?? '0') === '1';
    }

    public function apply(Builder $source, string $report, string $search): Builder
    {
        $db = DB::connection('hospital');
        // Membership has a unique (dmdcomb, dmdctr) constraint. Join active groups inside
        // the mapping, so inactive membership falls back to the original medicine.
        $mapping = $db->table('pharm_drug_equivalence_members as member')
            ->join('pharm_drug_equivalence_groups as eq', 'eq.id', '=', 'member.group_id')
            ->where('eq.status', 'A')->select('member.dmdcomb', 'member.dmdctr', 'member.group_id')
            ->selectRaw(DrugDescription::fromStock('canonical').' AS group_name')
            ->leftJoin('hdmhdr as canonical', function ($join) {
                $join->on('canonical.dmdcomb', '=', 'eq.canonical_dmdcomb')
                    ->on('canonical.dmdctr', '=', 'eq.canonical_dmdctr');
            });
        $mapped = $db->query()->fromSub((clone $source)->reorder(), 'source')
            ->leftJoinSub($mapping, 'equivalent', function ($join) {
                $join->on('equivalent.dmdcomb', '=', 'source.raw_dmdcomb')
                    ->on('equivalent.dmdctr', '=', 'source.raw_dmdctr');
            })
            ->select('source.*')
            ->selectRaw("COALESCE(equivalent.group_name, source.medicine) AS group_name,
                CASE WHEN equivalent.group_id IS NOT NULL THEN CONCAT('group:', equivalent.group_id)
                    ELSE CONCAT('drug:', source.raw_dmdcomb, ':', source.raw_dmdctr) END AS equivalent_key");
        if ($search !== '') {
            $mapped->where(function ($query) use ($search) {
                $query->whereRaw('COALESCE(equivalent.group_name, source.medicine) LIKE ?', ['%'.$search.'%'])
                    ->orWhereExists(function ($members) use ($search) {
                        $members->selectRaw('1')->from('pharm_drug_equivalence_members as search_member')
                            ->whereColumn('search_member.group_id', 'equivalent.group_id')
                            ->whereRaw(DrugDescription::fromStock('search_member').' LIKE ?', ['%'.$search.'%']);
                    });
            });
        }
        $query = $db->query()->fromSub($mapped, 'grouped')
            ->selectRaw('MAX(grouped.group_name) AS medicine, MAX(grouped.location) AS location,
                MAX(grouped.fund) AS fund,
                COUNT(DISTINCT CONCAT(grouped.raw_dmdcomb, \'|\', grouped.raw_dmdctr)) AS member_count')
            ->groupBy('grouped.equivalent_key', 'grouped.raw_location', 'grouped.raw_fund');

        if ($report === 'reconciliation') {
            // Never let SUM silently hide an unavailable child balance.
            foreach (['opening', 'closing'] as $balance) {
                $query->selectRaw("CASE WHEN COUNT(grouped.[$balance]) = COUNT(*) THEN SUM(grouped.[$balance]) ELSE NULL END AS [$balance]");
            }
        }
        foreach (ReportDefinition::totals($report) as $column) {
            $query->selectRaw("SUM(grouped.[$column]) AS [$column]");
        }
        $dimensions = match ($report) {
            'expiry' => ['expiry_date', 'status'],
            'deliveries' => ['status'],
            default => [],
        };
        foreach ($dimensions as $column) {
            $query->addSelect('grouped.'.$column)->groupBy('grouped.'.$column);
        }
        $query->orderBy('grouped.equivalent_key')->orderBy('grouped.raw_location')->orderBy('grouped.raw_fund');
        foreach ($dimensions as $column) {
            $query->orderBy('grouped.'.$column);
        }

        return $query;
    }
}
