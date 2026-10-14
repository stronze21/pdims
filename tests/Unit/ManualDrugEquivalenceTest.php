<?php

namespace Tests\Unit;

use App\Services\Pharmacy\DrugEquivalenceService;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManualDrugEquivalenceTest extends TestCase
{
    private SQLiteConnection $db;
    private DrugEquivalenceService $service;
    private array $keys = ['A|1', 'B|1'];

    protected function setUp(): void
    {
        parent::setUp();
        // Dedicated in-memory connection. No application databases or migrations.
        $this->db = new SQLiteConnection(new \PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
        foreach ([
            'CREATE TABLE hdmhdr (dmdcomb TEXT, dmdctr INTEGER, grpcode TEXT, dmdnost TEXT, dmdnnostp TEXT, strecode TEXT, formcode TEXT, rtecode TEXT, brandname TEXT, dmdstat TEXT)',
            'CREATE TABLE hdruggrp (grpcode TEXT, gencode TEXT)',
            'CREATE TABLE hgen (gencode TEXT, gendesc TEXT)',
            'CREATE TABLE hstre (strecode TEXT, stredesc TEXT)',
            'CREATE TABLE hform (formcode TEXT, formdesc TEXT)',
            'CREATE TABLE hroute (rtecode TEXT, rtedesc TEXT)',
            'CREATE TABLE pharm_drug_equivalence_groups (id INTEGER PRIMARY KEY AUTOINCREMENT, canonical_dmdcomb TEXT, canonical_dmdctr INTEGER, status TEXT, created_by TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE pharm_drug_equivalence_members (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER, dmdcomb TEXT, dmdctr INTEGER, added_by TEXT, created_at TEXT, updated_at TEXT, UNIQUE(dmdcomb, dmdctr))',
            'CREATE TABLE pharm_drug_equivalence_audits (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER, action TEXT, dmdcomb TEXT, dmdctr INTEGER, actor TEXT, details_json TEXT, created_at TEXT)',
        ] as $sql) {
            $this->db->statement($sql);
        }
        $this->db->table('hdruggrp')->insert(['grpcode' => 'G', 'gencode' => 'GEN']);
        $this->db->table('hgen')->insert(['gencode' => 'GEN', 'gendesc' => 'Example medicine']);
        $this->db->table('hstre')->insert(['strecode' => 'MG', 'stredesc' => 'mg']);
        $this->db->table('hform')->insert(['formcode' => 'TAB', 'formdesc' => 'Tablet']);
        $this->db->table('hroute')->insert(['rtecode' => 'PO', 'rtedesc' => 'Oral']);
        foreach (['A', 'B', 'C'] as $code) {
            $this->db->table('hdmhdr')->insert([
                'dmdcomb' => $code, 'dmdctr' => 1, 'grpcode' => 'G', 'dmdnost' => '500', 'dmdnnostp' => 'N',
                'strecode' => 'MG', 'formcode' => 'TAB', 'rtecode' => 'PO', 'brandname' => 'Brand '.$code, 'dmdstat' => 'A',
            ]);
        }
        $this->service = new DrugEquivalenceService($this->db);
    }

    public function test_matching_manual_group_can_be_saved_without_override(): void
    {
        $review = $this->service->reviewManual('A|1', $this->keys);
        $this->assertFalse($review['has_warnings']);
        $id = $this->service->createManual('A|1', $this->keys, 'tester', $review['token'], false, '');
        $this->assertSame(2, $this->service->members($id)->count());
        $this->assertSame(1, $this->db->table('pharm_drug_equivalence_audits')->where('action', 'manual_group_approved')->count());
    }

    public function test_mismatch_requires_confirmation_and_reason_without_partial_saves(): void
    {
        $this->db->table('hdmhdr')->where('dmdcomb', 'B')->update(['dmdnost' => '250']);
        $review = $this->service->reviewManual('A|1', $this->keys);
        $this->assertTrue($review['has_warnings']);
        $this->assertSame('Dose', $review['members'][1]['differences'][0]['field']);
        foreach ([[false, 'A sufficient reason'], [true, 'short']] as [$confirmed, $reason]) {
            try {
                $this->service->createManual('A|1', $this->keys, 'tester', $review['token'], $confirmed, $reason);
                $this->fail('Unconfirmed or unexplained mismatch should fail.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('manualEquivalence', $error->errors());
            }
            $this->assertSame(0, $this->db->table('pharm_drug_equivalence_groups')->count());
            $this->assertSame(0, $this->db->table('pharm_drug_equivalence_members')->count());
        }
    }

    public function test_confirmed_mismatch_is_saved_audited_and_visible_on_reopen(): void
    {
        $this->db->table('hdmhdr')->where('dmdcomb', 'B')->update(['dmdnost' => '250']);
        $review = $this->service->reviewManual('A|1', $this->keys);
        $id = $this->service->createManual('A|1', $this->keys, 'tester', $review['token'], true, 'Approved record mapping exception.');
        $audit = $this->db->table('pharm_drug_equivalence_audits')->where('action', 'member_override_confirmed')->first();
        $details = json_decode($audit->details_json, true);
        $this->assertSame('tester', $audit->actor);
        $this->assertTrue($details['confirmed']);
        $this->assertSame('Approved record mapping exception.', $details['reason']);
        $this->assertSame('250', $details['differences'][0]['candidate']);
        $this->assertCount(1, $this->service->groupClinicalWarnings($id));
    }

    public function test_changed_drug_or_selection_requires_fresh_review(): void
    {
        $review = $this->service->reviewManual('A|1', $this->keys);
        $this->db->table('hdmhdr')->where('dmdcomb', 'B')->update(['dmdnost' => '250']);
        foreach ([$this->keys, ['A|1', 'C|1']] as $keys) {
            try {
                $this->service->createManual('A|1', $keys, 'tester', $review['token'], true, 'Trying stale confirmation.');
                $this->fail('Stale review should fail.');
            } catch (ValidationException $error) {
                $this->assertStringContainsString('changed', $error->getMessage());
            }
        }
        $this->assertSame(0, $this->db->table('pharm_drug_equivalence_groups')->count());
    }

    public function test_inactive_missing_and_already_grouped_records_cannot_be_overridden(): void
    {
        $this->db->table('hdmhdr')->where('dmdcomb', 'B')->update(['dmdstat' => 'I']);
        foreach ([$this->keys, ['A|1', 'MISSING|1']] as $keys) {
            try {
                $this->service->reviewManual('A|1', $keys);
                $this->fail('Unavailable member should fail.');
            } catch (ValidationException $error) {
                $this->assertStringContainsString('missing, inactive, or already grouped', $error->getMessage());
            }
        }
        $this->db->table('hdmhdr')->where('dmdcomb', 'B')->update(['dmdstat' => 'A']);
        $this->service->create('B', 1, 'other-user');
        $this->expectException(ValidationException::class);
        $this->service->reviewManual('A|1', $this->keys);
    }

    public function test_strict_existing_addition_still_rejects_different_dose(): void
    {
        $this->db->table('hdmhdr')->where('dmdcomb', 'B')->update(['dmdnost' => '250']);
        $id = $this->service->create('A', 1, 'tester');
        $this->expectException(ValidationException::class);
        $this->service->addMember($id, 'B', 1, 'tester');
    }

    public function test_invalid_group_selection_is_rejected(): void
    {
        foreach ([['A|1'], ['A|1', 'A|1'], ['B|1', 'C|1']] as $keys) {
            try {
                $this->service->reviewManual('A|1', $keys);
                $this->fail('Invalid selection should fail.');
            } catch (ValidationException $error) {
                $this->assertStringContainsString('reference drug', $error->getMessage());
            }
        }
    }
}
