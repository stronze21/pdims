<?php

/** Isolated fixtures; no Laravel bootstrap, connection resolver or database execution. */
namespace Illuminate\Support\Facades {
    final class Schema
    {
        public static $available = [];
        public static function connection($name) { return new self; }
        public function getColumnListing($table) { return self::$available[$table] ?? []; }
    }
    final class DB
    {
        public static $issued = [], $lookups = 0;
        public static function selectOne($sql, $params)
        {
            if (strpos($sql, 'FROM hospital.dbo.hrxoissue WHERE docointkey = ? AND enccode = ? AND hpercode = ? AND dmdcomb = ? AND dmdctr = ?') === false) {
                throw new \RuntimeException('Unexpected fixture query');
            }
            self::$lookups++;
            foreach (self::$issued as $row) {
                if ([$row->docointkey, $row->enccode, $row->hpercode, $row->dmdcomb, $row->dmdctr] === $params) return $row;
            }
            return null;
        }
        public static function connection($name) { throw new \RuntimeException('Database connections are forbidden in this runner'); }
    }
}
namespace App\Models\Pharmacy\Dispensing {
    final class DrugOrder
    {
        public static $rows = [], $lookups = 0;
        public static function find($key) { self::$lookups++; return self::$rows[$key] ?? null; }
    }
}
namespace {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    require dirname(__DIR__, 2).'/app/Services/Pharmacy/UdddsTransactionMetadata.php';
    require dirname(__DIR__, 2).'/app/Models/Pharmacy/Dispensing/DrugOrderIssue.php';
    require dirname(__DIR__, 2).'/app/Models/Pharmacy/Dispensing/DrugOrderReturn.php';

    use App\Services\Pharmacy\UdddsTransactionMetadata as Metadata;
    use App\Models\Pharmacy\Dispensing\DrugOrder;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    // Only fire the in-memory creating event. Never call save/create/updateOrCreate.
    class IssueFixture extends \App\Models\Pharmacy\Dispensing\DrugOrderIssue
    {
        protected $dateFormat = 'Y-m-d H:i:s';
        public function simulateCreation(): void { $this->fireModelEvent('creating'); }
    }
    class ReturnFixture extends \App\Models\Pharmacy\Dispensing\DrugOrderReturn
    {
        protected $dateFormat = 'Y-m-d H:i:s';
        public function simulateCreation(): void { $this->fireModelEvent('creating'); }
    }
    \Illuminate\Database\Eloquent\Model::setEventDispatcher(new \Illuminate\Events\Dispatcher(new \Illuminate\Container\Container));
    set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
    $checks = 0;
    function check($condition, $message) {
        global $checks;
        if (!$condition) throw new RuntimeException($message);
        $checks++;
    }

    $order = (object) [
        'docointkey' => 'D1', 'enccode' => 'E1', 'hpercode' => 'P1', 'dmdcomb' => 'MED1', 'dmdctr' => '1',
        'order_type' => 'BASIC', 'is_uddds' => true, 'uddds_start_date' => '2026-10-08',
        'uddds_end_date' => '2026-10-20', 'uddds_source_docointkey' => 'SOURCE1', 'uddds_interval_days' => 3,
    ];
    $expected = [
        'order_type' => 'BASIC', 'is_uddds' => 1, 'uddds_start_date' => '2026-10-08',
        'uddds_end_date' => '2026-10-20', 'uddds_source_docointkey' => 'SOURCE1', 'uddds_interval_days' => 3,
    ];
    Schema::$available = ['hrxoissue' => Metadata::COLUMNS, 'hrxoreturn' => Metadata::COLUMNS];
    DrugOrder::$rows = ['D1' => $order];
    check(Metadata::forIssue($order) === $expected, 'All six issue fields match the order');
    check(Metadata::forReturn($order) === $expected, 'Return without an issue snapshot falls back to the order');
    foreach ([new IssueFixture, new ReturnFixture] as $model) {
        foreach (Metadata::COLUMNS as $column) check($model->isFillable($column), 'Transaction field is mass assignable');
    }
    $issue = new IssueFixture(['docointkey' => 'D1']);
    $issue->simulateCreation();
    check($issue->uddds_interval_days === 3 && $issue->is_uddds === true, 'Issue creation hook captures interval and flag');
    check($issue->uddds_start_date->format('Y-m-d') === '2026-10-08', 'Issue date casts retain the calendar date');
    check($issue->uddds_source_docointkey === 'SOURCE1' && $issue->order_type === 'BASIC', 'Issue captures source linkage and type');

    $recorded = clone $order;
    $recorded->issuedte = '2026-10-11'; $recorded->issuetme = '07:00:00';
    DB::$issued = [$recorded];
    $order->uddds_interval_days = 2; $order->is_uddds = false; $order->uddds_end_date = '2026-11-01';
    check(Metadata::forReturn($order) === $expected, 'Return retains issue schedule after removal or schedule change');
    $return = new ReturnFixture(['docointkey' => 'D1']);
    $return->simulateCreation();
    check($return->uddds_interval_days === 3 && $return->is_uddds === true, 'Return creation hook uses the issued snapshot');
    check($return->uddds_end_date->format('Y-m-d') === '2026-10-20', 'Return uses original enrollment boundary');
    $issue->simulateCreation();
    check($issue->uddds_interval_days === 3 && $issue->is_uddds === true, 'Existing populated snapshot is not overwritten');

    $explicit = new IssueFixture(array_merge(['docointkey' => 'D1'], $expected));
    $before = DrugOrder::$lookups;
    $explicit->simulateCreation();
    check(DrugOrder::$lookups === $before && $explicit->uddds_interval_days === 3, 'Explicit transaction-time snapshots are preserved without source lookup');
    $recorded->uddds_interval_days = null;
    check(Metadata::forReturn($order)['uddds_interval_days'] === null, 'Captured null daily interval is not replaced with current interval');
    $recorded->is_uddds = 0;
    check(Metadata::forReturn($order)['is_uddds'] === 0, 'Captured false flag is recognized as metadata');

    $different = clone $recorded; $different->enccode = 'OTHER'; DB::$issued = [$different];
    check(Metadata::forReturn($order)['uddds_interval_days'] === 2, 'Return lookup does not mix different encounters');
    $legacy = clone $recorded;
    foreach (Metadata::COLUMNS as $column) $legacy->{$column} = null;
    DB::$issued = [$legacy];
    check(Metadata::forReturn($order)['uddds_interval_days'] === 2, 'Legacy row without captured fields falls back to current order');

    Schema::$available['hrxoissue'] = [];
    $before = DB::$lookups;
    check(Metadata::forIssue($order) === [], 'Missing issue columns retain legacy writes');
    check(Metadata::forReturn($order)['uddds_interval_days'] === 2 && DB::$lookups === $before, 'No issue snapshot query when issue columns are absent');
    Schema::$available['hrxoreturn'] = [];
    check(Metadata::forReturn($order) === [], 'Missing return columns retain legacy writes');
    $missing = new IssueFixture(['docointkey' => 'D1']);
    $before = DrugOrder::$lookups;
    $missing->simulateCreation();
    check(DrugOrder::$lookups === $before && !array_key_exists('uddds_interval_days', $missing->getAttributes()), 'Legacy model creation does not add undeployed columns');

    Schema::$available['hrxoissue'] = ['order_type', 'is_uddds'];
    check(Metadata::forIssue($order) === ['order_type' => 'BASIC', 'is_uddds' => 0], 'Partial schema only receives available columns');
    $dated = clone $order; $dated->uddds_start_date = new DateTimeImmutable('2026-10-08');
    check(Metadata::snapshot($dated, Metadata::COLUMNS)['uddds_start_date'] === '2026-10-08', 'Date objects bind as calendar dates');
    echo 'PASS: '.$checks." database-free transaction metadata checks\n";
}
