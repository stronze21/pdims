<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalEncounterController extends Controller
{
    /**
     * Get patient's encounter history (records).
     * Returns encounters with type, status, diagnosis, and attending doctor.
     */
    public function encounters(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $encounters = DB::connection('hospital')->select("
            SELECT
                enctr.enccode,
                enctr.hpercode,
                enctr.encdate,
                enctr.enctime,
                enctr.toecode,
                enctr.encstat,
                enctr.enclock,
                CASE
                    WHEN enctr.toecode = 'OPD' THEN 'Out-Patient'
                    WHEN enctr.toecode = 'ER' THEN 'Emergency Room'
                    WHEN enctr.toecode = 'ERADM' THEN 'ER to Admission'
                    WHEN enctr.toecode = 'ADM' THEN 'Admission'
                    WHEN enctr.toecode = 'OPDAD' THEN 'OPD to Admission'
                    WHEN enctr.toecode = 'WALKN' THEN 'Walk-In'
                    ELSE enctr.toecode
                END AS encounter_type_label,
                CASE
                    WHEN enctr.toecode IN ('OPD', 'OPDAD') AND opd.opddtedis IS NOT NULL THEN 0
                    WHEN enctr.toecode IN ('ER', 'ERADM') AND er.erdtedis IS NOT NULL THEN 0
                    WHEN enctr.toecode = 'ADM' AND adm.disdate IS NOT NULL THEN 0
                    WHEN enctr.encstat = 'A' THEN 1
                    ELSE 0
                END AS is_active,
                diag.diagtext AS primary_diagnosis,
                diag.diagcode AS diagnosis_code,
                emp.lastname + ', ' + emp.firstname AS attending_doctor,
                opd.opddate AS opd_date,
                opd.opddtedis AS opd_discharge_date,
                er.erdate AS er_date,
                er.erdtedis AS er_discharge_date,
                adm.admdate AS admission_date,
                adm.disdate AS discharge_date,
                CASE
                    WHEN enctr.toecode IN ('OPD', 'OPDAD') AND opd.opddtedis IS NOT NULL THEN 'Discharged'
                    WHEN enctr.toecode IN ('ER', 'ERADM') AND er.erdtedis IS NOT NULL THEN 'Discharged'
                    WHEN enctr.toecode = 'ADM' AND adm.disdate IS NOT NULL THEN 'Discharged'
                    WHEN enctr.encstat = 'A' THEN 'Active'
                    ELSE 'Closed'
                END AS encounter_status,
                (SELECT COUNT(*)
                 FROM webapp.dbo.prescription rx WITH (NOLOCK)
                 WHERE rx.enccode = enctr.enccode) AS prescription_count
            FROM hospital.dbo.henctr enctr WITH (NOLOCK)
            LEFT JOIN hospital.dbo.hencdiag diag WITH (NOLOCK)
                ON enctr.enccode = diag.enccode
            LEFT JOIN hospital.dbo.hopdlog opd WITH (NOLOCK)
                ON enctr.enccode = opd.enccode
            LEFT JOIN hospital.dbo.herlog er WITH (NOLOCK)
                ON enctr.enccode = er.enccode
            LEFT JOIN hospital.dbo.hadmlog adm WITH (NOLOCK)
                ON enctr.enccode = adm.enccode
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON enctr.sopcode1 = emp.employeeid
            WHERE enctr.hpercode = ?
                AND enctr.toecode != 'WALKN'
            ORDER BY enctr.encdate DESC
        ", [$hpercode]);

        return response()->json($encounters);
    }

    /**
     * Get details of a specific encounter including all diagnoses.
     */
    public function encounterDetails(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $enccode = $request->query('enccode');

        if (!$enccode) {
            return response()->json(['message' => 'Encounter code is required.'], 400);
        }

        // Get the encounter with details
        $encounter = DB::connection('hospital')->selectOne("
            SELECT
                enctr.enccode,
                enctr.hpercode,
                enctr.encdate,
                enctr.enctime,
                enctr.toecode,
                enctr.encstat,
                CASE
                    WHEN enctr.toecode = 'OPD' THEN 'Out-Patient'
                    WHEN enctr.toecode = 'ER' THEN 'Emergency Room'
                    WHEN enctr.toecode = 'ERADM' THEN 'ER to Admission'
                    WHEN enctr.toecode = 'ADM' THEN 'Admission'
                    WHEN enctr.toecode = 'OPDAD' THEN 'OPD to Admission'
                    WHEN enctr.toecode = 'WALKN' THEN 'Walk-In'
                    ELSE enctr.toecode
                END AS encounter_type_label,
                CASE
                    WHEN enctr.toecode IN ('OPD', 'OPDAD') AND opd.opddtedis IS NOT NULL THEN 0
                    WHEN enctr.toecode IN ('ER', 'ERADM') AND er.erdtedis IS NOT NULL THEN 0
                    WHEN enctr.toecode = 'ADM' AND adm.disdate IS NOT NULL THEN 0
                    WHEN enctr.encstat = 'A' THEN 1
                    ELSE 0
                END AS is_active,
                emp.lastname + ', ' + emp.firstname AS attending_doctor,
                opd.opddate AS opd_date,
                opd.opddtedis AS opd_discharge_date,
                er.erdate AS er_date,
                er.erdtedis AS er_discharge_date,
                adm.admdate AS admission_date,
                adm.disdate AS discharge_date,
                CASE
                    WHEN enctr.toecode IN ('OPD', 'OPDAD') AND opd.opddtedis IS NOT NULL THEN 'Discharged'
                    WHEN enctr.toecode IN ('ER', 'ERADM') AND er.erdtedis IS NOT NULL THEN 'Discharged'
                    WHEN enctr.toecode = 'ADM' AND adm.disdate IS NOT NULL THEN 'Discharged'
                    WHEN enctr.encstat = 'A' THEN 'Active'
                    ELSE 'Closed'
                END AS encounter_status
            FROM hospital.dbo.henctr enctr WITH (NOLOCK)
            LEFT JOIN hospital.dbo.hopdlog opd WITH (NOLOCK)
                ON enctr.enccode = opd.enccode
            LEFT JOIN hospital.dbo.herlog er WITH (NOLOCK)
                ON enctr.enccode = er.enccode
            LEFT JOIN hospital.dbo.hadmlog adm WITH (NOLOCK)
                ON enctr.enccode = adm.enccode
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON enctr.sopcode1 = emp.employeeid
            WHERE enctr.enccode = ? AND enctr.hpercode = ?
        ", [$enccode, $hpercode]);

        if (!$encounter) {
            return response()->json(['message' => 'Encounter not found.'], 404);
        }

        // Get all diagnoses for the encounter
        $diagnoses = DB::connection('hospital')->select("
            SELECT
                diagcode,
                diagtext,
                diagdate
            FROM hospital.dbo.hencdiag WITH (NOLOCK)
            WHERE enccode = ?
            ORDER BY diagdate ASC
        ", [$enccode]);

        $processedDiagnoses = collect($diagnoses)->map(function ($diag) {
            return [
                'diagnosis_code' => (string) ($diag->diagcode ?? ''),
                'diagnosis_text' => (string) ($diag->diagtext ?? 'N/A'),
                'diagnosis_date' => $diag->diagdate,
            ];
        });

        // Get prescriptions for this encounter
        $prescriptions = DB::connection('hospital')->select("
            SELECT
                rx.id,
                rx.created_at,
                emp.lastname + ', ' + emp.firstname AS doctor_name,
                (SELECT COUNT(*)
                 FROM webapp.dbo.prescription_data pd WITH (NOLOCK)
                 WHERE pd.presc_id = rx.id) AS item_count
            FROM webapp.dbo.prescription rx WITH (NOLOCK)
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON rx.empid = emp.employeeid
            WHERE rx.enccode = ?
            ORDER BY rx.created_at DESC
        ", [$enccode]);

        return response()->json([
            'encounter' => $encounter,
            'diagnoses' => $processedDiagnoses,
            'prescriptions' => $prescriptions,
        ]);
    }
}
