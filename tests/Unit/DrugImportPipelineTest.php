<?php

use App\Models\Pharmacy\DrugImportBatch;
use App\Services\Pharmacy\DrugImport\DrugImportCommitter;
use App\Services\Pharmacy\DrugImport\DrugImportMapper;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.connections.hospital', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]);
    DB::purge('hospital');
    $schema = Schema::connection('hospital');
    $schema->create('hgen', fn (Blueprint $t) => [$t->string('gencode', 5)->primary(), $t->string('gendesc')]);
    $schema->create('hdruggrp', fn (Blueprint $t) => [$t->string('grpcode', 10)->primary(), $t->string('gencode', 5), $t->string('grpstat', 1)]);
    $schema->create('hstre', fn (Blueprint $t) => [$t->string('strecode', 5)->primary(), $t->string('stredesc')]);
    $schema->create('hform', fn (Blueprint $t) => [$t->string('formcode', 5)->primary(), $t->string('formdesc')]);
    $schema->create('hroute', fn (Blueprint $t) => [$t->string('rtecode', 5)->primary(), $t->string('rtedesc')]);
    $schema->create('huom', fn (Blueprint $t) => [$t->string('uomcode', 5)->primary(), $t->string('uomdesc')]);
    $schema->create('hdmhdr', function (Blueprint $t) {
        $t->string('dmdcomb', 12);
        $t->unsignedTinyInteger('dmdctr');
        $t->string('grpcode', 10)->nullable();
        $t->decimal('dmdnost', 12, 2);
        $t->string('strecode', 5);
        $t->string('formcode', 5);
        $t->string('rtecode', 5)->nullable();
        $t->string('brandname', 30)->nullable();
        $t->string('dmdpndf', 1);
        $t->string('dmdrxot', 3)->nullable();
        $t->string('dmdstat', 1)->nullable();
        $t->string('dmdlock', 1)->nullable();
        $t->string('dmdupsw', 1)->nullable();
        $t->dateTime('dmddtmd')->nullable();
        $t->string('dmdnnostp', 1)->nullable();
        $t->string('atcode', 5)->nullable();
        $t->string('techspec', 255)->nullable();
        $t->decimal('begbal', 18)->nullable();
        $t->decimal('stockbal', 12, 2)->nullable();
        $t->dateTime('baldteasof')->nullable();
        $t->string('lot_no', 15);
        $t->decimal('packvolno', 12, 2)->nullable();
        $t->string('packvolunitcode', 5)->nullable();
        $t->string('drug_concat', 255)->nullable();
        $t->primary(['dmdcomb', 'dmdctr']);
    });
    (require database_path('migrations/2026_08_03_000001_create_drug_import_tables.php'))->up();
    DB::connection('hospital')->table('hgen')->insert([['gencode' => 'GEN1', 'gendesc' => 'Paracetamol'], ['gencode' => 'GEN2', 'gendesc' => 'Example Drug']]);
    DB::connection('hospital')->table('hdruggrp')->insert([['grpcode' => 'GRP1', 'gencode' => 'GEN1', 'grpstat' => 'A'], ['grpcode' => 'GRP2', 'gencode' => 'GEN2', 'grpstat' => 'A']]);
    DB::connection('hospital')->table('hstre')->insert([
        ['strecode' => 'MG', 'stredesc' => 'mg'], ['strecode' => 'MG/ML', 'stredesc' => 'mg/mL'], ['strecode' => '%', 'stredesc' => '%'],
    ]);
    DB::connection('hospital')->table('hform')->insert([
        ['formcode' => 'TAB', 'formdesc' => 'Tablet'], ['formcode' => 'AMP', 'formdesc' => 'ampul'],
        ['formcode' => 'SOL', 'formdesc' => 'solution'],
    ]);
    DB::connection('hospital')->table('hroute')->insert([
        ['rtecode' => 'PO', 'rtedesc' => 'Oral'], ['rtecode' => 'IV', 'rtedesc' => 'Intravenous'],
        ['rtecode' => 'TP', 'rtedesc' => 'Topical'], ['rtecode' => 'IM', 'rtedesc' => 'Intramuscular'],
    ]);
    DB::connection('hospital')->table('huom')->insert(['uomcode' => 'ML', 'uomdesc' => 'mL']);
});

function pipelineBatch(array $candidates): DrugImportBatch
{
    $batch = DrugImportBatch::query()->create([
        'source_filename' => 'test.xlsx', 'source_hash' => str_repeat('a', 64), 'source_adapter' => 'test',
        'status' => 'validating', 'uploaded_by' => 'tester',
    ]);
    app(DrugImportMapper::class)->stage($batch, $candidates);

    return $batch->refresh();
}

function pipelineCandidate(string $generic = 'Paracetamol', string $strength = '500mg', string $route = 'Oral', int $row = 2): array
{
    return ['source_sheet' => 'Drug Import', 'source_row' => $row, 'raw' => ['Level 1' => 'Test class'],
        'generic_name' => $generic, 'strength_text' => $strength, 'form_text' => 'Tablet', 'route_text' => $route,
        'size_text' => null, 'pndf' => 'Y', 'rxot' => 'RXX', 'record_status' => 'A', 'atc_code' => 'N02BE01',
        'technical_spec' => str_repeat('x', 300)];
}

it('stages, imports, preserves full metadata, and skips subsequent duplicates', function () {
    $batch = pipelineBatch([pipelineCandidate()]);
    expect($batch->ready_count)->toBe(1);

    $result = app(DrugImportCommitter::class)->commit($batch, 'approver');
    expect($result['imported'])->toBe(1)
        ->and(DB::connection('hospital')->table('hdmhdr')->value('atcode'))->toBeNull()
        ->and(mb_strlen(DB::connection('hospital')->table('hdmhdr')->value('techspec')))->toBe(255)
        ->and(mb_strlen(DB::connection('hospital')->table('pharm_drug_metadata')->value('technical_spec')))->toBe(300);

    $duplicateBatch = pipelineBatch([pipelineCandidate(row: 3)]);
    expect($duplicateBatch->duplicate_count)->toBe(1)
        ->and(DB::connection('hospital')->table('hdmhdr')->count())->toBe(1);
});

it('rolls back the whole commit if a mapping disappears after review', function () {
    app(DrugImportCommitter::class)->commit(pipelineBatch([pipelineCandidate()]), 'approver');
    $batch = pipelineBatch([
        pipelineCandidate('Example Drug', '10mg', 'Oral', 10),
        pipelineCandidate('Example Drug', '20mg', 'IV', 11),
    ]);
    expect($batch->ready_count)->toBe(2);
    DB::connection('hospital')->table('hroute')->where('rtecode', 'IV')->delete();

    expect(fn () => app(DrugImportCommitter::class)->commit($batch, 'approver'))->toThrow(RuntimeException::class);
    expect(DB::connection('hospital')->table('hdmhdr')->count())->toBe(1)
        ->and($batch->rows()->where('row_status', 'imported')->count())->toBe(0);
});

it('automatically determines simple dosage, strength, form, and route values', function () {
    $ampul = pipelineCandidate('Example Drug', '200 mg/mL in 10 mL Ampul (IV Infusion)', '', 20);
    $ampul['form_text'] = '';
    $ampul['technical_spec'] = '200 mg/mL in 10 mL Ampul (IV Infusion)';

    $topical = pipelineCandidate('Example Drug', '70%', '', 21);
    $topical['form_text'] = 'Solution';
    $topical['technical_spec'] = '70% Solution for topical use';

    $batch = pipelineBatch([$ampul, $topical]);
    $rows = $batch->rows()->orderBy('source_row')->get();

    expect((float) $rows[0]->dmdnost)->toBe(200.0)
        ->and($rows[0]->strecode)->toBe('MG/ML')
        ->and($rows[0]->formcode)->toBe('AMP')
        ->and($rows[0]->rtecode)->toBe('IV')
        ->and((float) $rows[1]->dmdnost)->toBe(70.0)
        ->and($rows[1]->strecode)->toBe('%')
        ->and($rows[1]->formcode)->toBe('SOL')
        ->and($rows[1]->rtecode)->toBe('TP');
});

it('leaves combination strengths and multiple routes for manual review', function () {
    $candidate = pipelineCandidate('Example Drug', '1 mg + 2 mg', '', 22);
    $candidate['technical_spec'] = '1 mg + 2 mg Ampul (IV, IM)';
    $candidate['form_text'] = '';

    $row = pipelineBatch([$candidate])->rows()->first();

    expect($row->dmdnost)->toBeNull()
        ->and($row->strecode)->toBeNull()
        ->and($row->rtecode)->toBeNull()
        ->and($row->row_status)->toBe('needs_mapping');
});
