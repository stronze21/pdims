<?php

namespace Tests\Unit;

use App\Services\Pharmacy\DrugStockDefinitionSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DrugStockDefinitionSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.hospital', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('hospital');
        $db = DB::connection('hospital');
        $schema = $db->getSchemaBuilder();
        foreach (['hgen' => ['gencode', 'gendesc'], 'hdruggrp' => ['grpcode', 'gencode'],
            'hstre' => ['strecode', 'stredesc'], 'hform' => ['formcode', 'formdesc'],
            'hroute' => ['rtecode', 'rtedesc'],
            'hdmhdr' => ['dmdcomb', 'dmdctr', 'grpcode', 'brandname', 'dmdnost', 'dmdnnostp', 'strecode', 'formcode', 'rtecode'],
            'pharm_drug_stocks' => ['dmdcomb', 'dmdctr', 'loc_code', 'drug_concat', 'dmdnost', 'strecode', 'stock_bal', 'retail_price', 'exp_date', 'lot_no'],
        ] as $table => $columns) {
            $schema->create($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->string($column)->nullable();
                }
            });
        }
        $db->table('hgen')->insert(['gencode' => 'G', 'gendesc' => 'Example']);
        $db->table('hdruggrp')->insert(['grpcode' => 'GR', 'gencode' => 'G']);
        $db->table('hstre')->insert(['strecode' => '150', 'stredesc' => 'mg/ml']);
        $db->table('hform')->insert(['formcode' => 'BOT', 'formdesc' => 'bottle']);
        $db->table('hroute')->insert(['rtecode' => 'OP', 'rtedesc' => 'Ophthalmic']);
        $db->table('hdmhdr')->insert(['dmdcomb' => 'D', 'dmdctr' => '1', 'grpcode' => 'GR',
            'dmdnost' => '10', 'strecode' => '150', 'formcode' => 'BOT', 'rtecode' => 'OP']);
        foreach ([['D', '1', 'A'], ['D', '1', 'B'], ['D', '2', 'A']] as [$code, $counter, $location]) {
            $db->table('pharm_drug_stocks')->insert(['dmdcomb' => $code, 'dmdctr' => $counter,
                'loc_code' => $location, 'drug_concat' => 'Old label', 'dmdnost' => '5', 'strecode' => 'OLD',
                'stock_bal' => '42', 'retail_price' => '20', 'exp_date' => '2028-01-01', 'lot_no' => 'LOT']);
        }
    }

    protected function tearDown(): void
    {
        DB::purge('hospital');
        parent::tearDown();
    }

    public function test_sync_updates_strength_and_label_across_locations_without_changing_stock_details(): void
    {
        $this->assertSame(2, app(DrugStockDefinitionSync::class)->syncDrug('D', '1'));
        $rows = DB::connection('hospital')->table('pharm_drug_stocks')->where('dmdctr', '1')->get();
        foreach ($rows as $row) {
            $this->assertSame('10', $row->dmdnost);
            $this->assertSame('150', $row->strecode);
            $this->assertStringContainsString('10mg/ml bottle', $row->drug_concat);
            $parts = explode('_,', $row->drug_concat, 2);
            $this->assertCount(2, $parts);
            $this->assertSame('Example', $parts[0]);
            $this->assertSame('10mg/ml bottle', trim($parts[1]));
            $this->assertStringNotContainsString('Ophthalmic', $row->drug_concat);
            $this->assertSame('42', $row->stock_bal);
            $this->assertSame('20', $row->retail_price);
            $this->assertSame('2028-01-01', $row->exp_date);
            $this->assertSame('LOT', $row->lot_no);
        }
        $this->assertSame('Old label', DB::connection('hospital')->table('pharm_drug_stocks')->where('dmdctr', '2')->value('drug_concat'));
        $this->assertSame('OP', DB::connection('hospital')->table('hdmhdr')->value('rtecode'));
    }

    public function test_location_sync_leaves_other_locations_and_missing_master_records_unchanged(): void
    {
        $this->assertSame(1, app(DrugStockDefinitionSync::class)->syncLocation('A'));
        $db = DB::connection('hospital');
        $this->assertSame('Old label', $db->table('pharm_drug_stocks')->where('loc_code', 'B')->value('drug_concat'));
        $this->assertSame('Old label', $db->table('pharm_drug_stocks')->where('dmdctr', '2')->value('drug_concat'));
        $db->table('hdmhdr')->where('dmdctr', '1')->update(['dmdnost' => '25']);
        app(DrugStockDefinitionSync::class)->syncLocation('A');
        $this->assertSame('25', $db->table('pharm_drug_stocks')->where('loc_code', 'A')->where('dmdctr', '1')->value('dmdnost'));
    }
}
