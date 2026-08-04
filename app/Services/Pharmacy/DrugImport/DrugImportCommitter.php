<?php

namespace App\Services\Pharmacy\DrugImport;

use App\Models\Pharmacy\DrugImportBatch;
use App\Models\Pharmacy\DrugImportRow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DrugImportCommitter
{
    public function __construct(private readonly DrugMasterWriter $writer, private readonly DrugImportMapper $mapper) {}

    public function commit(DrugImportBatch $batch, ?string $approvedBy): array
    {
        $db = DB::connection('hospital');
        $result = $db->transaction(function () use ($db, $batch) {
            $rows = DrugImportRow::query()->where('batch_id', $batch->id)->where('row_status', 'ready')->where('action', 'import')->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                throw new RuntimeException('There are no ready rows to import.');
            }

            $nextCode = (int) $this->writer->nextDrugCodeLocked($db);
            $imported = 0;
            $duplicates = 0;
            foreach ($rows as $row) {
                $drug = $this->validatedDrug($db, $row);
                if ($duplicate = $this->writer->duplicate($db, $drug)) {
                    $row->update(['row_status' => 'duplicate', 'existing_dmdcomb' => $duplicate->dmdcomb, 'existing_dmdctr' => $duplicate->dmdctr]);
                    $duplicates++;

                    continue;
                }

                $dmdcomb = str_pad((string) $nextCode++, 12, '0', STR_PAD_LEFT);
                $now = Carbon::now();
                $db->table('hdmhdr')->insert([
                    'dmdcomb' => $dmdcomb, 'dmdctr' => 1, 'grpcode' => $drug['grpcode'], 'dmdnost' => $drug['dmdnost'],
                    'strecode' => $drug['strecode'], 'formcode' => $drug['formcode'], 'rtecode' => $drug['rtecode'],
                    'brandname' => null, 'dmdpndf' => $drug['dmdpndf'], 'dmdrxot' => $drug['dmdrxot'], 'dmdstat' => $drug['dmdstat'],
                    'dmdlock' => 'N', 'dmdupsw' => 'T', 'dmddtmd' => $now, 'dmdnnostp' => 'N', 'atcode' => null,
                    'techspec' => $row->technical_spec ? mb_substr($row->technical_spec, 0, 255) : null,
                    'begbal' => 0, 'stockbal' => 0, 'baldteasof' => $now, 'lot_no' => '',
                    'packvolno' => $row->packvolno, 'packvolunitcode' => $row->packvolunitcode,
                    'drug_concat' => $this->writer->description($db, $drug),
                ]);
                $raw = $row->raw_json ?? [];
                $db->table('pharm_drug_metadata')->insert([
                    'dmdcomb' => $dmdcomb, 'dmdctr' => 1, 'atc_code' => $row->atc_code,
                    'technical_spec' => $row->technical_spec, 'raw_size' => $row->size_text,
                    'classification_level_1' => $raw['Level 1'] ?? null, 'classification_level_2' => $raw['Level 2'] ?? null,
                    'classification_level_3' => $raw['Level 3'] ?? null, 'classification_level_4' => $raw['Level 4'] ?? null,
                    'source_batch_id' => $batch->id, 'source_sheet' => $row->source_sheet, 'source_row' => $row->source_row,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $row->update(['row_status' => 'imported', 'result_dmdcomb' => $dmdcomb, 'result_dmdctr' => 1]);
                $imported++;
            }

            return compact('imported', 'duplicates');
        }, 3);

        $batch->update(['status' => 'completed', 'approved_by' => $approvedBy, 'committed_at' => now()]);
        $this->mapper->refreshBatch($batch);

        return $result;
    }

    private function validatedDrug($db, DrugImportRow $row): array
    {
        $required = ['grpcode' => ['hdruggrp', 'grpcode'], 'strecode' => ['hstre', 'strecode'], 'formcode' => ['hform', 'formcode'], 'rtecode' => ['hroute', 'rtecode']];
        foreach ($required as $field => [$table, $column]) {
            if (! $row->{$field} || ! $db->table($table)->where($column, $row->{$field})->exists()) {
                throw new RuntimeException("Row {$row->source_row} has an invalid {$field} mapping.");
            }
        }
        if (! is_numeric($row->dmdnost) || ! in_array($row->pndf, ['Y', 'N'], true) || ! in_array($row->rxot, ['RXX', 'OTC'], true)) {
            throw new RuntimeException("Row {$row->source_row} is no longer valid.");
        }

        return ['grpcode' => $row->grpcode, 'dmdnost' => (float) $row->dmdnost, 'strecode' => $row->strecode,
            'formcode' => $row->formcode, 'rtecode' => $row->rtecode, 'dmdpndf' => $row->pndf,
            'dmdrxot' => $row->rxot, 'dmdstat' => $row->record_status, 'brandname' => null];
    }
}
