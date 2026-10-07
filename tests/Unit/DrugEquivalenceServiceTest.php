<?php

use App\Services\Pharmacy\DrugEquivalenceService;
use App\Livewire\Pharmacy\Dispensing\DispensingEncounter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.connections.hospital', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]);
    DB::purge('hospital');
    $db = DB::connection('hospital');
    $schema = $db->getSchemaBuilder();

    $schema->create('hdruggrp', function (Blueprint $table) {
        $table->string('grpcode')->primary();
        $table->string('gencode');
    });
    $schema->create('hgen', function (Blueprint $table) { $table->string('gencode')->primary(); $table->string('gendesc'); });
    $schema->create('hstre', function (Blueprint $table) { $table->string('strecode')->primary(); $table->string('stredesc'); });
    $schema->create('hform', function (Blueprint $table) { $table->string('formcode')->primary(); $table->string('formdesc'); });
    $schema->create('hroute', function (Blueprint $table) { $table->string('rtecode')->primary(); $table->string('rtedesc'); });
    $schema->create('hdmhdr', function (Blueprint $table) {
        $table->string('dmdcomb'); $table->unsignedTinyInteger('dmdctr');
        $table->string('grpcode'); $table->decimal('dmdnost', 12, 4);
        $table->string('dmdnnostp')->nullable(); $table->string('strecode');
        $table->string('formcode'); $table->string('rtecode');
        $table->string('drug_concat'); $table->string('brandname')->nullable();
        $table->string('dmdstat')->default('A');
        $table->primary(['dmdcomb', 'dmdctr']);
    });
    $schema->create('pharm_drug_equivalence_groups', function (Blueprint $table) {
        $table->id(); $table->string('canonical_dmdcomb'); $table->unsignedTinyInteger('canonical_dmdctr');
        $table->string('status')->default('A'); $table->string('created_by')->nullable(); $table->timestamps();
    });
    $schema->create('pharm_drug_equivalence_members', function (Blueprint $table) {
        $table->id(); $table->unsignedBigInteger('group_id'); $table->string('dmdcomb');
        $table->unsignedTinyInteger('dmdctr'); $table->string('added_by')->nullable(); $table->timestamps();
        $table->unique(['dmdcomb', 'dmdctr']);
    });
    $schema->create('pharm_drug_equivalence_audits', function (Blueprint $table) {
        $table->id(); $table->unsignedBigInteger('group_id'); $table->string('action');
        $table->string('dmdcomb')->nullable(); $table->unsignedTinyInteger('dmdctr')->nullable();
        $table->string('actor')->nullable(); $table->text('details_json')->nullable(); $table->timestamp('created_at');
    });
    $schema->create('pharm_drug_stocks', function (Blueprint $table) {
        $table->id(); $table->string('dmdcomb'); $table->unsignedTinyInteger('dmdctr');
        $table->string('loc_code'); $table->decimal('stock_bal'); $table->dateTime('exp_date');
    });

    $db->table('hdruggrp')->insert([
        ['grpcode' => 'G1', 'gencode' => 'GEN1'], ['grpcode' => 'G2', 'gencode' => 'GEN2'],
    ]);
    $db->table('hgen')->insert([['gencode' => 'GEN1', 'gendesc' => 'Paracetamol'], ['gencode' => 'GEN2', 'gendesc' => 'Different generic']]);
    $db->table('hstre')->insert(['strecode' => 'MG', 'stredesc' => 'mg']);
    $db->table('hform')->insert(['formcode' => 'TAB', 'formdesc' => 'tablet']);
    $db->table('hroute')->insert(['rtecode' => 'PO', 'rtedesc' => 'oral']);
    $db->table('hdmhdr')->insert([
        ['dmdcomb' => '0001', 'dmdctr' => 1, 'grpcode' => 'G1', 'dmdnost' => 500, 'dmdnnostp' => 'N', 'strecode' => 'MG', 'formcode' => 'TAB', 'rtecode' => 'PO', 'drug_concat' => 'Paracetamol 500 mg tablet', 'brandname' => null, 'dmdstat' => 'A'],
        ['dmdcomb' => '9001', 'dmdctr' => 1, 'grpcode' => 'G1', 'dmdnost' => 500, 'dmdnnostp' => 'N', 'strecode' => 'MG', 'formcode' => 'TAB', 'rtecode' => 'PO', 'drug_concat' => 'Paracetamol 500mg tab', 'brandname' => 'Brand A', 'dmdstat' => 'A'],
        ['dmdcomb' => '0001', 'dmdctr' => 2, 'grpcode' => 'G1', 'dmdnost' => 500, 'dmdnnostp' => 'N', 'strecode' => 'MG', 'formcode' => 'TAB', 'rtecode' => 'PO', 'drug_concat' => 'Paracetamol Brand B 500 mg tablet', 'brandname' => 'Brand B', 'dmdstat' => 'A'],
        ['dmdcomb' => '9002', 'dmdctr' => 1, 'grpcode' => 'G1', 'dmdnost' => 250, 'dmdnnostp' => 'N', 'strecode' => 'MG', 'formcode' => 'TAB', 'rtecode' => 'PO', 'drug_concat' => 'Paracetamol 250 mg tablet', 'brandname' => null, 'dmdstat' => 'A'],
        ['dmdcomb' => '9003', 'dmdctr' => 1, 'grpcode' => 'G2', 'dmdnost' => 500, 'dmdnnostp' => 'N', 'strecode' => 'MG', 'formcode' => 'TAB', 'rtecode' => 'PO', 'drug_concat' => 'Different generic 500 mg tablet', 'brandname' => null, 'dmdstat' => 'A'],
    ]);

    $this->db = $db;
    $this->service = new DrugEquivalenceService($db);
});

it('groups different codes and brand counters with the same clinical fingerprint', function () {
    $group = $this->service->create('0001', 1, 'pharmacist');
    $this->service->addMember($group, '9001', 1, 'pharmacist');
    $this->service->addMember($group, '0001', 2, 'pharmacist');

    expect($this->service->members($group))->toHaveCount(3)
        ->and($this->service->groupFor('9001', 1)->id)->toBe($group)
        ->and($this->db->table('pharm_drug_equivalence_audits')->count())->toBe(3);
});

it('rejects strength and generic mismatches', function (string $comb) {
    $group = $this->service->create('0001', 1, 'pharmacist');
    $this->service->addMember($group, $comb, 1, 'pharmacist');
})->with(['different strength' => '9002', 'different generic' => '9003'])->throws(ValidationException::class);

it('normalizes approved description abbreviations for suggestion ordering', function () {
    $group = $this->service->create('0001', 1, 'pharmacist');
    $suggestions = $this->service->suggestions($group);

    expect($this->service->normalizeDescription('PARACETAMOL, 500MG TAB.'))->toBe('paracetamol 500mg tablet')
        ->and($suggestions['compatible']->first()->dmdcomb)->toBe('9001')
        ->and($suggestions['conflicts'])->toHaveCount(2);
});

it('lists clinically exact ungrouped records as potential groups', function () {
    $groups = $this->service->possibleGroups();

    expect($groups)->toHaveCount(1)
        ->and($groups->first()->member_count)->toBe(3)
        ->and($groups->first()->members->pluck('dmdcomb')->all())->toContain('0001', '9001');

    $this->service->create('0001', 1, 'pharmacist');
    expect($this->service->possibleGroups()->first()->member_count)->toBe(2);
});

it('creates all reviewed members together and can dissolve the group', function () {
    $group = $this->service->createReviewed('0001', 1, [
        ['dmdcomb' => '0001', 'dmdctr' => 1],
        ['dmdcomb' => '9001', 'dmdctr' => 1],
        ['dmdcomb' => '0001', 'dmdctr' => 2],
    ], 'pharmacist');

    expect($this->service->members($group))->toHaveCount(3);
    $this->service->dissolve($group, 'pharmacist');
    expect($this->service->groupFor('0001', 1, false))->toBeNull()
        ->and($this->service->possibleGroups()->first()->member_count)->toBe(3);
});

it('returns only approved members with available stock and disables substitutions with the group', function () {
    $group = $this->service->create('0001', 1, 'pharmacist');
    $this->service->addMember($group, '9001', 1, 'pharmacist');
    $this->db->table('pharm_drug_stocks')->insert([
        ['dmdcomb' => '0001', 'dmdctr' => 1, 'loc_code' => 'MAIN', 'stock_bal' => 4, 'exp_date' => now()->addMonth()],
        ['dmdcomb' => '9001', 'dmdctr' => 1, 'loc_code' => 'MAIN', 'stock_bal' => 8, 'exp_date' => now()->addMonth()],
        ['dmdcomb' => '9002', 'dmdctr' => 1, 'loc_code' => 'MAIN', 'stock_bal' => 20, 'exp_date' => now()->addMonth()],
    ]);

    expect($this->service->substitutionCandidates('0001', 1, 'MAIN'))->toHaveCount(2);
    $this->service->toggle($group, 'pharmacist');
    expect($this->service->substitutionCandidates('0001', 1, 'MAIN'))->toBeEmpty();
});

it('requires a new canonical member before removing the current canonical drug', function () {
    $group = $this->service->create('0001', 1, 'pharmacist');
    $this->service->removeMember($group, '0001', 1, 'pharmacist');
})->throws(ValidationException::class);

it('activates prescription equivalence mode and manual typing clears its prescription context', function () {
    $group = $this->service->create('0001', 1, 'pharmacist');
    $this->service->addMember($group, '9001', 1, 'pharmacist');

    $component = new DispensingEncounter;
    $component->searchGenericItem(42, 'Paracetamol 500 mg tablet', '0001', 1, 'EMP1');

    expect($component->stockSearchMode)->toBe('equivalence')
        ->and($component->stockSearchGroupMemberCount)->toBe(2)
        ->and($component->rx_id)->toBe(42);

    $component->generic = 'omeprazole';
    $component->updatedGeneric();

    expect($component->stockSearchMode)->toBe('text')
        ->and($component->generic)->toBe('omeprazole')
        ->and($component->rx_id)->toBeNull()
        ->and($component->rx_dmdcomb)->toBeNull();
});

it('shows exact-only prescription mode when no approved equivalence group exists', function () {
    $component = new DispensingEncounter;
    $component->searchGenericItem(43, 'Paracetamol 250 mg tablet', '9002', 1, 'EMP1');

    expect($component->stockSearchMode)->toBe('equivalence')
        ->and($component->stockSearchGroupMemberCount)->toBe(1);

    $component->clearStockPrescriptionFilter();
    expect($component->stockSearchMode)->toBe('text')
        ->and($component->generic)->toBe('');
});

it('builds order-independent stock search conditions and expands compact dosage tokens', function () {
    $component = new DispensingEncounter;
    $method = new ReflectionMethod($component, 'buildStockTextFilter');
    $filter = $method->invoke($component, 'paracetamol 100mg');

    expect($filter)->toContain("LIKE '%paracetamol%'")
        ->and($filter)->toContain("LIKE '%100mg%'")
        ->and($filter)->toContain("LIKE '%100.00mg%'");
});
