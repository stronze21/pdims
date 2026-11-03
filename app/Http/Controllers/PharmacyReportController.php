<?php

namespace App\Http\Controllers;

use App\Services\Pharmacy\Reports\PharmacyReportService;
use App\Services\Pharmacy\Reports\EquivalentReportGrouping;
use App\Services\Pharmacy\Reports\ReportDefinition;
use App\Services\Pharmacy\Reports\ReportWorkbook;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PharmacyReportController extends Controller
{
    public function print(Request $request, string $report, PharmacyReportService $service)
    {
        abort_unless(isset(ReportDefinition::TITLES[$report]), 404);
        $options = $service->options();
        $filters = $service->filters($request->query(), $options);
        $query = $service->query($report, $filters);

        return response()->view('pharmacy.reports.print', [
            'report' => $report, 'columns' => ReportDefinition::columns($report, EquivalentReportGrouping::enabled($filters)),
            'metadata' => $service->metadata($report, $filters, $options),
            'notes' => ReportDefinition::notes($report, EquivalentReportGrouping::enabled($filters)), 'totals' => $service->totals($report, $query),
            'rows' => $query->cursor()->map(fn ($row) => $service->row($report, $row)),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, string $report, PharmacyReportService $service)
    {
        abort_unless(isset(ReportDefinition::TITLES[$report]), 404);
        $options = $service->options();
        $filters = $service->filters($request->query(), $options);
        $query = $service->query($report, $filters);
        $metadata = $service->metadata($report, $filters, $options);
        $totals = $service->totals($report, $query);
        $rows = $query->cursor()->map(fn ($record) => $service->row($report, $record));
        $book = (new ReportWorkbook)->build($report, $metadata, $totals, $rows, EquivalentReportGrouping::enabled($filters));
        $path = null;
        try {
            $path = tempnam(sys_get_temp_dir(), 'pdims-report-');
            if ($path === false) {
                throw new \RuntimeException('Unable to create report download.');
            }
            (new Xlsx($book))->save($path);

            return response()->download($path, 'pharmacy-'.$report.'-'.now()->format('Ymd-His').'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
            throw $e;
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
