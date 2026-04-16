<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Pharmacy\PrescriptionReactivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionReactivationService $reactivationService)
    {
    }

    public function getPrescribedItems($prescriptionId)
    {
        try {
            $items = collect(DB::connection('webapp')->select("
            SELECT
                pd.id,
                pd.dmdcomb,
                pd.dmdctr,
                pd.qty,
                pd.order_type,
                pd.stat,
                pd.remark,
                dm.drug_concat,
                COALESCE(pdi.total_issued, 0) as total_issued
            FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
            INNER JOIN hospital.dbo.hdmhdr dm WITH (NOLOCK)
                ON pd.dmdcomb = dm.dmdcomb AND pd.dmdctr = dm.dmdctr
            LEFT JOIN (
                SELECT presc_data_id, SUM(qtyissued) as total_issued
                FROM webapp.dbo.prescription_data_issued WITH (NOLOCK)
                GROUP BY presc_data_id
            ) pdi ON pd.id = pdi.presc_data_id
            WHERE pd.presc_id = ?
                AND pd.stat = 'A'
            ORDER BY pd.created_at ASC
        ", [$prescriptionId]));


            $processedItems = $this->reactivationService->enrichItems($items)->map(function (array $item) {
                $parts = explode('_,', $item['drug_concat'] ?? '');
                return [
                    'id' => (int) $item['id'],
                    'dmdcomb' => (string) ($item['dmdcomb'] ?? ''),
                    'dmdctr' => (string) ($item['dmdctr'] ?? ''),
                    'generic' => $parts[0] ?? 'N/A',
                    'brand' => $parts[1] ?? '',
                    'drug_concat' => (string) ($item['drug_concat'] ?? ''),
                    'qty_per_administration' => (float) ($item['qty_per_administration'] ?? 0),
                    'administrations_per_day' => $item['administrations_per_day'],
                    'duration_days' => $item['duration_days'],
                    'computed_total_qty' => $item['computed_total_qty'],
                    'single_allowable_dispense_qty' => $item['single_allowable_dispense_qty'],
                    'allowable_request_qty' => $item['allowable_request_qty'],
                    'qty_issued' => (float) ($item['qty_issued'] ?? 0),
                    'qty_remaining' => $item['computed_remaining_qty'],
                    'order_type' => (string) ($item['order_type'] ?? ''),
                    'remark' => (string) ($item['remark'] ?? ''),
                    'status' => (string) ($item['stat'] ?? 'A'),
                    'is_fully_issued' => (bool) ($item['is_fully_issued'] ?? false),
                    'needs_manual_review' => (bool) ($item['needs_manual_review'] ?? false),
                    'calculation_notes' => $item['calculation_notes'] ?? null,
                ];
            });

            return response()->json($processedItems);
        } catch (\Exception $e) {
            \Log::error('Error loading prescribed items: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
