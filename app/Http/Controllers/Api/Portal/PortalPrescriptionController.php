<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\PortalPrescriptionRefill;
use App\Services\Pharmacy\PrescriptionReactivationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalPrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionReactivationService $reactivationService)
    {
    }

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

        $start = max((int) $request->query('start', 0), 0);
        $count = (int) $request->query('count', 12);
        $count = $count > 0 ? min($count, 12) : 12;

        $baseQuery = "
            FROM hospital.dbo.hrxoissue rxi WITH (NOLOCK)
            INNER JOIN hospital.dbo.hrxo hrxo WITH (NOLOCK)
                ON hrxo.docointkey = rxi.docointkey
            INNER JOIN hospital.dbo.hdmhdr hdmhdr WITH (NOLOCK)
                ON hdmhdr.dmdcomb = hrxo.dmdcomb
                AND hdmhdr.dmdctr = hrxo.dmdctr
            LEFT JOIN hospital.dbo.hcharge hcharge WITH (NOLOCK)
                ON hcharge.chrgcode = COALESCE(rxi.issuedfrom, hrxo.orderfrom)
            LEFT JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON enctr.enccode = hrxo.enccode
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON emp.employeeid = COALESCE(hrxo.prescribed_by, hrxo.entryby, rxi.issuedby)
            WHERE COALESCE(hrxo.hpercode, rxi.hpercode) = ?
              AND COALESCE(rxi.qty, hrxo.qtyissued, 0) > 0
        ";

        $total = DB::connection('hospital')->selectOne("
            SELECT COUNT(*) AS total
            $baseQuery
        ", [$hpercode]);

        $issuedMedications = DB::connection('hospital')->select("
            SELECT
                hrxo.docointkey,
                hrxo.enccode,
                hrxo.hpercode,
                COALESCE(rxi.pcchrgcod, hrxo.pcchrgcod) AS charge_slip_code,
                hrxo.dodate AS order_date,
                hrxo.dotime AS order_time,
                COALESCE(rxi.issuedte, hrxo.dodtepost) AS issued_date,
                COALESCE(rxi.issuetme, hrxo.dotmepost) AS issued_time,
                hrxo.pchrgqty AS quantity_ordered,
                COALESCE(rxi.qty, hrxo.qtyissued) AS quantity_issued,
                COALESCE(rxi.pchrgup, hrxo.pchrgup) AS unit_price,
                COALESCE(rxi.pchrgup, hrxo.pchrgup) * COALESCE(rxi.qty, hrxo.qtyissued) AS charge_amount,
                hdmhdr.drug_concat AS item_name,
                hcharge.chrgdesc AS cost_center_name,
                COALESCE(rxi.issuedfrom, hrxo.orderfrom) AS cost_center_code,
                hrxo.tx_type,
                hrxo.remarks,
                hrxo.estatus,
                COALESCE(hrxo.prescription_data_id, rxi.prescription_data_id) AS prescription_data_id,
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
            $baseQuery
            ORDER BY
                COALESCE(rxi.issuedte, hrxo.dodtepost, hrxo.dodate) DESC,
                COALESCE(rxi.issuetme, hrxo.dotmepost, hrxo.dotime) DESC,
                hrxo.docointkey DESC
            OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
        ", [$hpercode, $start, $count]);

        return response()->json([
            'items' => $issuedMedications,
            'total' => (int) ($total->total ?? 0),
            'start' => $start,
            'count' => $count,
        ]);
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
                0 AS refillable_count
            FROM webapp.dbo.prescription rx WITH (NOLOCK)
            INNER JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON rx.enccode = enctr.enccode
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON rx.empid = emp.employeeid
            WHERE enctr.hpercode = ?
            ORDER BY rx.created_at DESC
        ", [$hpercode]);

        $prescriptionIds = collect($prescriptions)->pluck('id')->filter()->values();

        if ($prescriptionIds->isEmpty()) {
            return response()->json($prescriptions);
        }

        $placeholders = implode(',', array_fill(0, $prescriptionIds->count(), '?'));
        $prescriptionItems = DB::connection('hospital')->select("
            SELECT
                pd.id,
                pd.presc_id,
                pd.qty,
                pd.frequency,
                pd.duration,
                pd.remark,
                pd.addtl_remarks,
                pd.stat,
                pd.archive,
                COALESCE(pdi.total_issued, 0) AS total_issued
            FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
            LEFT JOIN (
                SELECT presc_data_id, SUM(qtyissued) AS total_issued
                FROM webapp.dbo.prescription_data_issued WITH (NOLOCK)
                GROUP BY presc_data_id
            ) pdi ON pd.id = pdi.presc_data_id
            WHERE pd.presc_id IN ({$placeholders})
                AND pd.stat = 'A'
        ", $prescriptionIds->all());

        $refillableCounts = $this->reactivationService
            ->enrichItems($prescriptionItems)
            ->filter(fn (array $item) => !($item['needs_manual_review'] ?? false) && (float) ($item['computed_remaining_qty'] ?? 0) > 0)
            ->groupBy('presc_id')
            ->map->count();

        $processed = collect($prescriptions)->map(function ($prescription) use ($refillableCounts) {
            $row = (array) $prescription;
            $row['refillable_count'] = (int) ($refillableCounts[$prescription->id] ?? 0);

            return $row;
        });

        return response()->json($processed);
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
            ORDER BY pd.stat ASC, pd.created_at ASC
        ", [$prescriptionId]);

        // Check which items already have a pending refill
        $pendingRefillIds = PortalPrescriptionRefill::where('patient_id', $account->patient->id)
            ->where('prescription_id', $prescriptionId)
            ->where('status', 'pending')
            ->pluck('prescription_data_id')
            ->toArray();

        $processedItems = $this->reactivationService->enrichItems($items)->map(function (array $item) use ($pendingRefillIds) {
            $parts = explode('_,', $item['drug_concat'] ?? '');
            $remaining = (float) ($item['computed_remaining_qty'] ?? 0);
            $isActive = ($item['stat'] ?? '') === 'A';

            return [
                'id' => (int) $item['id'],
                'dmdcomb' => (string) ($item['dmdcomb'] ?? ''),
                'dmdctr' => (string) ($item['dmdctr'] ?? ''),
                'generic' => $parts[0] ?? 'N/A',
                'brand' => $parts[1] ?? '',
                'drug_name' => trim(($parts[0] ?? '') . ' ' . ($parts[1] ?? '')),
                'qty_per_administration' => (float) ($item['qty_per_administration'] ?? 0),
                'administrations_per_day' => $item['administrations_per_day'],
                'duration_days' => $item['duration_days'],
                'computed_total_qty' => $item['computed_total_qty'],
                'single_allowable_dispense_qty' => $item['single_allowable_dispense_qty'],
                'allowable_request_qty' => $item['allowable_request_qty'],
                'refill_after_30_days_qty' => $item['refill_after_30_days_qty'],
                'qty_issued' => (float) ($item['qty_issued'] ?? 0),
                'qty_remaining' => $remaining,
                'order_type' => (string) ($item['order_type'] ?? ''),
                'stat' => (string) ($item['stat'] ?? ''),
                'is_active' => $isActive,
                'remark' => (string) ($item['remark'] ?? ''),
                'addtl_remarks' => (string) ($item['addtl_remarks'] ?? ''),
                'frequency' => (string) ($item['frequency'] ?? ''),
                'duration' => (string) ($item['duration'] ?? ''),
                'has_remaining' => $isActive && $remaining > 0,
                'has_pending_refill' => in_array($item['id'], $pendingRefillIds),
                'can_auto_reactivate' => (bool) ($item['can_auto_reactivate'] ?? false),
                'needs_manual_review' => (bool) ($item['needs_manual_review'] ?? false),
                'calculation_notes' => $item['calculation_notes'] ?? null,
            ];
        });

        return response()->json($processedItems);
    }

    public function reactivatedToday(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $items = $this->reactivationService->reactivatedToday(now('Asia/Manila'), $hpercode)
            ->values()
            ->all();

        return response()->json($items);
    }

    public function reactivatesTomorrow(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $items = $this->reactivationService->reactivatesTomorrow(now('Asia/Manila'), $hpercode)
            ->values()
            ->all();

        return response()->json($items);
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
                pd.frequency,
                pd.duration,
                pd.remark,
                pd.addtl_remarks,
                pd.archive,
                pd.stat,
                COALESCE(pdi.total_issued, 0) as total_issued,
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

        $enrichedItem = $this->reactivationService->enrichItem($prescData);
        $remainingQty = (float) ($enrichedItem['computed_remaining_qty'] ?? 0);
        $allowableRequestQty = (float) ($enrichedItem['allowable_request_qty'] ?? $remainingQty);

        if ($enrichedItem['needs_manual_review'] ?? false) {
            return response()->json([
                'message' => 'This medication needs manual review before refill because the original CDOE schedule could not be computed automatically.',
            ], 422);
        }

        if ($remainingQty <= 0) {
            return response()->json([
                'message' => 'This medication has been fully dispensed. Please consult your doctor for a new prescription.',
            ], 422);
        }

        if ($request->qty_requested > $allowableRequestQty) {
            return response()->json([
                'message' => "Requested quantity exceeds the current allowable release ({$allowableRequestQty}). A single dispense/request is limited to 30 days, with the remaining balance to be refilled later.",
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
            'request_source' => 'patient',
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
