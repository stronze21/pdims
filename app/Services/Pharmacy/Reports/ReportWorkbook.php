<?php

namespace App\Services\Pharmacy\Reports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class ReportWorkbook
{
    /** $rows must already be normalized by PharmacyReportService::row(). */
    public function build(string $report, array $metadata, array $totals, iterable $rows, bool $grouped = false): Spreadsheet
    {
        $columns = ReportDefinition::columns($report, $grouped);
        $lastColumn = Coordinate::stringFromColumnIndex(count($columns));
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Report');
        $line = 1;
        foreach ($metadata as $label => $value) {
            $sheet->setCellValueExplicit('A'.$line, ucfirst($label), DataType::TYPE_STRING);
            $sheet->getStyle('A'.$line)->getFont()->setBold(true);
            $sheet->mergeCells('B'.$line.':'.$lastColumn.$line);
            $sheet->setCellValueExplicit('B'.$line, (string) $value, DataType::TYPE_STRING);
            $sheet->getStyle('B'.$line)->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($line++)->setRowHeight(30);
        }
        foreach (ReportDefinition::notes($report, $grouped) as $note) {
            $sheet->mergeCells('A'.$line.':'.$lastColumn.$line);
            $sheet->setCellValueExplicit('A'.$line, $note, DataType::TYPE_STRING);
            $sheet->getStyle('A'.$line)->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($line++)->setRowHeight(44);
        }
        $header = ++$line;
        foreach (array_values($columns) as $index => $label) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$line, $label, DataType::TYPE_STRING);
        }
        foreach ($rows as $row) {
            if (++$line > 1048575) {
                $book->disconnectWorksheets();
                abort(422, 'Too many rows for Excel. Narrow the report date range or medicine filter.');
            }
            foreach (array_keys($columns) as $index => $key) {
                $value = $row[$key] ?? null;
                if ($value !== null) {
                    $numeric = ReportDefinition::numeric($key);
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($index + 1).$line,
                        $numeric ? (float) $value : (string) $value, $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                }
            }
        }
        $lastData = $line;
        $line++;
        foreach (array_keys($columns) as $index => $key) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            if ($index === 0) {
                $sheet->setCellValueExplicit($letter.$line, 'Filtered totals', DataType::TYPE_STRING);
            } elseif (array_key_exists($key, $totals)) {
                $sheet->setCellValueExplicit($letter.$line, (float) $totals[$key], DataType::TYPE_NUMERIC);
            }
            $sheet->getColumnDimension($letter)->setWidth($index === 0 ? 42 : 23);
            if (ReportDefinition::numeric($key)) {
                $sheet->getStyle($letter.($header + 1).':'.$letter.$line)->getNumberFormat()->setFormatCode($key === 'member_count' ? '0' : '#,##0.00;[Red]-#,##0.00');
            }
        }
        $sheet->getStyle("A$header:$lastColumn$header")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A$header:$lastColumn$header")->getFill()->setFillType('solid')->getStartColor()->setARGB('FF334155');
        $sheet->getStyle("A$line:$lastColumn$line")->getFont()->setBold(true);
        $sheet->setAutoFilter("A$header:$lastColumn$lastData");
        $sheet->freezePane('A'.($header + 1));
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($header, $header);

        return $book;
    }
}
