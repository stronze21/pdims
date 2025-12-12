<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrescriptionController extends Controller
{
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
                COALESCE(pdi.total_issued, 0) as total_issued,
                (pd.qty - COALESCE(pdi.total_issued, 0)) as remaining_qty
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

            // Debug: Log the raw data types
            if ($items->isNotEmpty()) {
                $firstItem = $items->first();
                \Log::info('Raw item data types:', [
                    'id' => gettype($firstItem->id) . ' = ' . $firstItem->id,
                    'qty' => gettype($firstItem->qty) . ' = ' . $firstItem->qty,
                    'total_issued' => gettype($firstItem->total_issued) . ' = ' . $firstItem->total_issued,
                ]);
            }

            $processedItems = $items->map(function ($item) {
                $parts = explode('_,', $item->drug_concat ?? '');
                return [
                    'id' => (int) $item->id,
                    'dmdcomb' => (string) ($item->dmdcomb ?? ''),
                    'dmdctr' => (string) ($item->dmdctr ?? ''),
                    'generic' => $parts[0] ?? 'N/A',
                    'brand' => $parts[1] ?? '',
                    'drug_concat' => (string) ($item->drug_concat ?? ''),
                    'qty_ordered' => (float) ($item->qty ?? 0),
                    'qty_issued' => (float) ($item->total_issued ?? 0),
                    'qty_remaining' => (float) ($item->remaining_qty ?? 0),
                    'order_type' => (string) ($item->order_type ?? ''),
                    'remark' => (string) ($item->remark ?? ''),
                    'status' => (string) ($item->stat ?? 'A'),
                    'is_fully_issued' => ((float) ($item->remaining_qty ?? 0)) <= 0,
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
