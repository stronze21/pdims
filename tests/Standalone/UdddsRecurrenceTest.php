<?php

/** Run directly with PHP. No Laravel bootstrap, configuration, or real database classes. */
namespace Illuminate\Support\Facades {
    final class Schema
    {
        public static $intervalAvailable = true;
        public static function connection($name) { return new self; }
        public function hasColumn($table, $column) { return $column === 'uddds_interval_days' ? self::$intervalAvailable : true; }
        public function hasColumns($table, $columns) { return true; }
    }
    final class DB
    {
        public static $rows = [], $enrollments = [], $queue = [], $writes = [];
        public static function select($sql, $params = [])
        {
            if (strpos($sql, 'FROM hospital.dbo.hrxo') === false) throw new \RuntimeException('Unexpected fixture query');
            if (strpos($sql, 'WHERE hrxo.docointkey IN') !== false || strpos($sql, 'WHERE docointkey IN') !== false) {
                return array_values(array_intersect_key(self::$rows, array_flip($params)));
            }
            if (strpos($sql, 'AS is_actionable') !== false) return array_map(fn ($row) => clone $row, self::$queue);
            if (strpos($sql, "hrxo.estatus = 'S'") !== false) return self::$enrollments;
            throw new \RuntimeException('Unrecognized fixture query');
        }
        public static function selectOne($sql, $params)
        {
            if (strpos($sql, 'toecode FROM') !== false) return (object) ['toecode' => 'ADM'];
            if (strpos($sql, 'WHERE docointkey =') !== false) return self::$rows[$params[0]] ?? null;
            if (strpos($sql, 'WHERE uddds_source_docointkey =') !== false) {
                foreach (self::$rows as $row) {
                    if ($row->uddds_source_docointkey === $params[0] && substr($row->dodate, 0, 10) === $params[1]) return $row;
                }
                return null;
            }
            throw new \RuntimeException('Unexpected fixture lookup');
        }
        public static function update($sql, $params) { self::$writes[] = [$sql, $params]; return 1; }
        public static function connection($name) { throw new \RuntimeException('Real database access is forbidden in this test'); }
    }
}
namespace App\Models\Pharmacy\Dispensing {
    final class DrugOrder
    {
        private $key;
        public static function where($column, $key) { $query = new self; $query->key = $key; return $query; }
        public function first() { return \Illuminate\Support\Facades\DB::$rows[$this->key] ?? null; }
        public static function create($fields)
        {
            \Illuminate\Support\Facades\DB::$writes[] = $fields;
            \Illuminate\Support\Facades\DB::$rows[$fields['docointkey']] = (object) array_merge(['qtyissued' => null], $fields);
        }
    }
}
namespace App\Services\Pharmacy {
    final class DrugDescription { public static function fromMaster($alias) { return $alias.'.drug_concat'; } }
}
namespace {
    // Composer autoload only supplies Carbon; no application is created or bootstrapped.
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    require dirname(__DIR__, 2).'/app/Services/Pharmacy/UdddsSchedule.php';
    require dirname(__DIR__, 2).'/app/Services/Pharmacy/UdddsService.php';

    function now($timezone = null) { return \Carbon\Carbon::parse('2026-10-08 09:00:00', $timezone ?: 'Asia/Manila'); }
    function check($condition, $message) {
        global $checks;
        if (!$condition) throw new RuntimeException($message);
        $checks++;
    }
    function rejects(callable $operation, $message) {
        try { $operation(); } catch (InvalidArgumentException $e) { check(true, $message); return; }
        check(false, $message);
    }
    function fixture($days = 3, $key = 'source') {
        return (object) [
            'docointkey' => $key, 'enccode' => 'ADM1', 'hpercode' => 'P1',
            'dmdcomb' => 'D1', 'dmdctr' => 1, 'orderfrom' => 'F1', 'entryby' => 'E1',
            'has_tag' => false, 'tx_type' => 'service', 'ris' => false,
            'pchrgqty' => 2, 'pchrgup' => 5, 'pcchrgamt' => 10, 'qtyissued' => 2,
            'dmdprdte' => '2026-01-01', 'exp_date' => '2027-01-01', 'loc_code' => 'L1',
            'item_id' => 1, 'remarks' => '', 'prescription_data_id' => null,
            'prescribed_by' => 'E1', 'deptcode' => null, 'original_enccode' => null,
            'order_type' => 'BASIC', 'is_uddds' => 1, 'estatus' => 'S',
            'uddds_start_date' => '2026-10-08', 'uddds_end_date' => '2026-10-20',
            'uddds_interval_days' => $days, 'uddds_source_docointkey' => null,
            'dodate' => '2026-10-08 07:00:00', 'pcchrgcod' => null, 'is_actionable' => 1,
        ];
    }

    use App\Services\Pharmacy\UdddsSchedule as Schedule;
    use App\Services\Pharmacy\UdddsService as Service;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
    $checks = 0;
    foreach ([null, 1, 2, 3, 5, 7] as $step) {
        for ($offset = -1; $offset <= 13; $offset++) {
            $date = (new DateTimeImmutable('2026-10-08'))->modify(sprintf('%+d days', $offset))->format('Y-m-d');
            $expected = $offset >= 0 && $offset <= 12 && $offset % ($step ?? 1) === 0;
            check(Schedule::due('2026-10-08', '2026-10-20', $step, $date) === $expected, 'Anchored recurrence and inclusive boundaries');
        }
    }
    check(Schedule::due('2026-12-30', '2027-01-05', 3, '2027-01-02'), 'Year transition');
    check(Schedule::due('2028-02-28', '2028-03-04', 2, '2028-03-01'), 'Leap-year transition');
    check(Schedule::due('2026-01-31', '2026-02-04', 2, '2026-02-02'), 'Month transition');
    check(Schedule::next('2026-10-08', '2026-10-20', 3, '2026-10-09') === '2026-10-11', 'Next due supply');
    check(Schedule::next('2026-10-08', '2026-10-20', 3, '2026-10-08') === '2026-10-08', 'Next includes today');
    check(Schedule::next('2026-10-08', '2026-10-20', 3, '2026-10-21') === null, 'Expired schedule');
    check(Schedule::next('2026-10-08', '2026-10-20', 2147483647, '2026-10-09') === null, 'Large interval avoids date overflow');
    check(Schedule::date(new DateTimeImmutable('2026-10-07 20:00:00', new DateTimeZone('UTC')))->format('Y-m-d') === '2026-10-08', 'Manila calendar boundary');
    foreach ([0, -1, '1.5', 1.5, '', '0', '01', true, [], '2147483648', '999999999999'] as $invalid) {
        rejects(fn () => Schedule::interval($invalid), 'Invalid interval rejected');
    }
    rejects(fn () => Schedule::due('2026-02-30', '2026-03-08', 2, '2026-03-01'), 'Invalid calendar date');
    rejects(fn () => Schedule::due('2026-10-10', '2026-10-08', 2, '2026-10-09'), 'Reversed bounds');
    rejects(fn () => Schedule::due(null, '2026-10-08', 2, '2026-10-08'), 'Missing start');

    $service = new Service;
    $source = fixture();
    DB::$rows = ['source' => $source]; DB::$enrollments = [$source];
    check($service->generateDaily('2026-10-09', true)['count'] === 0, 'Generator skips off day');
    check($service->generateDaily('2026-10-11', true)['count'] === 1, 'Generator includes due day');
    check($service->generateDaily('2026-10-08', true)['count'] === 0, 'Original issued day avoids duplicate');
    DB::$queue = [$source];
    check($service->wardItemsForDate(null, 'L1', '2026-10-09') === [], 'Virtual queue skips off day');
    $queue = $service->wardItemsForDate(null, 'L1', '2026-10-11');
    check(count($queue) === 1 && $queue[0]->next_supply_date === '2026-10-11', 'Virtual due queue and summary');
    rejects(fn () => $service->materializeDailyItems(['source'], '2026-10-09'), 'Materializer rejects off day');
    check(DB::$writes === [], 'Off day creates no records');
    $keys = $service->materializeDailyItems(['source'], '2026-10-11');
    $clone = DB::$rows[$keys[0]];
    check($clone->pchrgqty === 2 && $clone->uddds_interval_days === 3, 'Clone retains quantity and interval');
    $writeCount = count(DB::$writes);
    check($service->materializeDailyItems(['source'], '2026-10-11') === $keys && count(DB::$writes) === $writeCount, 'Repeated materialization reuses existing order');
    check($service->generateDaily('2026-10-11', true)['count'] === 0, 'Generator sees materialized duplicate');
    $source->uddds_interval_days = 2;
    rejects(fn () => $service->materializeDailyItems($keys, '2026-10-11'), 'Stale pending clone rejected');
    check(!$service->chargeAndIssue($keys, 'L1', [], ['fallback' => ['F2']])['ok'], 'Alternate fund processing cannot bypass schedule guard');
    check($service->generatedOrderProblem($keys) !== null, 'Ordinary dispensing rejects stale clone');
    DB::$queue = [$clone];
    check($service->wardItemsForDate(null, 'L1', '2026-10-11') === [], 'Stale pending clone hidden');
    $source->uddds_interval_days = 3; $source->is_uddds = 0;
    rejects(fn () => $service->materializeDailyItems($keys, '2026-10-11'), 'Removed source rejected');
    check(!$service->chargeAndIssue($keys, 'L1', [])['ok'], 'Removed enrollment cannot be processed');
    $source->is_uddds = 1; $source->uddds_interval_days = 0; DB::$queue = [$source];
    rejects(fn () => $service->generateDaily('2026-10-11', true), 'Invalid data never defaults to daily');
    $queue = $service->wardItemsForDate(null, 'L1', '2026-10-11');
    check(count($queue) === 1 && $queue[0]->is_actionable === 0 && $queue[0]->schedule_error, 'Invalid schedule visible but disabled');
    $source->uddds_interval_days = null;
    check($service->generateDaily('2026-10-09', true)['count'] === 1, 'Legacy null interval remains daily');
    Schema::$intervalAvailable = false;
    check(!$service->enrollSingleOrder('source', 'BASIC', '2026-10-08', '2026-10-20', 2)['ok'], 'Missing schema blocks interval enrollment');
    $source->is_uddds = 0;
    check($service->enrollSingleOrder('source', 'BASIC', '2026-10-08', '2026-10-20', 1)['ok'], 'Daily enrollment works without new column');
    [$sql, $params] = DB::$writes[count(DB::$writes) - 1];
    check(strpos($sql, 'uddds_interval_days') === false && count($params) === 3, 'Legacy enrollment does not reference absent column');
    Schema::$intervalAvailable = true; $source->is_uddds = 1;
    check(!$service->enrollSingleOrder('source', 'BASIC', '2026-10-08', '2026-10-20', 2)['ok'], 'Active enrollment cannot be changed');
    $source->is_uddds = 0;
    check($service->enrollIssuedOrders(['source'], '2026-10-08', '2026-10-20', 5)['ok'], 'Batch accepts custom recurrence');
    [$sql, $params] = DB::$writes[count(DB::$writes) - 1];
    check(strpos($sql, 'uddds_interval_days = ?') !== false && $params[2] === 5, 'Batch persists recurrence');
    $source->order_type = 'G24';
    check(!$service->enrollSingleOrder('source', 'BASIC', '2026-10-08', '2026-10-20', 2)['ok'], 'G24 cannot be converted to recurring by request');
    $source->order_type = 'OR';
    check(!$service->enrollSingleOrder('source', 'BASIC', '2026-10-08', '2026-10-20', 2)['ok'], 'OR cannot be converted to recurring by request');
    $source->order_type = 'BASIC';
    $active = fixture(2, 'active'); DB::$rows['active'] = $active;
    $before = count(DB::$writes);
    check(!$service->enrollIssuedOrders(['source', 'active'], '2026-10-08', '2026-10-20', 5)['ok'] && count(DB::$writes) === $before, 'Batch refusal happens before any enrollment writes');
    $source->is_uddds = 1; $source->uddds_interval_days = 3;
    rejects(fn () => $service->materializeDailyItems(['source', 'missing'], '2026-10-14'), 'Missing selected order rejected before materialization');
    check(count(DB::$writes) === $before, 'Missing selection creates no partial clones');
    $invalid = fixture(0, 'invalid'); DB::$enrollments = [$source, $invalid];
    rejects(fn () => $service->generateDaily('2026-10-14'), 'Invalid schedule preflight rejects entire generator run');
    check(count(DB::$writes) === $before, 'Invalid generator run creates no partial clones');
    $source->uddds_interval_days = null; DB::$enrollments = [$source];
    Schema::$intervalAvailable = false;
    $daily = $service->materializeDailyItems(['source'], '2026-10-09');
    check($daily[0] !== $keys[0], 'Different supply dates receive distinct order keys');
    check(!property_exists(DB::$rows[$daily[0]], 'uddds_interval_days'), 'Legacy clone avoids missing column');
    Schema::$intervalAvailable = true;
    $clone->estatus = 'S'; $clone->qtyissued = 2;
    $source->uddds_interval_days = 2; DB::$queue = [$clone];
    $history = $service->wardItemsForDate(null, 'L1', '2026-10-11');
    check(count($history) === 1, 'Issued historical clone remains visible after schedule change');
    $source->uddds_interval_days = 5; $source->is_uddds = 0;
    check($service->activateOnIssued(['source'])['ok'], 'Issued enrollment activates using its saved interval');
    check($source->uddds_interval_days === 5, 'Activation does not reset custom recurrence');
    $before = count(DB::$writes);
    check($service->activateOnIssued([$keys[0]])['ok'] && count(DB::$writes) === $before, 'Issued clone never becomes a new source enrollment');
    echo 'PASS: '.$checks." database-free recurrence checks\n";
}
