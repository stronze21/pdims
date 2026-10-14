<?php
// Standalone regression scenarios. No Laravel bootstrap, PDO, network, or real database.
namespace DeliveryTest {
    class Failure extends \Exception {}
    class Store { public static $tables = []; }
    class Row extends \stdClass {
        public function __construct($values) { foreach ($values as $key => $value) $this->$key = $value; }
        public function save() {}
        public function items() { return new Query('items', [['delivery_id', $this->id]]); }
    }
    class Rows implements \Countable {
        private $rows;
        public function __construct($rows) { $this->rows = $rows; }
        public function count(): int { return count($this->rows); }
        public function isEmpty() { return !$this->rows; }
        public function contains($callback) { foreach ($this->rows as $row) if ($callback($row)) return true; return false; }
        public function firstWhere($key, $value) { foreach ($this->rows as $row) if ($row->$key == $value) return $row; return null; }
    }
    class Query {
        private $table, $filters, $descending = false;
        public function __construct($table, $filters = []) { $this->table = $table; $this->filters = $filters; }
        public function whereKey($id) { return $this->where('id', $id); }
        public function where($key, $value) { $this->filters[] = [$key, $value]; return $this; }
        public function lockForUpdate() { return $this; }
        public function orderBy($key) { return $this; }
        public function orderByDesc($key) { $this->descending = true; return $this; }
        private function matching() {
            $rows = array_values(array_filter(Store::$tables[$this->table], function ($row) {
                foreach ($this->filters as [$key, $value]) if ($row->$key != $value) return false;
                return true;
            }));
            if ($this->descending) usort($rows, function ($a, $b) { return $b->id <=> $a->id; });
            return $rows;
        }
        public function get() { return new Rows($this->matching()); }
        public function first() { return $this->matching()[0] ?? null; }
        public function firstOrFail() { return $this->first() ?? throw new Failure('Missing row'); }
        public function insert($values) { $values['id'] = count(Store::$tables[$this->table]) + 1; Store::$tables[$this->table][] = new Row($values); }
    }
    class Connection {
        public function transaction($callback) {
            $snapshot = serialize(Store::$tables);
            try { return $callback(); } catch (\Throwable $error) { Store::$tables = unserialize($snapshot); throw $error; }
        }
        public function table($name) { return new Query($name); }
    }
    class User { public $pharm_location_id = '1', $name = 'Test User'; public function can($name) { return $name === 'view-deliveries'; } }
    class Auth { public static $user; public function user() { return self::$user; } public function id() { return 7; } }
}
namespace Illuminate\Support\Facades {
    class DB { public static function connection($name) { if ($name !== 'hospital') throw new \DeliveryTest\Failure('Wrong connection'); return new \DeliveryTest\Connection; } }
}
namespace Illuminate\Validation {
    class ValidationException extends \DeliveryTest\Failure {
        public static function withMessages($messages) { return new self(implode(' ', $messages)); }
    }
}
namespace Carbon {
    class Carbon { public static function parse($value) { return new \DateTimeImmutable($value); } }
}
namespace App\Models\Pharmacy {
    class DeliveryDetail { public static function whereKey($id) { return (new \DeliveryTest\Query('deliveries'))->whereKey($id); } }
}
namespace App\Models\Pharmacy\Drugs {
    class DrugStock { public static function whereKey($id) { return (new \DeliveryTest\Query('stocks'))->whereKey($id); } }
    class DrugStockLog { public static function whereKey($id) { return (new \DeliveryTest\Query('logs'))->whereKey($id); } }
    class DrugStockCard { public static function on($name) { return new \DeliveryTest\Query('cards'); } }
}
namespace {
    use DeliveryTest\Store;
    use DeliveryTest\Row;
    function auth() { return new DeliveryTest\Auth; }
    function now() { return new DateTimeImmutable('2026-10-12 10:00:00'); }
    function abort_unless($condition, $status) { if (!$condition) throw new DeliveryTest\Failure('Forbidden'); }
    require __DIR__.'/../../app/Services/Pharmacy/DeliveryInventory.php';
    function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
    function seed($balance = 25) {
        DeliveryTest\Auth::$user = new DeliveryTest\User;
        $batch = ['dmdcomb' => 'drug', 'dmdctr' => '1', 'loc_code' => '1', 'chrgcode' => 'DRUMA', 'lot_no' => 'LOT', 'exp_date' => '2027-01-01', 'retail_price' => '10.00'];
        $items = []; $entries = [];
        foreach ([10, 15] as $index => $qty) {
            $id = $index + 1;
            $items[] = new Row(['id' => $id, 'delivery_id' => 1, 'status' => 'delivered', 'qty' => $qty, 'dmdcomb' => 'drug', 'dmdctr' => '1', 'pharm_location_id' => '1', 'charge_code' => 'DRUMA', 'lot_no' => 'LOT', 'expiry_date' => '2027-01-01', 'retail_price' => '10.00']);
            $entries[] = ['item_id' => $id, 'stock_id' => 1, 'log_id' => 1, 'card_id' => 1, 'qty' => $qty, 'drug' => 'Drug', 'lot_no' => 'LOT', 'expiry_date' => '2027-01-01'];
        }
        Store::$tables = [
            'deliveries' => [new Row(['id' => 1, 'status' => 'locked', 'pharm_location_id' => '1', 'updated_at' => now()])],
            'items' => $items,
            'stocks' => [new Row($batch + ['id' => 1, 'stock_bal' => $balance, 'beg_bal' => 25])],
            'logs' => [new Row(['id' => 1, 'purchased' => 25, 'issue_qty' => 3, 'pullout_qty' => 2, 'consumption_id' => null])],
            'cards' => [new Row(['id' => 1, 'rec' => 25, 'bal' => 25, 'iss' => 3])],
            'pharm_delivery_audits' => [new Row(['id' => 1, 'delivery_id' => 1, 'action' => 'locked', 'entries' => json_encode($entries)])],
            'pharm_consumption_log_details' => [],
        ];
    }
    function rejected($callback, $expected) {
        try { $callback(); } catch (DeliveryTest\Failure $error) {
            check(strpos($error->getMessage(), $expected) !== false, 'Unexpected rejection: '.$error->getMessage()); return;
        }
        throw new RuntimeException('Expected rejection');
    }
    $service = new App\Services\Pharmacy\DeliveryInventory;
    seed();
    $service->revert(1, 'Incorrect delivery receipt');
    check(Store::$tables['stocks'][0]->stock_bal === 0, 'Shared batch quantities must both be deducted');
    check(Store::$tables['logs'][0]->purchased === 0, 'Purchase receipt must be corrected');
    check(Store::$tables['cards'][0]->rec === 0, 'Receipt card must be corrected');
    check(Store::$tables['logs'][0]->issue_qty === 3 && Store::$tables['logs'][0]->pullout_qty === 2 && Store::$tables['cards'][0]->iss === 3, 'Reversal must not become consumption');
    check(Store::$tables['deliveries'][0]->status === 'pending', 'Delivery must reopen');
    check(count(Store::$tables['pharm_delivery_audits']) === 2, 'Audit must be retained');
    $audit = Store::$tables['pharm_delivery_audits'][1];
    $entries = json_decode($audit->entries, true);
    check($audit->user_id === '7' && $audit->reason === 'Incorrect delivery receipt' && $entries[1]['balance_after'] === 0, 'Audit must record actor, reason and balances');
    rejected(function () use ($service) { $service->revert(1, 'Duplicate reversal'); }, 'Only locked');
    seed(20); $before = serialize(Store::$tables);
    rejected(function () use ($service) { $service->revert(1, 'Incorrect delivery receipt'); }, 'Insufficient balance');
    check(serialize(Store::$tables) === $before, 'Shortage on second shared-batch line must roll back the first');
    seed(); Store::$tables['cards'][0]->rec = 20; $before = serialize(Store::$tables);
    rejected(function () use ($service) { $service->revert(1, 'Incorrect delivery receipt'); }, 'receipt quantities');
    check(serialize(Store::$tables) === $before, 'Receipt shortage must leave all records unchanged');
    seed(); DeliveryTest\Auth::$user->pharm_location_id = '2';
    rejected(function () use ($service) { $service->revert(1, 'Incorrect delivery receipt'); }, 'Forbidden');
    seed(); Store::$tables['logs'][0]->consumption_id = 9;
    Store::$tables['pharm_consumption_log_details'][] = new Row(['id' => 9, 'status' => 'closed', 'consumption_to' => '2026-10-11']);
    rejected(function () use ($service) { $service->revert(1, 'Incorrect delivery receipt'); }, 'closed consumption');
    seed(); Store::$tables['stocks'][0]->lot_no = 'OTHER';
    rejected(function () use ($service) { $service->revert(1, 'Incorrect delivery receipt'); }, 'reclassified');
    seed(); Store::$tables['logs'][0]->consumption_id = 9;
    Store::$tables['pharm_consumption_log_details'][] = new Row(['id' => 9, 'status' => 'open', 'consumption_to' => null]);
    $service->revert(1, 'Incorrect delivery receipt');
    check(Store::$tables['stocks'][0]->stock_bal === 0, 'An open reporting period must permit reversal');
    seed(0.3);
    Store::$tables['stocks'][0]->beg_bal = 0.3;
    Store::$tables['logs'][0]->purchased = 0.3;
    Store::$tables['cards'][0]->rec = Store::$tables['cards'][0]->bal = 0.3;
    $entries = json_decode(Store::$tables['pharm_delivery_audits'][0]->entries, true);
    foreach ([0.1, 0.2] as $index => $qty) {
        Store::$tables['items'][$index]->qty = $qty;
        $entries[$index]['qty'] = $qty;
    }
    Store::$tables['pharm_delivery_audits'][0]->entries = json_encode($entries);
    $service->revert(1, 'Incorrect fractional receipt');
    check(Store::$tables['stocks'][0]->stock_bal === 0, 'Decimal quantities must not cause false shortages');
    echo "PASS: shared-batch reversal, no consumption, audit, duplicate rejection, full rollback, receipt shortage, location access, closed/open periods, changed batch and decimal quantities.\n";
}
