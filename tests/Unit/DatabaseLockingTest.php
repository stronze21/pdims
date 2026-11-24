<?php

namespace Tests\Unit;

use App\Models\Pharmacy\Drugs\DrugStockCard;
use App\Services\Pharmacy\UdddsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseLockingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The default session deliberately has no hospital tables.
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        config()->set('database.connections.hospital', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        DB::purge('hospital');
        DB::extend('hospital', function ($config, $name) {
            $config['name'] = $name;
            return new class(new \PDO('sqlite::memory:'), ':memory:', '', $config) extends \Illuminate\Database\SQLiteConnection {
                protected function run($query, $bindings, \Closure $callback)
                {
                    return parent::run(str_replace(['"hospital"."dbo".', 'hospital.dbo.'], '', $query), $bindings, $callback);
                }
            };
        });
        DB::connection('hospital')->getSchemaBuilder()->create('hrxo', function (Blueprint $table) {
            $table->string('docointkey')->primary();
            $table->string('uddds_source_docointkey')->nullable();
            $table->boolean('is_uddds');
        });
    }

    protected function tearDown(): void
    {
        DB::purge('hospital');
        DB::purge('sqlite');
        DB::forgetExtension('hospital');
        parent::tearDown();
    }

    public function test_uddds_reads_and_writes_use_the_inventory_session_and_roll_back(): void
    {
        $service = new class extends UdddsService {
            public static function hasHrxoColumns(): bool { return true; }
        };
        $db = DB::connection('hospital');
        $db->table('hrxo')->insert(['docointkey' => 'ORDER', 'is_uddds' => 1]);
        $db->beginTransaction();
        try {
            $this->assertNull($service->generatedOrderProblem(['ORDER']));
            $this->assertTrue($service->removeFromUddds('ORDER')['ok']);
            $this->assertSame(0, (int) $db->table('hrxo')->value('is_uddds'));
        } finally {
            $db->rollBack();
        }
        $this->assertSame(1, (int) $db->table('hrxo')->value('is_uddds'));
    }

    public function test_stock_cards_join_the_inventory_transaction(): void
    {
        $db = DB::connection('hospital');
        $db->getSchemaBuilder()->create('pharm_drug_stock_cards', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->timestamps();
        });
        $db->beginTransaction();
        try {
            DrugStockCard::create(['reference' => 'TEST']);
            $this->assertSame(1, $db->table('pharm_drug_stock_cards')->count());
        } finally {
            $db->rollBack();
        }
        $this->assertSame(0, $db->table('pharm_drug_stock_cards')->count());
    }
}
