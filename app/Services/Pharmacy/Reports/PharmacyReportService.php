<?php

namespace App\Services\Pharmacy\Reports;

use App\Services\Pharmacy\DrugDescription;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class PharmacyReportService
{
    private const MOVEMENTS = ['opening', 'closing', 'receipts', 'transfer_in', 'transfer_out',
        'patient_issues', 'patient_returns', 'ward_issues', 'ward_returns', 'pullouts', 'adjustments',
        'opening_present', 'closing_present'];

    public function options(): array
    {
        abort_unless(auth()->user()?->can('view-reports'), 403);
        $locations = DB::connection('hospital')->table('pharm_locations')->select('id', 'description')
            ->when(! auth()->user()->can('filter-stocks-location'), fn ($q) => $q->where('id', auth()->user()->pharm_location_id))
            ->orderBy('description')->get();
        $funds = DB::connection('hospital')->table('hcharge')->select('chrgcode', 'chrgdesc')
            ->where('bentypcod', 'DRUME')->where('chrgstat', 'A')->whereIn('chrgcode', app('chargetable'))
            ->orderBy('chrgdesc')->get();

        return compact('locations', 'funds');
    }

    public function filters(array $input, ?array $options = null): array
    {
        $options ??= $this->options();
        $user = auth()->user();
        abort_unless($user?->can('view-reports'), 403);
        $filters = ReportFilters::validate($input, $user->pharm_location_id === null ? null : (string) $user->pharm_location_id,
            $user->can('filter-stocks-location'), $options['funds']->pluck('chrgcode')->all());
        abort_unless($options['locations']->contains(fn ($loc) => (string) $loc->id === (string) $filters['location']), 403);

        return $filters;
    }

    public function query(string $report, array $filters): Builder
    {
        abort_unless(isset(ReportDefinition::TITLES[$report]), 404);

        $grouped = EquivalentReportGrouping::enabled($filters);
        $sourceFilters = $filters;
        if ($grouped) {
            // Search the full equivalence group after mapping, so a search for one
            // member does not accidentally omit its peers from the summary.
            $sourceFilters['medicine'] = '';
        }
        $query = match ($report) {
            'issuance' => $this->transactions($sourceFilters, false),
            'returns' => $this->transactions($sourceFilters, true),
            'deliveries' => $this->deliveries($sourceFilters),
            'expiry' => $this->expiry($sourceFilters),
            'reconciliation' => $this->reconciliation($sourceFilters),
        };

        return $grouped ? (new EquivalentReportGrouping)->apply($query, $report, $filters['medicine']) : $query;
    }

    public function totals(string $report, Builder $query): array
    {
        $aggregate = DB::connection('hospital')->query()->fromSub((clone $query)->reorder(), 'report_totals');
        foreach (ReportDefinition::totals($report) as $column) {
            // Column names come exclusively from ReportDefinition, never request input.
            $aggregate->selectRaw("COALESCE(SUM([$column]), 0) AS [$column]");
        }

        return (array) $aggregate->first();
    }

    public function row(string $report, object|array $row): array
    {
        $row = (array) $row;

        return $report === 'reconciliation' ? ReportMath::reconcile($row) : $row;
    }

    public function metadata(string $report, array $filters, array $options): array
    {
        return ['title' => ReportDefinition::TITLES[$report], 'generated' => now()->format('Y-m-d H:i:s T'),
            'location' => $options['locations']->first(fn ($loc) => (string) $loc->id === (string) $filters['location'])->description,
            'fund' => $filters['fund'] === '' ? 'All permitted funds' : $options['funds']->firstWhere('chrgcode', $filters['fund'])->chrgdesc,
            'period' => $report === 'expiry' ? 'Current stock; '.$filters['expiry'].'; horizon '.$filters['days'].' days'
                : $filters['from'].' through '.$filters['to'],
            'medicine' => $filters['medicine'] === '' ? 'All medicines' : $filters['medicine'],
            'grouping' => EquivalentReportGrouping::enabled($filters) ? 'Equivalent drugs grouped within each fund source' : 'Separate medicines'];
    }

    private function table(string $table): Builder
    {
        return DB::connection('hospital')->table($table);
    }

    private function dates(Builder $query, string $column, array $filters): Builder
    {
        [$from, $until] = ReportFilters::bounds($filters);

        return $query->where($column, '>=', $from)->where($column, '<', $until);
    }

    private function scope(Builder $query, array $f, string $location, string $fund, string $medicine): Builder
    {
        return $query->where($location, $f['location'])->whereIn($fund, app('chargetable'))
            ->when($f['fund'] !== '', fn ($q) => $q->where($fund, $f['fund']))
            ->when($f['medicine'] !== '', fn ($q) => $q->whereRaw("$medicine LIKE ?", ['%'.$f['medicine'].'%']));
    }

    private function transactions(array $f, bool $returns): Builder
    {
        $table = $returns ? 'hrxoreturn' : 'hrxoissue';
        $date = $returns ? 't.returndate' : 't.issuedte';
        $fund = $returns ? 't.returnfrom' : 't.issuedfrom';
        $staff = $returns ? 't.returnby' : 't.issuedby';
        $medicine = DrugDescription::fromStock('t');
        $query = $this->table("$table as t")
            ->join('hrxo as o', 'o.docointkey', '=', 't.docointkey')
            ->leftJoin('hperson as p', 'p.hpercode', '=', 't.hpercode')
            ->leftJoin('pharm_locations as l', 'l.id', '=', 'o.loc_code')
            ->leftJoin('hcharge as f', 'f.chrgcode', '=', $fund)
            ->selectRaw("$date AS event_date, $medicine AS medicine, l.description AS location, f.chrgdesc AS fund,
                CONCAT(RTRIM(p.patlast), ', ', RTRIM(p.patfirst), ' ', RTRIM(p.patmiddle)) AS patient,
                t.hpercode AS patient_no, t.pcchrgcod AS reference, t.docointkey AS order_reference,
                t.qty AS quantity, COALESCE((SELECT MAX(u.name) FROM pharm_users u WHERE u.employeeid = $staff), $staff) AS staff")
            ->where('t.status', 'A');
        $this->dates($query, $date, $f);
        $this->scope($query, $f, 'o.loc_code', $fund, $medicine);
        $query->selectRaw("t.dmdcomb AS raw_dmdcomb, t.dmdctr AS raw_dmdctr, o.loc_code AS raw_location, $fund AS raw_fund");

        return $query->orderBy($date)->orderBy('t.docointkey')->orderBy('t.qty');
    }

    private function deliveries(array $f): Builder
    {
        $medicine = DrugDescription::fromStock('i');
        $received = "CASE WHEN d.status = 'locked' AND i.status = 'delivered' THEN i.qty ELSE 0 END";
        $query = $this->table('pharm_delivery_items as i')->join('pharm_delivery_details as d', 'd.id', '=', 'i.delivery_id')
            ->leftJoin('pharm_locations as l', 'l.id', '=', 'i.pharm_location_id')
            ->leftJoin('hcharge as f', 'f.chrgcode', '=', 'i.charge_code')
            ->selectRaw("d.delivery_date AS event_date, $medicine AS medicine, l.description AS location, f.chrgdesc AS fund,
                CONCAT(d.si_no, ' / ', d.po_no) AS reference, i.lot_no AS lot, i.expiry_date,
                CASE WHEN d.status <> 'locked' AND (SELECT TOP(1) a.action FROM pharm_delivery_audits a WHERE a.delivery_id = d.id ORDER BY a.created_at DESC, a.id DESC) = 'reverted'
                    THEN 'reverted' ELSE d.status END AS status,
                i.qty AS quantity, i.unit_price AS unit_cost, $received AS received_quantity,
                CAST(($received) * i.unit_price AS decimal(20,2)) AS received_cost");
        $this->dates($query, 'd.delivery_date', $f);
        $this->scope($query, $f, 'i.pharm_location_id', 'i.charge_code', $medicine);
        $query->selectRaw('i.dmdcomb AS raw_dmdcomb, i.dmdctr AS raw_dmdctr, i.pharm_location_id AS raw_location, i.charge_code AS raw_fund');

        return $query->orderBy('d.delivery_date')->orderBy('i.id');
    }

    private function expiry(array $f): Builder
    {
        $medicine = DrugDescription::fromStock('s');
        $query = $this->table('pharm_drug_stocks as s')
            ->leftJoin('pharm_locations as l', 'l.id', '=', 's.loc_code')
            ->leftJoin('hcharge as f', 'f.chrgcode', '=', 's.chrgcode')
            ->selectRaw("$medicine AS medicine, l.description AS location, f.chrgdesc AS fund, s.lot_no AS lot,
                s.exp_date AS expiry_date, s.stock_bal AS quantity,
                CASE WHEN s.exp_date IS NULL THEN 'Unknown expiry' WHEN s.exp_date < ? THEN 'Expired' ELSE 'Not expired' END AS status", [today()->toDateString()])
            ->where('s.stock_bal', '>', 0);
        $today = today()->toDateString();
        match ($f['expiry']) {
            'near' => $query->where('s.exp_date', '>=', $today)->where('s.exp_date', '<', today()->addDays((int) $f['days'] + 1)->toDateString()),
            'expired' => $query->where('s.exp_date', '<', $today),
            'unknown' => $query->whereNull('s.exp_date'),
            default => $query,
        };
        $this->scope($query, $f, 's.loc_code', 's.chrgcode', $medicine);
        $query->selectRaw('s.dmdcomb AS raw_dmdcomb, s.dmdctr AS raw_dmdctr, s.loc_code AS raw_location, s.chrgcode AS raw_fund');

        return $query->orderBy('s.exp_date')->orderBy('s.id');
    }

    /** Every source is aggregated independently; joining raw movements would multiply quantities. */
    private function event(Builder $query, array $identity, array $values): Builder
    {
        foreach (['dmdcomb', 'dmdctr', 'loc_code', 'chrgcode', 'dmdprdte'] as $key) {
            $query->selectRaw($identity[$key]." AS [$key]");
        }
        foreach (self::MOVEMENTS as $key) {
            $query->selectRaw(($values[$key] ?? 'CAST(0 AS decimal(20,2))')." AS [$key]");
        }

        return $query;
    }

    private function identity(string $alias): array
    {
        return array_combine(['dmdcomb', 'dmdctr', 'loc_code', 'chrgcode', 'dmdprdte'],
            array_map(fn ($key) => "$alias.$key", ['dmdcomb', 'dmdctr', 'loc_code', 'chrgcode', 'dmdprdte']));
    }

    private function reconciliation(array $f): Builder
    {
        $previous = CarbonImmutable::parse($f['from'])->subDay()->toDateString();
        $card = $this->table('pharm_drug_stock_cards as c')->where('c.stock_date', '>=', $previous)
            ->where('c.stock_date', '<', CarbonImmutable::parse($f['to'])->addDay()->toDateString());
        $isOpening = "c.stock_date = '$previous'"; // Validated ISO dates, generated by Carbon.
        $isClosing = "c.stock_date = '{$f['to']}'";
        $inPeriod = "c.stock_date >= '{$f['from']}'";
        $io = "NULLIF(LTRIM(RTRIM(c.io_trans_ref_no)), '') IS NOT NULL AND c.io_trans_ref_no NOT LIKE 'RIS-%'";
        $todayClosing = $f['to'] === today()->toDateString();
        $events = [$this->event($card, $this->identity('c'), [
            'opening' => "CASE WHEN $isOpening THEN COALESCE(c.bal, 0) ELSE 0 END",
            'opening_present' => "CASE WHEN $isOpening AND c.reference IS NOT NULL THEN 1 ELSE 0 END",
            'closing' => $todayClosing ? '0' : "CASE WHEN $isClosing THEN COALESCE(c.bal, 0) ELSE 0 END",
            'closing_present' => $todayClosing ? '0' : "CASE WHEN $isClosing AND c.reference IS NOT NULL THEN 1 ELSE 0 END",
            'transfer_in' => "CASE WHEN $inPeriod AND $io THEN COALESCE(c.rec, 0) ELSE 0 END",
            'transfer_out' => "CASE WHEN $inPeriod AND $io THEN COALESCE(c.iss, 0) ELSE 0 END",
            'ward_issues' => "CASE WHEN $inPeriod AND c.io_trans_ref_no LIKE 'RIS-%' THEN COALESCE(c.iss, 0) ELSE 0 END",
        ])];
        if ($todayClosing) {
            $events[] = $this->event($this->table('pharm_drug_stocks as s'), $this->identity('s'),
                ['closing' => 's.stock_bal', 'closing_present' => '1']);
        }

        // Stock allocations are mutable on return. Issue events preserve the original quantity/date.
        $issueId = $this->identity('s');
        $issueId['loc_code'] = 'o.loc_code';
        $issueId['chrgcode'] = 's.issuedfrom';
        $events[] = $this->event($this->dates($this->table('hrxoissue as s')->join('hrxo as o', 'o.docointkey', '=', 's.docointkey')
            ->where('s.status', 'A')->where(fn ($q) => $q->whereNull('s.ris')->orWhere('s.ris', 0)), 's.issuedte', $f), $issueId, ['patient_issues' => 's.qty']);
        $returnId = $this->identity('r');
        $returnId['loc_code'] = 'o.loc_code';
        $returnId['chrgcode'] = 'r.returnfrom';
        $events[] = $this->event($this->dates($this->table('hrxoreturn as r')->join('hrxo as o', 'o.docointkey', '=', 'r.docointkey')
            ->where('r.status', 'A'), 'r.returndate', $f), $returnId, ['patient_returns' => 'r.qty']);

        $ward = $this->table('pharm_ward_ris_requests as w')->where('w.return_qty', '>', 0);
        $events[] = $this->event($this->dates($ward, 'w.updated_at', $f), $this->identity('w'), ['ward_returns' => 'w.return_qty']);
        $events[] = $this->event($this->dates($this->table('pharm_pull_out_items as p')->join('pharm_drug_stocks as s', 's.id', '=', 'p.stock_id'), 'p.created_at', $f),
            $this->identity('s'), ['pullouts' => 'p.pullout_qty']);
        $events[] = $this->event($this->dates($this->table('pharm_stock_adjustments as a')->join('pharm_drug_stocks as s', 's.id', '=', 'a.stock_id'), 'a.created_at', $f),
            $this->identity('s'), ['adjustments' => 'a.to_qty - a.from_qty']);

        // Audit entries preserve receipt/reversal dates and quantities, including repeated lock/revert cycles.
        $audit = $this->table('pharm_delivery_audits as a')
            ->fromRaw("pharm_delivery_audits AS a CROSS APPLY OPENJSON(a.entries) WITH (item_id bigint '$.item_id', qty decimal(20,2) '$.qty') AS entry")
            ->join('pharm_delivery_items as i', 'i.id', '=', 'entry.item_id')
            ->whereIn('a.action', ['locked', 'reverted']);
        $deliveryId = $this->identity('i');
        $deliveryId['loc_code'] = 'i.pharm_location_id';
        $deliveryId['chrgcode'] = 'i.charge_code';
        $events[] = $this->event($this->dates($audit, 'a.created_at', $f), $deliveryId,
            ['receipts' => "CASE WHEN a.action = 'locked' THEN entry.qty ELSE -entry.qty END"]);
        $legacy = $this->table('pharm_delivery_items as i')->join('pharm_delivery_details as d', 'd.id', '=', 'i.delivery_id')
            ->where('d.status', 'locked')->where('i.status', 'delivered')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pharm_delivery_audits as a')->whereColumn('a.delivery_id', 'd.id'));
        $events[] = $this->event($this->dates($legacy, 'd.updated_at', $f), $deliveryId, ['receipts' => 'i.qty']);
        $emergencyId = $this->identity('p');
        $emergencyId['loc_code'] = 'p.pharm_location_id';
        $emergencyId['chrgcode'] = 'p.charge_code';
        $events[] = $this->event($this->dates($this->table('pharm_drug_emergency_purchases as p')
            ->where('p.status', 'pushed'), 'p.updated_at', $f), $emergencyId, ['receipts' => 'p.qty']);

        $union = array_shift($events);
        foreach ($events as $event) {
            $union->unionAll($event);
        }
        $grouped = DB::connection('hospital')->query()->fromSub($union, 'e')
            ->where('e.loc_code', $f['location'])->whereIn('e.chrgcode', app('chargetable'))
            ->when($f['fund'] !== '', fn ($q) => $q->where('e.chrgcode', $f['fund']))
            ->select('e.dmdcomb', 'e.dmdctr', 'e.loc_code', 'e.chrgcode', 'e.dmdprdte')
            ->groupBy('e.dmdcomb', 'e.dmdctr', 'e.loc_code', 'e.chrgcode', 'e.dmdprdte');
        foreach (self::MOVEMENTS as $key) {
            $grouped->selectRaw("SUM(COALESCE(e.[$key], 0)) AS [$key]");
        }
        $medicine = DrugDescription::fromStock('g');
        $query = DB::connection('hospital')->query()->fromSub($grouped, 'g')
            ->leftJoin('pharm_locations as l', 'l.id', '=', 'g.loc_code')
            ->leftJoin('hcharge as f', 'f.chrgcode', '=', 'g.chrgcode')
            ->selectRaw("$medicine AS medicine, l.description AS location, f.chrgdesc AS fund,
                CONVERT(varchar(23), g.dmdprdte, 121) AS price_key,
                CASE WHEN g.opening_present > 0 THEN g.opening ELSE NULL END AS opening,
                CASE WHEN g.closing_present > 0 THEN g.closing ELSE NULL END AS closing")
            ->addSelect(array_map(fn ($key) => 'g.'.$key, array_diff(self::MOVEMENTS, ['opening', 'closing', 'opening_present', 'closing_present'])))
            ->when($f['medicine'] !== '', fn ($q) => $q->whereRaw("$medicine LIKE ?", ['%'.$f['medicine'].'%']));
        $query->selectRaw('g.dmdcomb AS raw_dmdcomb, g.dmdctr AS raw_dmdctr, g.loc_code AS raw_location, g.chrgcode AS raw_fund');

        return $query->orderBy('g.dmdcomb')->orderBy('g.dmdctr')->orderBy('g.chrgcode')->orderBy('g.dmdprdte');
    }
}
