<?php

namespace App\Services\Pharmacy\DrugImport;

use App\Models\Pharmacy\DrugImportBatch;
use App\Models\Pharmacy\DrugImportRow;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class DrugImportMapper
{
    private ConnectionInterface $db;

    private array $lookups = [];

    private array $aliases = [];

    public function __construct(private readonly DrugMasterWriter $writer)
    {
        $this->db = DB::connection('hospital');
    }

    public function stage(DrugImportBatch $batch, array $candidates): void
    {
        $this->warmCaches();
        foreach (array_chunk($candidates, 100) as $chunk) {
            foreach ($chunk as $candidate) {
                $mapped = $this->map($candidate);
                DrugImportRow::query()->create(['batch_id' => $batch->id, ...$mapped]);
            }
        }
        $this->refreshBatch($batch);
    }

    public function revalidate(DrugImportRow $row, bool $refreshBatch = true, bool $refreshCaches = true): DrugImportRow
    {
        $this->warmCaches($refreshCaches);
        $candidate = [
            'source_sheet' => $row->source_sheet,
            'source_row' => $row->source_row,
            'raw' => $row->raw_json,
            'generic_name' => $row->generic_name,
            'strength_text' => $row->strength_text,
            'form_text' => $row->form_text,
            'route_text' => $row->route_text,
            'size_text' => $row->size_text,
            'pndf' => $row->pndf,
            'rxot' => $row->rxot,
            'record_status' => $row->record_status,
            'atc_code' => $row->atc_code,
            'technical_spec' => $row->technical_spec,
            'grpcode' => $row->grpcode,
            'dmdnost' => $row->dmdnost,
            'strecode' => $row->strecode,
            'formcode' => $row->formcode,
            'rtecode' => $row->rtecode,
        ];
        $mapped = $this->map($candidate, true);
        $row->fill($mapped)->save();
        if ($refreshBatch) {
            $this->refreshBatch($row->batch);
        }

        return $row->refresh();
    }

    public function revalidateBatch(DrugImportBatch $batch): void
    {
        $this->warmCaches(true);
        $batch->rows()->whereNotIn('row_status', ['imported'])->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $this->revalidate($row, false, false);
            }
        });
        $this->refreshBatch($batch);
    }

    public function refreshBatch(DrugImportBatch $batch): void
    {
        $counts = $batch->rows()->selectRaw('row_status, COUNT(*) as aggregate')->groupBy('row_status')->pluck('aggregate', 'row_status');
        $batch->update([
            'total_count' => $counts->sum(),
            'ready_count' => (int) ($counts['ready'] ?? 0),
            'issue_count' => (int) ($counts['needs_mapping'] ?? 0),
            'duplicate_count' => (int) ($counts['duplicate'] ?? 0),
            'excluded_count' => (int) ($counts['excluded'] ?? 0),
            'imported_count' => (int) ($counts['imported'] ?? 0),
        ]);
    }

    private function map(array $candidate, bool $respectOverrides = false): array
    {
        $issues = [];
        $generic = $this->clean($candidate['generic_name'] ?? null);
        $gencode = $this->resolve('generic', $generic, 'hgen');
        $providedGroup = $this->validCode('hdruggrp', 'grpcode', $candidate['grpcode'] ?? null)
            ? trim((string) $candidate['grpcode']) : null;
        if (! $gencode && $providedGroup) {
            $gencode = $this->lookups['hdruggrp']['generic_by_group'][$this->normalize($providedGroup)] ?? null;
        }
        if (! $gencode) {
            $issues[] = ['code' => 'generic', 'message' => 'Generic name is not mapped.'];
        }

        $grpcode = $providedGroup ?: $this->resolveGroup($generic, $gencode);
        if (! $grpcode) {
            $issues[] = ['code' => 'group', 'message' => 'Select one PNDF group for this generic.'];
        }

        [$dmdnost, $strecode] = $this->resolveStrength($candidate, $respectOverrides);
        if ($dmdnost === null || ! $strecode) {
            $issues[] = ['code' => 'strength', 'message' => 'Strength must be mapped to a numeric value and PDIMS strength code.'];
        }

        $formcode = $respectOverrides && $this->validCode('hform', 'formcode', $candidate['formcode'] ?? null)
            ? trim($candidate['formcode']) : $this->resolveForm($candidate);
        if (! $formcode) {
            $issues[] = ['code' => 'form', 'message' => 'Dosage form is not mapped.'];
        }

        $rtecode = $respectOverrides && $this->validCode('hroute', 'rtecode', $candidate['rtecode'] ?? null)
            ? trim($candidate['rtecode']) : $this->resolveRoute($candidate['route_text'] ?? null, $candidate['technical_spec'] ?? null);
        if (! $rtecode) {
            $issues[] = ['code' => 'route', 'message' => 'Route is missing, ambiguous, or not mapped.'];
        }

        $pndf = in_array($candidate['pndf'] ?? null, ['Y', 'N'], true) ? $candidate['pndf'] : 'Y';
        $rxot = in_array(strtoupper((string) ($candidate['rxot'] ?? '')), ['RXX', 'OTC'], true) ? strtoupper($candidate['rxot']) : null;
        if (! $rxot) {
            $issues[] = ['code' => 'rxot', 'message' => 'RX/OTC classification is invalid.'];
        }
        $recordStatus = in_array(strtoupper((string) ($candidate['record_status'] ?? '')), ['A', 'I'], true) ? strtoupper($candidate['record_status']) : null;
        if (! $recordStatus) {
            $issues[] = ['code' => 'status', 'message' => 'Status is invalid.'];
        }

        [$packvolno, $packvolunitcode] = $this->resolveSize($candidate['size_text'] ?? null);
        $duplicate = null;
        if (! $issues) {
            $duplicate = $this->writer->duplicate($this->db, compact('grpcode', 'dmdnost', 'strecode', 'formcode', 'rtecode'));
        }

        $action = $candidate['action'] ?? 'import';
        $rowStatus = $action === 'exclude' ? 'excluded' : ($duplicate ? 'duplicate' : ($issues ? 'needs_mapping' : 'ready'));

        return [
            'source_sheet' => $candidate['source_sheet'], 'source_row' => $candidate['source_row'], 'raw_json' => $candidate['raw'],
            'generic_name' => $generic, 'strength_text' => $this->clean($candidate['strength_text'] ?? null),
            'form_text' => $this->clean($candidate['form_text'] ?? null), 'route_text' => $this->clean($candidate['route_text'] ?? null),
            'size_text' => $this->clean($candidate['size_text'] ?? null), 'pndf' => $pndf, 'rxot' => $rxot ?: 'RXX',
            'record_status' => $recordStatus ?: 'A', 'atc_code' => mb_substr($this->clean($candidate['atc_code'] ?? null) ?? '', 0, 35) ?: null,
            'technical_spec' => $this->clean($candidate['technical_spec'] ?? null), 'gencode' => $gencode, 'grpcode' => $grpcode,
            'dmdnost' => $dmdnost, 'strecode' => $strecode, 'formcode' => $formcode, 'rtecode' => $rtecode,
            'packvolno' => $packvolno, 'packvolunitcode' => $packvolunitcode, 'row_status' => $rowStatus,
            'issue_code' => $issues[0]['code'] ?? null, 'issues_json' => $issues ?: null, 'action' => $action,
            'existing_dmdcomb' => $duplicate?->dmdcomb, 'existing_dmdctr' => $duplicate?->dmdctr,
        ];
    }

    private function warmCaches(bool $force = false): void
    {
        if ($this->lookups && ! $force) {
            return;
        }
        $this->lookups = [];
        foreach ([
            'hgen' => ['gencode', 'gendesc'], 'hform' => ['formcode', 'formdesc'], 'hroute' => ['rtecode', 'rtedesc'],
            'hstre' => ['strecode', 'stredesc'], 'huom' => ['uomcode', 'uomdesc'],
        ] as $table => [$code, $description]) {
            foreach ($this->db->table($table)->get([$code, $description]) as $row) {
                $this->lookups[$table]['code'][$this->normalize($row->{$code})] = trim((string) $row->{$code});
                $this->lookups[$table]['description'][$this->normalize($row->{$description})][] = trim((string) $row->{$code});
                $this->lookups[$table]['compact'][$this->compact($row->{$description})][] = trim((string) $row->{$code});
            }
        }
        foreach (['hstre' => 'strecode', 'hform' => 'formcode', 'hroute' => 'rtecode'] as $table => $column) {
            $this->lookups[$table]['usage'] = $this->db->table('hdmhdr')
                ->whereNotNull($column)
                ->select($column, $this->db->raw('COUNT(*) as aggregate'))
                ->groupBy($column)
                ->pluck('aggregate', $column)
                ->map(fn ($count) => (int) $count)
                ->all();
        }
        foreach ($this->db->table('hdruggrp')->where('grpstat', 'A')->get(['grpcode', 'gencode']) as $row) {
            $grpcode = trim((string) $row->grpcode);
            $this->lookups['hdruggrp']['code'][$this->normalize($grpcode)] = $grpcode;
            $this->lookups['hdruggrp']['by_generic'][$this->normalize($row->gencode)][] = $grpcode;
            $this->lookups['hdruggrp']['generic_by_group'][$this->normalize($grpcode)] = trim((string) $row->gencode);
        }
        $this->aliases = $this->db->table('pharm_drug_import_mappings')->get()->groupBy('mapping_type')
            ->map(fn ($rows) => $rows->pluck('target_code', 'source_normalized')->all())->all();
    }

    private function resolve(string $type, $value, string $table): ?string
    {
        $normalized = $this->normalize($value);
        if ($normalized === '') {
            return null;
        }
        if (isset($this->lookups[$table]['code'][$normalized])) {
            return $this->lookups[$table]['code'][$normalized];
        }
        $matches = $this->lookups[$table]['description'][$normalized] ?? [];
        if ($match = $this->preferredCode($table, $matches)) {
            return $match;
        }
        $alias = $this->aliases[$type][$normalized] ?? null;

        return $alias && isset($this->lookups[$table]['code'][$this->normalize($alias)]) ? $alias : null;
    }

    private function resolveGroup(?string $generic, ?string $gencode): ?string
    {
        $alias = $this->aliases['group'][$this->normalize($generic)] ?? null;
        if ($alias && $this->validCode('hdruggrp', 'grpcode', $alias)) {
            return $alias;
        }
        if (! $gencode) {
            return null;
        }
        $groups = array_values(array_unique($this->lookups['hdruggrp']['by_generic'][$this->normalize($gencode)] ?? []));

        return count($groups) === 1 ? $groups[0] : null;
    }

    private function resolveStrength(array $candidate, bool $respectOverrides): array
    {
        if ($respectOverrides && is_numeric($candidate['dmdnost'] ?? null) && $this->validCode('hstre', 'strecode', $candidate['strecode'] ?? null)) {
            return [(float) $candidate['dmdnost'], trim($candidate['strecode'])];
        }
        foreach ([$candidate['strength_text'] ?? null, $candidate['technical_spec'] ?? null] as $text) {
            [$number, $unit] = $this->parseStrength($text);
            if ($number === null || ! $unit) {
                continue;
            }
            $strecode = $this->resolve('strength', $unit, 'hstre');
            if ($strecode) {
                return [$number, $strecode];
            }
        }

        return [null, null];
    }

    private function parseStrength($value): array
    {
        $text = $this->clean($value);
        if (! $text || preg_match('/\b(variable|various)\b/iu', $text) || str_contains($text, '+') || preg_match('/\d\s*[\x{2013}\x{2014}-]\s*\d/u', $text)) {
            return [null, null];
        }
        if (! preg_match('/(?<![\d.])([0-9]+(?:[.,][0-9]+)?)\s*(mcg|ug|\x{00B5}g|mg|g|iu|units?|meq|mmol|%)(?:\s*\/\s*(?:([0-9]+(?:[.,][0-9]+)?)\s*)?(ml|l|dose|tablet|capsule))?/iu', $text, $match)) {
            return [null, null];
        }
        $number = (float) str_replace(',', '.', $match[1]);
        $numerator = strtolower($match[2]);
        $numerator = in_array($numerator, ['ug', 'µg'], true) ? 'mcg' : (in_array($numerator, ['unit', 'units'], true) ? 'IU' : $match[2]);
        $unit = $numerator;
        if (! empty($match[4])) {
            $denominator = ! empty($match[3]) ? str_replace(',', '.', $match[3]) : '';
            $unit .= '/'.$denominator.$match[4];
        }

        return [$number, $unit];
    }

    private function resolveForm(array $candidate): ?string
    {
        $form = $this->clean($candidate['form_text'] ?? null);
        if ($resolved = $this->resolve('form', $form, 'hform')) {
            return $resolved;
        }
        $search = $this->normalize(implode(' ', array_filter([$form, $candidate['technical_spec'] ?? null])));
        $patterns = [
            'powder for oral suspension' => ['POW20', 'GS', 'SUSP'],
            'granules for suspension' => ['GS', 'SUSP'],
            'effervescent tablet' => ['TAB'], 'chewable tablet' => ['TAB'],
            'tablet' => ['TAB'], 'capsule' => ['CAP'], 'ampul' => ['AMP'], 'ampoule' => ['AMP'], 'ampule' => ['AMP'],
            'vial' => ['VIAL'], 'suspension' => ['SUSP'], 'solution' => ['SOL'],
        ];
        foreach ($patterns as $needle => $codes) {
            if (str_contains($search, $needle)) {
                foreach ($codes as $code) {
                    if ($this->validCode('hform', 'formcode', $code)) {
                        return $this->lookups['hform']['code'][$this->normalize($code)];
                    }
                }
            }
        }

        return null;
    }

    private function resolveRoute($value, $technicalSpec = null): ?string
    {
        $normalized = rtrim($this->normalize($value), '.:');
        if ($normalized !== '' && ! in_array($normalized, ['inj', 'injection'], true)) {
            if ($resolved = $this->resolve('route', $normalized, 'hroute')) {
                return $resolved;
            }
        }
        $search = $this->normalize(implode(' ', array_filter([$value, $technicalSpec])));
        $routes = [];
        foreach ([
            'IV' => '/\b(iv|intravenous)\b/i', 'IM' => '/\b(im|intramuscular)\b/i',
            'SC' => '/\b(sc|subcutaneous)\b/i', 'PO' => '/\b(po|oral)\b/i',
            'IH' => '/\b(inhalation|inhaled)\b/i', 'TP' => '/\b(topical)\b/i',
            'OP' => '/\b(ophthalmic)\b/i', 'PR' => '/\b(rectal)\b/i',
        ] as $code => $pattern) {
            if (preg_match($pattern, $search) && $this->validCode('hroute', 'rtecode', $code)) {
                $routes[] = $this->lookups['hroute']['code'][$this->normalize($code)];
            }
        }

        return count(array_unique($routes)) === 1 ? $routes[0] : null;
    }

    private function preferredCode(string $table, array $matches): ?string
    {
        $matches = array_values(array_unique(array_filter($matches)));
        if (! $matches) {
            return null;
        }
        usort($matches, fn ($left, $right) => ($this->lookups[$table]['usage'][$right] ?? 0) <=> ($this->lookups[$table]['usage'][$left] ?? 0));

        return $matches[0];
    }

    private function resolveSize($value): array
    {
        if (! preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(.+)$/u', $this->clean($value) ?? '', $match)) {
            return [null, null];
        }
        $matches = $this->lookups['huom']['compact'][$this->compact($match[2])] ?? [];

        return count(array_unique($matches)) === 1 ? [(float) $match[1], $matches[0]] : [null, null];
    }

    private function validCode(string $table, string $column, $code): bool
    {
        if ($this->clean($code) === null) {
            return false;
        }

        return match ($table) {
            'hdruggrp' => isset($this->lookups['hdruggrp']['code'][$this->normalize($code)]),
            'hform', 'hroute', 'hstre', 'huom', 'hgen' => isset($this->lookups[$table]['code'][$this->normalize($code)]),
            default => $this->db->table($table)->where($column, trim((string) $code))->exists(),
        };
    }

    private function normalize($value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));
    }

    private function compact($value): string
    {
        return preg_replace('/\s+/u', '', $this->normalize($value));
    }

    private function clean($value): ?string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }
}
