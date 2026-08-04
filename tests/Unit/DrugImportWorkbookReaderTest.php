<?php

use App\Services\Pharmacy\DrugImport\DrugImportWorkbookReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function temporaryDrugWorkbook(Spreadsheet $book): string
{
    $path = tempnam(sys_get_temp_dir(), 'drug-import-').'.xlsx';
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    return $path;
}

it('adapts the supplied local database layout without importing helper rows', function () {
    $book = new Spreadsheet;
    $pnf = $book->getActiveSheet()->setTitle('PNF');
    $pnf->fromArray(['Molecule', 'Route', 'Technical Specifications', 'Level 1', 'Level 2', 'Level 3', 'Level 4', 'ATC Code'], null, 'A1');
    $pnf->fromArray(['Abacavir', 'Oral:', '300 mg (as Sulfate) Tablet', 'L1', 'L2', 'L3', 'L4', 'J05AF06'], null, 'A2');
    $pnf->fromArray(['Example Drug', 'Inj.:', '10 mg/mL in 1 mL Ampule (IM, IV)', 'L1', 'L2', 'L3', 'L4', 'A01AA01'], null, 'A3');
    $pnf->fromArray(['Acetylcysteine', 'Oral:', '600 mg Effervescent tablet', 'L1', 'L2', 'L3', 'L4', 'R05CB01'], null, 'A4');

    $copy = $book->createSheet()->setTitle('Copy of PNF');
    $copy->fromArray(['Molecule', 'Route', 'Dosage Strength', 'Dosage Form', 'Size', 'Level 1', 'Level 2', 'Level 3', 'Level 4', 'ATC Code'], null, 'A1');
    $copy->fromArray(['Abacavir', 'Oral:', '300mg', 'Tablet', null, 'L1', 'L2', 'L3', 'L4', 'J05AF06'], null, 'A2');

    $final = $book->createSheet()->setTitle('FINAL TABLE');
    $final->fromArray(['Item', 'Generic Name', 'Dosage Strength', 'Dosage Form', 'Size', 'Route', 'PNF/non-PNF'], null, 'A1');
    $final->fromArray(['Local medicine', 'Local medicine', '5mg', 'Tablet', null, 'Oral', 'non-PNF'], null, 'A2');
    $final->fromArray(['Duplicate hint', 'Abacavir', '300mg', 'Tablet', null, 'Oral', 'PNF'], null, 'A3');
    $final->fromArray(['Unknown', 'Unknown', '1mg', 'Tablet', null, 'Oral', null], null, 'A4');

    $path = temporaryDrugWorkbook($book);
    try {
        $result = app(DrugImportWorkbookReader::class)->read($path);
        expect($result['adapter'])->toBe(DrugImportWorkbookReader::PDIMS_LOCAL)
            ->and($result['rows'])->toHaveCount(6)
            ->and(array_column($result['rows'], 'route_text'))->toContain('IM', 'IV', 'Oral')
            ->and(collect($result['rows'])->where('source_sheet', 'FINAL TABLE'))->toHaveCount(2)
            ->and(collect($result['rows'])->firstWhere('generic_name', 'Unknown')['pndf'])->toBe('Y')
            ->and(collect($result['rows'])->firstWhere('generic_name', 'Acetylcysteine')['strength_text'])->toBe('600 mg')
            ->and(collect($result['rows'])->firstWhere('generic_name', 'Acetylcysteine')['form_text'])->toBe('Effervescent tablet')
            ->and(implode(' ', $result['warnings']))->toContain('1 FINAL TABLE rows with blank PNDF/non-PNDF were treated as PNDF');
    } finally {
        @unlink($path);
    }
});

it('preserves displayed percentage strengths', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('PNF')->fromArray(['Molecule', 'Route', 'Technical Specifications', 'Level 1', 'Level 2', 'Level 3', 'Level 4', 'ATC Code'], null, 'A1');
    $final = $book->createSheet()->setTitle('FINAL TABLE');
    $final->fromArray(['Item', 'Generic Name', 'Dosage Strength', 'Dosage Form', 'Size', 'Route', 'PNF/non-PNF'], null, 'A1');
    $final->fromArray(['Test solution', 'Test', .009, 'Solution', null, 'Topical', 'non-PNF'], null, 'A2');
    $final->getStyle('C2')->getNumberFormat()->setFormatCode('0.00%');
    $path = temporaryDrugWorkbook($book);
    try {
        $result = app(DrugImportWorkbookReader::class)->read($path);
        expect($result['rows'][0]['strength_text'])->toBe('0.90%');
    } finally {
        @unlink($path);
    }
});

it('reads the reusable PDIMS template and rejects formulas in import fields', function () {
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet()->setTitle('Drug Import');
    $sheet->fromArray(['Generic Name', 'Group Code', 'Dosage Number', 'Strength', 'Dosage Form', 'Route', 'PNDF', 'RX/OTC', 'Status'], null, 'A1');
    $sheet->fromArray(['Paracetamol', '', 500, 'mg', 'Tablet', 'Oral', 'Y', 'RXX', 'A'], null, 'A2');
    $path = temporaryDrugWorkbook($book);
    try {
        $result = app(DrugImportWorkbookReader::class)->read($path);
        expect($result['adapter'])->toBe(DrugImportWorkbookReader::PDIMS_TEMPLATE)
            ->and($result['rows'][0]['strength_text'])->toBe('500 mg');
    } finally {
        @unlink($path);
    }

    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet()->setTitle('Drug Import');
    $sheet->fromArray(['Generic Name', 'Group Code', 'Dosage Number', 'Strength', 'Dosage Form', 'Route', 'PNDF', 'RX/OTC', 'Status'], null, 'A1');
    $sheet->setCellValue('A2', '=UPPER("paracetamol")');
    $sheet->fromArray(['', '', 500, 'mg', 'Tablet', 'Oral', 'Y', 'RXX', 'A'], null, 'B2');
    $path = temporaryDrugWorkbook($book);
    try {
        expect(fn () => app(DrugImportWorkbookReader::class)->read($path))->toThrow(RuntimeException::class, 'Formula cells are not allowed');
    } finally {
        @unlink($path);
    }
});
