<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalServicesController extends Controller
{
    /**
     * Get patient's appointment history from eclinic database.
     */
    public function appointments(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        try {
            $appointments = DB::connection('portal')->table('appointments')
                ->leftJoin('appointment_types', 'appointments.appointment_type', '=', 'appointment_types.id')
                ->where('appointments.patient_id', $patient->id)
                ->whereNull('appointments.deleted_at')
                ->orderByDesc('appointments.appointment_date')
                ->select(
                    'appointments.id',
                    'appointments.appointment_date',
                    'appointment_types.name as appointment_type_name',
                    'appointments.attending_clinic',
                    'appointments.doctor',
                    'appointments.remarks',
                    'appointments.ref_no',
                    'appointments.queue_no',
                    'appointments.confirmed_by'
                )
                ->get()
                ->map(function ($appt) {
                    return [
                        'id' => $appt->id,
                        'appointment_date' => $appt->appointment_date,
                        'appointment_type' => $appt->appointment_type_name ?? 'General',
                        'attending_clinic' => $appt->attending_clinic,
                        'doctor' => $appt->doctor,
                        'remarks' => $appt->remarks,
                        'ref_no' => $appt->ref_no,
                        'queue_no' => $appt->queue_no,
                        'status' => $appt->confirmed_by ? 'Confirmed' : 'Pending',
                    ];
                });

            return response()->json($appointments);
        } catch (\Exception $e) {
            // Table may not exist yet
            return response()->json([]);
        }
    }

    /**
     * Get patient's lab results from hospital database.
     * Queries hlaborder for laboratory orders linked to the patient.
     */
    public function labResults(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        try {
            $labResults = DB::connection('hospital')->select("
                SELECT
                    l.pcchrgcod AS lab_code,
                    c.chrgdesc AS lab_name,
                    l.enccode,
                    l.estession AS department,
                    l.oession AS ordered_by,
                    l.orderfrom AS ordered_from,
                    l.datemod AS result_date,
                    enctr.encdate,
                    CASE
                        WHEN l.resulession IS NOT NULL THEN 'Released'
                        ELSE 'Pending'
                    END AS status
                FROM hospital.dbo.hlaborder l WITH (NOLOCK)
                LEFT JOIN hospital.dbo.hcharge c WITH (NOLOCK)
                    ON l.pcchrgcod = c.chrgcode
                LEFT JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                    ON l.enccode = enctr.enccode
                WHERE l.hpercode = ?
                ORDER BY COALESCE(l.datemod, enctr.encdate) DESC
            ", [$hpercode]);

            return response()->json(collect($labResults)->map(function ($lab) {
                return [
                    'lab_code' => $lab->lab_code,
                    'lab_name' => $lab->lab_name ?? 'Laboratory Test',
                    'encounter_date' => $lab->encdate,
                    'result_date' => $lab->result_date,
                    'ordered_by' => $lab->ordered_by,
                    'department' => $lab->department,
                    'status' => $lab->status,
                ];
            })->values());
        } catch (\Exception $e) {
            // hlaborder table may not exist in all hospital instances
            return response()->json([]);
        }
    }

    /**
     * Get patient's billing summary per encounter from hospital database.
     */
    public function billing(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $billingRecords = DB::connection('hospital')->select("
            SELECT
                at.enccode,
                at.billstat,
                at.billdate,
                enctr.encdate,
                enctr.toecode,
                CASE
                    WHEN enctr.toecode = 'OPD' THEN 'Out-Patient'
                    WHEN enctr.toecode = 'ER' THEN 'Emergency Room'
                    WHEN enctr.toecode = 'ERADM' THEN 'ER to Admission'
                    WHEN enctr.toecode = 'ADM' THEN 'Admission'
                    WHEN enctr.toecode = 'OPDAD' THEN 'OPD to Admission'
                    ELSE enctr.toecode
                END AS encounter_type,
                CASE at.billstat
                    WHEN '00' THEN 'Unbilled'
                    WHEN '01' THEN 'Partially Billed'
                    WHEN '02' THEN 'Fully Billed'
                    WHEN '03' THEN 'Closed'
                    ELSE 'Unknown'
                END AS billing_status,
                emp.lastname + ', ' + emp.firstname AS attending_doctor
            FROM hospital.dbo.hactrack at WITH (NOLOCK)
            INNER JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON at.enccode = enctr.enccode
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON enctr.sopcode1 = emp.employeeid
            WHERE enctr.hpercode = ?
            ORDER BY enctr.encdate DESC
        ", [$hpercode]);

        return response()->json(collect($billingRecords)->map(function ($bill) {
            return [
                'enccode' => $bill->enccode,
                'encounter_date' => $bill->encdate,
                'encounter_type' => $bill->encounter_type,
                'billing_status' => $bill->billing_status,
                'bill_status_code' => $bill->billstat,
                'bill_date' => $bill->billdate,
                'attending_doctor' => $bill->attending_doctor,
            ];
        })->values());
    }

    /**
     * Get patient's active prescription queue entries.
     */
    public function queue(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $queues = PrescriptionQueue::where('hpercode', $hpercode)
            ->whereDate('queued_at', today())
            ->orderByDesc('queued_at')
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'queue_number' => $q->queue_number,
                    'queue_status' => $q->queue_status,
                    'priority' => $q->priority,
                    'queued_at' => $q->queued_at?->toIso8601String(),
                    'estimated_wait_minutes' => $q->estimated_wait_minutes,
                    'wait_time_minutes' => $q->getWaitTimeMinutes(),
                    'assigned_window' => $q->assigned_window,
                    'location_code' => $q->location_code,
                    'remarks' => $q->remarks,
                ];
            });

        // Also get recent completed/cancelled (last 7 days)
        $recentQueues = PrescriptionQueue::where('hpercode', $hpercode)
            ->whereIn('queue_status', ['dispensed', 'cancelled'])
            ->where('queued_at', '>=', now()->subDays(7))
            ->orderByDesc('queued_at')
            ->limit(10)
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'queue_number' => $q->queue_number,
                    'queue_status' => $q->queue_status,
                    'priority' => $q->priority,
                    'queued_at' => $q->queued_at?->toIso8601String(),
                    'estimated_wait_minutes' => $q->estimated_wait_minutes,
                    'wait_time_minutes' => $q->getWaitTimeMinutes(),
                    'assigned_window' => $q->assigned_window,
                    'location_code' => $q->location_code,
                    'remarks' => $q->remarks,
                ];
            });

        return response()->json([
            'active' => $queues->values(),
            'recent' => $recentQueues->values(),
        ]);
    }
}
