<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\PortalPrescriptionRefill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalPrescriptionController extends Controller
{
    /**
     * Get patient's prescriptions from the webapp database.
     */
    public function prescriptions(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $prescriptions = DB::connection('webapp')->select("
            SELECT
                rx.id,
                rx.enccode,
                rx.hpercode,
                rx.created_at,
                rx.updated_at,
                emp.lastname + ', ' + emp.firstname AS doctor_name,
                (SELECT COUNT(*)
                 FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
                 WHERE pd.presc_id = rx.id AND pd.stat = 'A') AS item_count
            FROM webapp.dbo.prescription rx WITH (NOLOCK)
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON rx.empid = emp.employeeid
            WHERE rx.hpercode = ?
            ORDER BY rx.created_at DESC
        ", [$hpercode]);

        return response()->json($prescriptions);
    }

    /**
     * Get prescription items/drugs for a specific prescription.
     */
    public function prescriptionItems(Request $request, $prescriptionId)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        // Verify prescription belongs to this patient
        $prescription = DB::connection('webapp')->selectOne("
            SELECT id, hpercode FROM webapp.dbo.prescription WITH (NOLOCK)
            WHERE id = ? AND hpercode = ?
        ", [$prescriptionId, $hpercode]);

        if (!$prescription) {
            return response()->json(['message' => 'Prescription not found.'], 404);
        }

        $items = DB::connection('webapp')->select("
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
        ", [$prescriptionId]);

        $processedItems = collect($items)->map(function ($item) {
            $parts = explode('_,', $item->drug_concat ?? '');
            return [
                'id' => (int) $item->id,
                'dmdcomb' => (string) ($item->dmdcomb ?? ''),
                'dmdctr' => (string) ($item->dmdctr ?? ''),
                'generic' => $parts[0] ?? 'N/A',
                'brand' => $parts[1] ?? '',
                'drug_name' => trim(($parts[0] ?? '') . ' ' . ($parts[1] ?? '')),
                'qty_ordered' => (float) ($item->qty ?? 0),
                'qty_issued' => (float) ($item->total_issued ?? 0),
                'qty_remaining' => (float) ($item->remaining_qty ?? 0),
                'order_type' => (string) ($item->order_type ?? ''),
                'remark' => (string) ($item->remark ?? ''),
            ];
        });

        return response()->json($processedItems);
    }

    /**
     * Submit a prescription refill request.
     */
    public function requestRefill(Request $request)
    {
        $request->validate([
            'prescription_id' => 'required|integer',
            'prescription_data_id' => 'required|integer',
            'dmdcomb' => 'nullable|string|max:50',
            'dmdctr' => 'nullable|string|max:50',
            'drug_name' => 'required|string|max:255',
            'qty_requested' => 'required|numeric|min:1',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient || !$patient->hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        // Verify prescription belongs to this patient
        $prescription = DB::connection('webapp')->selectOne("
            SELECT id, enccode, hpercode FROM webapp.dbo.prescription WITH (NOLOCK)
            WHERE id = ? AND hpercode = ?
        ", [$request->prescription_id, $patient->hpercode]);

        if (!$prescription) {
            return response()->json(['message' => 'Prescription not found.'], 404);
        }

        // Check for existing pending refill for same item
        $existingRefill = PortalPrescriptionRefill::where('patient_id', $patient->id)
            ->where('prescription_data_id', $request->prescription_data_id)
            ->where('status', 'pending')
            ->first();

        if ($existingRefill) {
            return response()->json([
                'message' => 'You already have a pending refill request for this medication.',
            ], 422);
        }

        $refill = PortalPrescriptionRefill::create([
            'patient_id' => $patient->id,
            'hpercode' => $patient->hpercode,
            'enccode' => $prescription->enccode,
            'prescription_id' => $request->prescription_id,
            'prescription_data_id' => $request->prescription_data_id,
            'dmdcomb' => $request->dmdcomb,
            'dmdctr' => $request->dmdctr,
            'drug_name' => $request->drug_name,
            'qty_requested' => $request->qty_requested,
            'remarks' => $request->remarks,
        ]);

        return response()->json([
            'message' => 'Refill request submitted successfully.',
            'refill' => $refill,
        ], 201);
    }

    /**
     * Get patient's refill request history.
     */
    public function refillHistory(Request $request)
    {
        $account = $request->user();
        $account->load('patient');

        if (!$account->patient) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $refills = PortalPrescriptionRefill::where('patient_id', $account->patient->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($refills);
    }
}
