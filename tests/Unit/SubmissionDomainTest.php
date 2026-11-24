<?php

use App\Events\Pharmacy\QueueStatusChanged;
use App\Livewire\Pharmacy\Drugs\StockList;
use App\Models\Pharmacy\Drugs\DrugStock;
use App\Models\Pharmacy\Drugs\InOutTransaction;
use App\Services\Pharmacy\InventoryJob;
use App\Services\Pharmacy\InventoryMutation;
use App\Services\Pharmacy\PrescriptionQueueService;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function () {
    foreach (['hospital', 'webapp'] as $name) {
        config()->set("database.connections.$name", ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge($name);
        // Compoships constructs its own query grammar. Adapt qualified SQL at the
        // isolated connection boundary instead of changing production model tables.
        DB::extend($name, function ($config, $name) {
            $config['name'] = $name;
            return new class(new PDO('sqlite::memory:'), ':memory:', '', $config) extends \Illuminate\Database\SQLiteConnection {
                protected function run($query, $bindings, \Closure $callback) {
                    return parent::run(str_replace(['"hospital"."dbo".', '"webapp"."dbo".'], '', $query), $bindings, $callback);
                }
            };
        });
        $connection = DB::connection($name);
        $connection->setQueryGrammar(new class($connection) extends SQLiteGrammar {
            public function wrapTable($table, $prefix = null) {
                return parent::wrapTable(str_replace(['hospital.dbo.', 'webapp.dbo.'], '', $table), $prefix);
            }
        });
    }
    Schema::connection('hospital')->create('pharm_submission_receipts', function (Blueprint $t) {
        $t->string('id')->primary(); $t->string('fingerprint'); $t->string('state'); $t->text('response')->nullable(); $t->timestamps();
    });
    Schema::connection('hospital')->create('pharm_drug_stocks', function (Blueprint $t) {
        $t->id(); $t->string('dmdcomb'); $t->integer('dmdctr'); $t->string('chrgcode');
        $t->integer('loc_code'); $t->date('exp_date'); $t->decimal('stock_bal'); $t->timestamps();
    });
    Schema::connection('hospital')->create('pharm_stock_adjustments', function (Blueprint $t) {
        $t->id(); $t->integer('stock_id'); $t->integer('user_id'); $t->decimal('from_qty'); $t->decimal('to_qty'); $t->timestamps();
    });
    Schema::connection('hospital')->create('pharm_io_trans', function (Blueprint $t) {
        $t->id(); $t->string('trans_stat'); $t->timestamps();
    });
    Schema::connection('hospital')->create('hopdlog', function (Blueprint $t) {
        $t->string('enccode'); $t->string('tscode')->nullable();
    });
    Schema::connection('webapp')->create('prescription_queues', function (Blueprint $t) {
        $t->id(); $t->integer('prescription_id'); $t->string('enccode'); $t->string('hpercode');
        $t->string('queue_number'); $t->string('queue_prefix'); $t->integer('sequence_number');
        $t->string('clinic_code')->nullable();
        $t->string('location_code'); $t->string('queue_status'); $t->string('priority');
        foreach (['queued_at', 'preparing_at', 'charging_at', 'ready_at', 'dispensed_at', 'cancelled_at'] as $field) $t->dateTime($field)->nullable();
        foreach (['prepared_by', 'charged_by', 'dispensed_by', 'cancelled_by', 'cancellation_reason', 'remarks', 'created_from'] as $field) $t->string($field)->nullable();
        $t->integer('estimated_wait_minutes')->nullable(); $t->softDeletes(); $t->timestamps();
    });
    Schema::connection('webapp')->create('prescription_queue_logs', function (Blueprint $t) {
        $t->id(); $t->integer('queue_id'); $t->string('status_from')->nullable(); $t->string('status_to');
        $t->string('changed_by')->nullable(); $t->string('remarks')->nullable(); $t->timestamps();
    });
});

function submissionStock($qty = 10) {
    return DrugStock::create(['dmdcomb' => 'drug', 'dmdctr' => 1, 'chrgcode' => 'DRUMA', 'loc_code' => 1, 'exp_date' => '2099-01-01', 'stock_bal' => $qty]);
}

it('does not duplicate adjustment audit records for the same target balance', function () {
    Auth::shouldReceive('id')->andReturn(1);
    $stock = submissionStock();
    $component = new StockList;
    $component->setId('stock-test');
    $component->selectedStockId = $stock->id;
    $component->qty = 6;
    $component->adjust_qty();
    $component->adjust_qty();
    expect((float) $stock->fresh()->stock_bal)->toBe(6.0)
        ->and(DB::connection('hospital')->table('pharm_stock_adjustments')->count())->toBe(1);
});

it('rejects an excessive pull-out before creating any records', function () {
    $stock = submissionStock(3);
    $component = new StockList;
    $component->selectedStockId = $stock->id;
    $component->qty = 4;
    expect(fn () => $component->pull_out())->toThrow(ValidationException::class);
    expect((float) $stock->fresh()->stock_bal)->toBe(3.0);
});

it('checks cumulative dispensing quantities for orders sharing a stock pool', function () {
    submissionStock(5);
    $order = (object) ['dmdcomb' => 'drug', 'dmdctr' => 1, 'orderfrom' => 'DRUMA', 'pchrgqty' => 3, 'ris' => false];
    expect(fn () => app(InventoryMutation::class)->run(fn () => app(InventoryMutation::class)->reserveStock([$order, clone $order], 1)))
        ->toThrow(ValidationException::class);
});

it('rejects stale IO issuance and makes repeated receipt a no-op', function ($class) {
    $txn = InOutTransaction::create(['trans_stat' => 'Issued']);
    $component = new $class;
    $component->selected_request = $txn;
    expect(fn () => $component->issueRequest())->toThrow(ValidationException::class);
    $txn->update(['trans_stat' => 'Received']);
    // No item/stock tables are needed: the fresh state must stop before any receipt work.
    $component->receiveIssued($txn);
    expect($txn->fresh()->trans_stat)->toBe('Received');
})->with([
    App\Livewire\Pharmacy\Drugs\IoTransactions::class,
    App\Livewire\Pharmacy\Drugs\ViewIoTransRef::class,
    App\Livewire\Pharmacy\Drugs\ViewIoTransDate::class,
]);

it('returns the existing daily queue and does not repeat status timestamps logs or events', function () {
    Event::fake([QueueStatusChanged::class]);
    $service = app(PrescriptionQueueService::class);
    $data = ['prescription_id' => 123, 'enccode' => 'enc', 'hpercode' => 'patient', 'location_code' => '1'];
    $first = $service->createQueue($data);
    $second = $service->createQueue($data);
    expect($first['success'])->toBeTrue()->and($second['success'])->toBeTrue()
        ->and($second['queue']->id)->toBe($first['queue']->id);
    $changed = $service->updateQueueStatus($first['queue']->id, 'ready');
    $this->travel(1)->minute();
    $repeated = $service->updateQueueStatus($first['queue']->id, 'ready');
    expect($repeated['success'])->toBeTrue()
        ->and((string) $repeated['queue']->ready_at)->toBe((string) $changed['queue']->ready_at)
        ->and(DB::connection('webapp')->table('prescription_queues')->count())->toBe(1)
        ->and(DB::connection('webapp')->table('prescription_queue_logs')->count())->toBe(2);
    Event::assertDispatchedTimes(QueueStatusChanged::class, 1);
});

it('rolls back both the job receipt and its writes on failure and safely retries', function () {
    $runner = app(InventoryJob::class);
    expect(fn () => $runner->run('test-job', 'retry-key', function () {
        submissionStock();
        throw new RuntimeException('Simulated failure after the write');
    }))->toThrow(RuntimeException::class);
    expect(DrugStock::count())->toBe(0)->and(DB::connection('hospital')->table('pharm_submission_receipts')->count())->toBe(0);
    $runner->run('test-job', 'retry-key', fn () => submissionStock());
    $runner->run('test-job', 'retry-key', fn () => submissionStock());
    expect(DrugStock::count())->toBe(1)->and(DB::connection('hospital')->table('pharm_submission_receipts')->count())->toBe(1);
});
