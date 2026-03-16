<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalLabResultController extends Controller
{
    /**
     * Get all laboratory orders/results for the authenticated patient.
     * Queries hdocord joined with hprocm (costcenter='LABOR') to retrieve lab orders.
     */
    public function labResults(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $results = DB::connection('hospital')->select("
            SELECT
                hdocord.docointkey,
                hdocord.enccode,
                hdocord.hpercode,
                hprocm.proccode AS lab_code,
                hprocm.procdesc AS lab_name,
                hdocord.dodate AS order_date,
                hdocord.dotime AS order_time,
                hdocord.dodtepost AS result_date,
                hdocord.dotmepost AS result_time,
                hdocord.estatus,
                hdocord.dopriority,
                hdocord.pcchrgcod AS charge_slip_code,
                hdocord.pcchrgamt AS charge_amount,
                hdocord.pchrgqty AS quantity,
                hdocord.pchrgup AS unit_price,
                hdocord.speccode,
                hspec.description AS specimen_name,
                prov.lastname + ', ' + prov.firstname AS ordered_by,
                CASE
                    WHEN hdocord.estatus = 'S' THEN 'Released'
                    WHEN hdocord.estatus = 'P' THEN 'Processing'
                    WHEN hdocord.estatus = 'C' THEN 'Cancelled'
                    ELSE 'Pending'
                END AS status,
                CASE
                    WHEN hdocord.dopriority = 'STAT' THEN 'STAT'
                    WHEN hdocord.dopriority = 'ROUTIN' THEN 'Routine'
                    ELSE ISNULL(hdocord.dopriority, 'Routine')
                END AS priority_label,
                enctr.toecode,
                enctr.encdate AS encounter_date,
                CASE
                    WHEN enctr.toecode = 'OPD' THEN 'Out-Patient'
                    WHEN enctr.toecode = 'ER' THEN 'Emergency Room'
                    WHEN enctr.toecode = 'ADM' THEN 'Admission'
                    WHEN enctr.toecode = 'ERADM' THEN 'ER to Admission'
                    WHEN enctr.toecode = 'OPDAD' THEN 'OPD to Admission'
                    WHEN enctr.toecode = 'WALKN' THEN 'Walk-In'
                    ELSE enctr.toecode
                END AS encounter_type
            FROM hospital.dbo.hdocord hdocord WITH (NOLOCK)
            JOIN hospital.dbo.hprocm hprocm WITH (NOLOCK)
                ON hprocm.proccode = hdocord.proccode
                AND hprocm.costcenter = 'LABOR'
            LEFT JOIN hospital.dbo.hspec hspec WITH (NOLOCK)
                ON hspec.speccode = hdocord.speccode
            LEFT JOIN hospital.dbo.hprovider hprov WITH (NOLOCK)
                ON hdocord.licno = hprov.licno
            LEFT JOIN hospital.dbo.hpersonal prov WITH (NOLOCK)
                ON hprov.employeeid = prov.employeeid
            LEFT JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON hdocord.enccode = enctr.enccode
            WHERE hdocord.hpercode = ?
                AND hdocord.dostat = 'A'
                AND (hdocord.estatus IS NULL OR hdocord.estatus <> 'C')
            ORDER BY hdocord.dodate DESC, hdocord.dotime DESC
        ", [$hpercode]);

        return response()->json($results);
    }

    /**
     * Get laboratory orders/results for a specific encounter.
     */
    public function encounterLabResults(Request $request)
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

        $results = DB::connection('hospital')->select("
            SELECT
                hdocord.docointkey,
                hprocm.proccode AS lab_code,
                hprocm.procdesc AS lab_name,
                hdocord.dodate AS order_date,
                hdocord.dotime AS order_time,
                hdocord.dodtepost AS result_date,
                hdocord.dotmepost AS result_time,
                hdocord.estatus,
                hdocord.dopriority,
                hdocord.pcchrgcod AS charge_slip_code,
                hdocord.pcchrgamt AS charge_amount,
                hdocord.speccode,
                hspec.description AS specimen_name,
                prov.lastname + ', ' + prov.firstname AS ordered_by,
                CASE
                    WHEN hdocord.estatus = 'S' THEN 'Released'
                    WHEN hdocord.estatus = 'P' THEN 'Processing'
                    WHEN hdocord.estatus = 'C' THEN 'Cancelled'
                    ELSE 'Pending'
                END AS status,
                CASE
                    WHEN hdocord.dopriority = 'STAT' THEN 'STAT'
                    WHEN hdocord.dopriority = 'ROUTIN' THEN 'Routine'
                    ELSE ISNULL(hdocord.dopriority, 'Routine')
                END AS priority_label
            FROM hospital.dbo.hdocord hdocord WITH (NOLOCK)
            JOIN hospital.dbo.hprocm hprocm WITH (NOLOCK)
                ON hprocm.proccode = hdocord.proccode
                AND hprocm.costcenter = 'LABOR'
            LEFT JOIN hospital.dbo.hspec hspec WITH (NOLOCK)
                ON hspec.speccode = hdocord.speccode
            LEFT JOIN hospital.dbo.hprovider hprov WITH (NOLOCK)
                ON hdocord.licno = hprov.licno
            LEFT JOIN hospital.dbo.hpersonal prov WITH (NOLOCK)
                ON hprov.employeeid = prov.employeeid
            WHERE hdocord.enccode = ?
                AND hdocord.hpercode = ?
                AND hdocord.dostat = 'A'
                AND (hdocord.estatus IS NULL OR hdocord.estatus <> 'C')
            ORDER BY hdocord.dodate DESC, hdocord.dotime DESC
        ", [$enccode, $hpercode]);

        return response()->json($results);
    }
}
