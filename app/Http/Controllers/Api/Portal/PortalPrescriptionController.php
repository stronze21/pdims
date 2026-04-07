<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\PortalPrescriptionRefill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalPrescriptionController extends Controller
{
    /**
     * Get issued medications from hrxo, including walk-in / non-prescription issues.
     */
    public function issuedMedications(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $issuedMedications = DB::connection('hospital')->select("
            SELECT
                hrxo.docointkey,
                hrxo.enccode,
                hrxo.hpercode,
                hrxo.pcchrgcod AS charge_slip_code,
                hrxo.dodate AS order_date,
                hrxo.dotime AS order_time,
                hrxo.dodtepost AS issued_date,
                hrxo.dotmepost AS issued_time,
                hrxo.pchrgqty AS quantity_ordered,
                hrxo.qtyissued AS quantity_issued,
                hrxo.pchrgup AS unit_price,
                hrxo.pcchrgamt AS charge_amount,
                hdmhdr.drug_concat AS item_name,
                hcharge.chrgdesc AS cost_center_name,
                hrxo.orderfrom AS cost_center_code,
                hrxo.tx_type,
                hrxo.remarks,
                hrxo.estatus,
                hrxo.prescription_data_id,
                enctr.encdate AS encounter_date,
                CASE
                    WHEN enctr.toecode = 'OPD' THEN 'Out-Patient'
                    WHEN enctr.toecode = 'ER' THEN 'Emergency Room'
                    WHEN enctr.toecode = 'ADM' THEN 'Admission'
                    WHEN enctr.toecode = 'ERADM' THEN 'ER to Admission'
                    WHEN enctr.toecode = 'OPDAD' THEN 'OPD to Admission'
                    WHEN enctr.toecode = 'WALKN' THEN 'Walk-In'
                    ELSE enctr.toecode
                END AS encounter_type,
                emp.lastname + ', ' + emp.firstname AS ordered_by
            FROM hospital.dbo.hrxo hrxo WITH (NOLOCK)
            INNER JOIN hospital.dbo.hdmhdr hdmhdr WITH (NOLOCK)
                ON hdmhdr.dmdcomb = hrxo.dmdcomb
                AND hdmhdr.dmdctr = hrxo.dmdctr
            LEFT JOIN hospital.dbo.hcharge hcharge WITH (NOLOCK)
                ON hcharge.chrgcode = hrxo.orderfrom
            LEFT JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON enctr.enccode = hrxo.enccode
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON emp.employeeid = COALESCE(hrxo.prescribed_by, hrxo.entryby)
            WHERE hrxo.hpercode = ?
              AND hrxo.estatus = 'S'
              AND COALESCE(hrxo.qtyissued, 0) > 0
            ORDER BY
                COALESCE(hrxo.dodtepost, hrxo.dodate) DESC,
                COALESCE(hrxo.dotmepost, hrxo.dotime) DESC,
                hrxo.docointkey DESC
        ", [$hpercode]);

        return response()->json($issuedMedications);
    }

    /**
     * Get patient's prescriptions from the webapp database.
     * Returns all prescriptions (active and inactive) with status indicators.
     */
    public function prescriptions(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $prescriptions = DB::connection('hospital')->select("
            SELECT
                rx.id,
                rx.enccode,
                enctr.hpercode,
                rx.created_at,
                rx.updated_at,
                emp.lastname + ', ' + emp.firstname AS doctor_name,
                CASE WHEN enctr.encstat = 'A' THEN 1 ELSE 0 END AS is_active,
                enctr.toecode AS encounter_type,
                (SELECT COUNT(*)
                 FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
                 WHERE pd.presc_id = rx.id) AS item_count,
                (SELECT COUNT(*)
                 FROM webapp.dbo.prescription_data pd_active WITH (NOLOCK)
                 WHERE pd_active.presc_id = rx.id AND pd_active.stat = 'A') AS active_item_count,
                (SELECT COUNT(*)
                 FROM webapp.dbo.prescription_data pd2 WITH (NOLOCK)
                 LEFT JOIN (
                     SELECT presc_data_id, SUM(qtyissued) as total_issued
                     FROM webapp.dbo.prescription_data_issued WITH (NOLOCK)
                     GROUP BY presc_data_id
                 ) pdi ON pd2.id = pdi.presc_data_id
                 WHERE pd2.presc_id = rx.id AND pd2.stat = 'A'
                 AND (pd2.qty - COALESCE(pdi.total_issued, 0)) > 0) AS refillable_count
            FROM webapp.dbo.prescription rx WITH (NOLOCK)
            INNER JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON rx.enccode = enctr.enccode
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON rx.empid = emp.employeeid
            WHERE enctr.hpercode = ?
            ORDER BY rx.created_at DESC
        ", [$hpercode]);

        return response()->json($prescriptions);
    }

    /**
     * Get prescription items with frequency, duration, and dispensing history.
     */
    public function prescriptionItems(Request $request, $prescriptionId)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $prescription = DB::connection('hospital')->selectOne("
            SELECT rx.id, enctr.hpercode
            FROM webapp.dbo.prescription rx WITH (NOLOCK)
            INNER JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON rx.enccode = enctr.enccode
            WHERE rx.id = ? AND enctr.hpercode = ?
        ", [$prescriptionId, $hpercode]);

        if (!$prescription) {
            return response()->json(['message' => 'Prescription not found.'], 404);
        }

        $items = DB::connection('hospital')->select("
            SELECT
                pd.id,
                pd.dmdcomb,
                pd.dmdctr,
                pd.qty,
                pd.order_type,
                pd.stat,
                pd.remark,
                pd.addtl_remarks,
                pd.frequency,
                pd.duration,
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
            ORDER BY pd.stat ASC, pd.created_at ASC
        ", [$prescriptionId]);

        // Check which items already have a pending refill
        $pendingRefillIds = PortalPrescriptionRefill::where('patient_id', $account->patient->id)
            ->where('prescription_id', $prescriptionId)
            ->where('status', 'pending')
            ->pluck('prescription_data_id')
            ->toArray();

        $processedItems = collect($items)->map(function ($item) use ($pendingRefillIds) {
            $parts = explode('_,', $item->drug_concat ?? '');
            $remaining = (float) ($item->remaining_qty ?? 0);
            $isActive = ($item->stat ?? '') === 'A';

            return [
                'id' => (int) $item->id,
                'dmdcomb' => (string) ($item->dmdcomb ?? ''),
                'dmdctr' => (string) ($item->dmdctr ?? ''),
                'generic' => $parts[0] ?? 'N/A',
                'brand' => $parts[1] ?? '',
                'drug_name' => trim(($parts[0] ?? '') . ' ' . ($parts[1] ?? '')),
                'qty_ordered' => (float) ($item->qty ?? 0),
                'qty_issued' => (float) ($item->total_issued ?? 0),
                'qty_remaining' => $remaining,
                'order_type' => (string) ($item->order_type ?? ''),
                'stat' => (string) ($item->stat ?? ''),
                'is_active' => $isActive,
                'remark' => (string) ($item->remark ?? ''),
                'addtl_remarks' => (string) ($item->addtl_remarks ?? ''),
                'frequency' => (string) ($item->frequency ?? ''),
                'duration' => (string) ($item->duration ?? ''),
                'has_remaining' => $isActive && $remaining > 0,
                'has_pending_refill' => in_array($item->id, $pendingRefillIds),
            ];
        });

        return response()->json($processedItems);
    }

    /**
     * Submit a prescription refill request.
     * Validates qty_requested does not exceed remaining qty.
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
        $prescription = DB::connection('hospital')->selectOne("
            SELECT rx.id, rx.enccode, enctr.hpercode
            FROM webapp.dbo.prescription rx WITH (NOLOCK)
            INNER JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON rx.enccode = enctr.enccode
            WHERE rx.id = ? AND enctr.hpercode = ?
        ", [$request->prescription_id, $patient->hpercode]);

        if (!$prescription) {
            return response()->json(['message' => 'Prescription not found.'], 404);
        }

        // Check remaining qty from webapp
        $prescData = DB::connection('hospital')->selectOne("
            SELECT
                pd.qty,
                COALESCE(pdi.total_issued, 0) as total_issued,
                (pd.qty - COALESCE(pdi.total_issued, 0)) as remaining_qty
            FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
            LEFT JOIN (
                SELECT presc_data_id, SUM(qtyissued) as total_issued
                FROM webapp.dbo.prescription_data_issued WITH (NOLOCK)
                GROUP BY presc_data_id
            ) pdi ON pd.id = pdi.presc_data_id
            WHERE pd.id = ? AND pd.presc_id = ? AND pd.stat = 'A'
        ", [$request->prescription_data_id, $request->prescription_id]);

        if (!$prescData) {
            return response()->json(['message' => 'Prescription item not found or inactive.'], 404);
        }

        if ((float) $prescData->remaining_qty <= 0) {
            return response()->json([
                'message' => 'This medication has been fully dispensed. Please consult your doctor for a new prescription.',
            ], 422);
        }

        if ($request->qty_requested > (float) $prescData->remaining_qty) {
            return response()->json([
                'message' => "Requested quantity exceeds remaining balance ({$prescData->remaining_qty}). You can request up to {$prescData->remaining_qty}.",
            ], 422);
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
            'message' => 'Refill request submitted successfully. The pharmacy will process your request.',
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
