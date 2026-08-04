<?php

namespace App\Services\Pharmacy\DrugImport;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class DrugImportWorkbookReader
{
    public const PDIMS_LOCAL = 'pdims_local_database_v1';

    public const PDIMS_TEMPLATE = 'pdims_drug_import_v1';

    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $workbook = $reader->load($path);

        try {
            $adapter = $this->detectAdapter($workbook);

            return $adapter === self::PDIMS_LOCAL
                ? $this->readLocalDatabase($workbook)
                : $this->readTemplate($workbook);
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private function detectAdapter(Spreadsheet $workbook): string
    {
        if ($workbook->getSheetByName('PNF') && $workbook->getSheetByName('FINAL TABLE')) {
            $headers = $this->headers($workbook->getSheetByName('PNF'));
            if (isset($headers['molecule'], $headers['route'], $headers['technical specifications'])) {
                return self::PDIMS_LOCAL;
            }
        }

        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $headers = $this->headers($sheet);
            if (isset($headers['generic name'], $headers['dosage number'], $headers['strength'], $headers['dosage form'], $headers['route'], $headers['pndf'], $headers['rx/otc'], $headers['status'])) {
                return self::PDIMS_TEMPLATE;
            }
        }

        throw new RuntimeException('Unsupported workbook. Use the PDIMS drug import template or the PDIMS Local Database workbook.');
    }

    private function readLocalDatabase(Spreadsheet $workbook): array
    {
        $hints = $this->structuredHints($workbook->getSheetByName('Copy of PNF'));
        $rows = [];
        $warnings = [];

        foreach ($this->rows($workbook->getSheetByName('PNF')) as $source) {
            $this->rejectFormulaFields($source, ['Molecule', 'Route', 'Technical Specifications', 'ATC Code']);
            $rawRoute = $this->clean($source['Route'] ?? null);
            $technicalSpec = $this->clean($source['Technical Specifications'] ?? null);
            $hintKey = $this->hintKey($source['Molecule'] ?? null, $source['ATC Code'] ?? null, $rawRoute);
            $hint = isset($hints[$hintKey]) && count($hints[$hintKey]) === 1 ? $hints[$hintKey][0] : [];
            $parsed = $this->parseSpecification($technicalSpec);
            $routeCandidates = $this->routeCandidates($rawRoute, $technicalSpec);

            foreach ($routeCandidates ?: [$rawRoute] as $route) {
                $rows[] = $this->candidate($source, [
                    'source_sheet' => 'PNF',
                    'generic_name' => $source['Molecule'] ?? null,
                    'strength_text' => $hint['Dosage Strength'] ?? $parsed['strength'],
                    'form_text' => $hint['Dosage Form'] ?? $parsed['form'],
                    'route_text' => $route,
                    'size_text' => $hint['Size'] ?? $parsed['size'],
                    'pndf' => 'Y',
                    'rxot' => 'RXX',
                    'record_status' => 'A',
                    'atc_code' => $source['ATC Code'] ?? null,
                    'technical_spec' => $technicalSpec,
                ]);
            }
        }

        $blankPndf = 0;
        $ignoredPnf = 0;
        $nonPnf = 0;
        foreach ($this->rows($workbook->getSheetByName('FINAL TABLE')) as $source) {
            $classification = mb_strtolower($this->clean($source['PNF/non-PNF'] ?? null) ?? '');
            if ($classification === 'pnf') {
                $ignoredPnf++;

                continue;
            }
            $this->rejectFormulaFields($source, ['Generic Name', 'Dosage Strength', 'Dosage Form', 'Route', 'PNF/non-PNF']);
            $isNonPnf = $classification === 'non-pnf';
            $isNonPnf ? $nonPnf++ : $blankPndf++;
            foreach ($this->routeCandidates($source['Route'] ?? null, $source['Item'] ?? null) ?: [$source['Route'] ?? null] as $route) {
                $rows[] = $this->candidate($source, [
                    'source_sheet' => 'FINAL TABLE',
                    'generic_name' => $source['Generic Name'] ?? null,
                    'strength_text' => $source['Dosage Strength'] ?? null,
                    'form_text' => $source['Dosage Form'] ?? null,
                    'route_text' => $route,
                    'size_text' => $source['Size'] ?? null,
                    'pndf' => $isNonPnf ? 'N' : 'Y',
                    'rxot' => 'RXX',
                    'record_status' => 'A',
                    'technical_spec' => $source['Item'] ?? null,
                ]);
            }
        }

        $warnings[] = "{$blankPndf} FINAL TABLE rows with blank PNDF/non-PNDF were treated as PNDF.";
        $warnings[] = "{$ignoredPnf} FINAL TABLE PNF rows were ignored because PNF is the canonical source.";
        $warnings[] = "{$nonPnf} explicitly classified non-PNF rows were added.";

        return ['adapter' => self::PDIMS_LOCAL, 'rows' => $rows, 'warnings' => $warnings];
    }

    private function readTemplate(Spreadsheet $workbook): array
    {
        foreach ($workbook->getWorksheetIterator() as $sheet) {
            $headers = $this->headers($sheet);
            if (! isset($headers['generic name'], $headers['dosage number'], $headers['strength'])) {
                continue;
            }

            $rows = [];
            foreach ($this->rows($sheet) as $source) {
                $this->rejectFormulaFields($source, ['Generic Name', 'Group Code', 'Dosage Number', 'Strength', 'Dosage Form', 'Route', 'PNDF', 'RX/OTC', 'Status']);
                foreach ($this->routeCandidates($source['Route'] ?? null, null) ?: [$source['Route'] ?? null] as $route) {
                    $rows[] = $this->candidate($source, [
                        'source_sheet' => $sheet->getTitle(),
                        'generic_name' => $source['Generic Name'] ?? null,
                        'strength_text' => trim(implode(' ', array_filter([$source['Dosage Number'] ?? null, $source['Strength'] ?? null]))),
                        'form_text' => $source['Dosage Form'] ?? null,
                        'route_text' => $route,
                        'size_text' => trim(implode(' ', array_filter([$source['Size Number'] ?? null, $source['Size Unit'] ?? null]))),
                        'pndf' => $this->yn($source['PNDF'] ?? null) ?? 'Y',
                        'rxot' => strtoupper($this->clean($source['RX/OTC'] ?? null) ?? 'RXX'),
                        'record_status' => strtoupper($this->clean($source['Status'] ?? null) ?? 'A'),
                        'atc_code' => $source['ATC Code'] ?? null,
                        'technical_spec' => $source['Technical Specification'] ?? null,
                        'grpcode' => $source['Group Code'] ?? null,
                    ]);
                }
            }

            return ['adapter' => self::PDIMS_TEMPLATE, 'rows' => $rows, 'warnings' => []];
        }

        throw new RuntimeException('The PDIMS template sheet was not found.');
    }

    private function rows(?Worksheet $sheet): array
    {
        if (! $sheet) {
            return [];
        }

        $headerRow = $this->headerRow($sheet);
        $headers = [];
        foreach ($sheet->getRowIterator($headerRow, $headerRow)->current()->getCellIterator() as $cell) {
            $name = $this->clean($cell->getFormattedValue());
            if ($name !== null) {
                $headers[$cell->getColumn()] = $name;
            }
        }

        $rows = [];
        for ($rowNumber = $headerRow + 1; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $data = ['_row_number' => $rowNumber, '_formula_fields' => []];
            $hasValue = false;
            foreach ($headers as $column => $name) {
                $cell = $sheet->getCell($column.$rowNumber);
                $isFormula = $cell->getDataType() === DataType::TYPE_FORMULA;
                $value = $this->clean($isFormula ? $cell->getValue() : $cell->getFormattedValue());
                $data[$name] = $value;
                $hasValue = $hasValue || $value !== null;
                if ($isFormula) {
                    $data['_formula_fields'][] = $name;
                }
            }
            if ($hasValue) {
                $rows[] = $data;
            }
        }

        return $rows;
    }

    private function headers(Worksheet $sheet): array
    {
        $row = $this->headerRow($sheet);
        $headers = [];
        foreach ($sheet->getRowIterator($row, $row)->current()->getCellIterator() as $cell) {
            if (($value = $this->clean($cell->getFormattedValue())) !== null) {
                $headers[mb_strtolower($value)] = $cell->getColumn();
            }
        }

        return $headers;
    }

    private function headerRow(Worksheet $sheet): int
    {
        for ($row = 1; $row <= min(10, $sheet->getHighestDataRow()); $row++) {
            foreach ($sheet->getRowIterator($row, $row)->current()->getCellIterator() as $cell) {
                if ($this->clean($cell->getFormattedValue()) !== null) {
                    return $row;
                }
            }
        }
        throw new RuntimeException("Sheet {$sheet->getTitle()} has no header row.");
    }

    private function structuredHints(?Worksheet $sheet): array
    {
        $hints = [];
        foreach ($this->rows($sheet) as $row) {
            if (! $this->clean($row['Dosage Strength'] ?? null) || ! $this->clean($row['Dosage Form'] ?? null)) {
                continue;
            }
            $hints[$this->hintKey($row['Molecule'] ?? null, $row['ATC Code'] ?? null, $row['Route'] ?? null)][] = $row;
        }

        return $hints;
    }

    private function hintKey($molecule, $atc, $route): string
    {
        return implode('|', [$this->normalize($molecule), $this->normalize($atc), $this->normalize($route)]);
    }

    private function parseSpecification(?string $spec): array
    {
        $result = ['strength' => null, 'form' => null, 'size' => null];
        if (! $spec) {
            return $result;
        }

        if (preg_match('/\b(amp(?:ou)?le|vial|tablet|capsule|sachet|syrup|suspension|solution|cream|ointment|drops?|suppository|inhaler|nebule|patch|lotion|gel|powder)\b/iu', $spec, $match, PREG_OFFSET_CAPTURE)) {
            $result['form'] = $match[0][0];
            $prefix = trim(substr($spec, 0, $match[0][1]));
            $prefix = preg_replace('/\s+\(as\s+[^)]+\)\s*$/iu', '', $prefix);
            if (preg_match('/^(.*?)(effervescent|chewable|film-coated|modified release|sustained release|extended release|controlled release|orally disintegrating|dispersible|sublingual)\s*$/iu', $prefix, $qualifiedForm)) {
                $prefix = trim($qualifiedForm[1]);
                $result['form'] = trim($qualifiedForm[2].' '.$result['form']);
            }
            $result['strength'] = $prefix ?: null;
        }
        if (preg_match('/\b([0-9]+(?:\.[0-9]+)?)\s*(mL|L|g|kg)\b/iu', $spec, $size)) {
            $result['size'] = $size[1].$size[2];
        }

        return $result;
    }

    private function routeCandidates($route, $technicalSpec): array
    {
        $route = $this->clean($route);
        if (! $route) {
            return [];
        }
        $explicit = [];
        if (preg_match('/\binj\.?\s*:/iu', $route) && $technicalSpec && preg_match_all('/\b(IV|IM|SC|ID|IA|IT|IP)\b/iu', $technicalSpec, $matches)) {
            $explicit = array_values(array_unique(array_map('strtoupper', $matches[1])));
        }
        if ($explicit) {
            return $explicit;
        }
        $clean = trim($route, " \t\n\r\0\x0B:");
        if (preg_match('/[,\/]\s*/', $clean)) {
            return array_values(array_filter(array_map(fn ($value) => trim($value, ' :'), preg_split('/[,\/]\s*/', $clean))));
        }

        return [$clean];
    }

    private function candidate(array $raw, array $mapped): array
    {
        return [...$mapped, 'source_row' => $raw['_row_number'], 'raw' => array_diff_key($raw, array_flip(['_formula_fields']))];
    }

    private function rejectFormulaFields(array $row, array $fields): void
    {
        $found = array_intersect($row['_formula_fields'] ?? [], $fields);
        if ($found) {
            throw new RuntimeException('Formula cells are not allowed in import fields (row '.($row['_row_number'] ?? '?').': '.implode(', ', $found).').');
        }
    }

    private function yn($value): ?string
    {
        return match (mb_strtolower($this->clean($value) ?? '')) {
            'y', 'yes', 'pnf' => 'Y',
            'n', 'no', 'non-pnf', 'non pnf' => 'N',
            default => null,
        };
    }

    private function normalize($value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $value)));
    }

    private function clean($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $value === '' ? null : $value;
    }
}
