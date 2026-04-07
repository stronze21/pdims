<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalBillingController extends Controller
{
    /**
     * Get pharmacy charge slips from hrxo for the authenticated patient.
     */
    public function pharmacyChargeSlips(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $chargeSlips = DB::connection('hospital')->select("
            SELECT
                hrxo.docointkey,
                hrxo.enccode,
                hrxo.hpercode,
                hrxo.pcchrgcod AS charge_slip_code,
                hrxo.dodate AS order_date,
                hrxo.dotime AS order_time,
                hrxo.pchrgqty AS quantity,
                hrxo.pchrgup AS unit_price,
                hrxo.pcchrgamt AS charge_amount,
                hdmhdr.drug_concat AS item_name,
                hcharge.chrgdesc AS charge_desc,
                'PHARM' AS department,
                'Pharmacy' AS department_name,
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
                emp.lastname + ', ' + emp.firstname AS ordered_by,
                hrxo.estatus
            FROM hospital.dbo.hrxo hrxo WITH (NOLOCK)
            INNER JOIN hospital.dbo.hdmhdr hdmhdr WITH (NOLOCK)
                ON hdmhdr.dmdcomb = hrxo.dmdcomb
                AND hdmhdr.dmdctr = hrxo.dmdctr
            INNER JOIN hospital.dbo.hcharge hcharge WITH (NOLOCK)
                ON hcharge.chrgcode = hrxo.orderfrom
            LEFT JOIN hospital.dbo.henctr enctr WITH (NOLOCK)
                ON enctr.enccode = hrxo.enccode
            LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK)
                ON emp.employeeid = hrxo.entryby
            WHERE hrxo.hpercode = ?
                AND hrxo.pcchrgcod IS NOT NULL
                AND LTRIM(RTRIM(hrxo.pcchrgcod)) <> ''
                AND hrxo.estatus IS NOT NULL
                AND hrxo.estatus <> 'C'
            ORDER BY hrxo.dodate DESC, hrxo.dotime DESC
        ", [$hpercode]);

        return response()->json($chargeSlips);
    }

    /**
     * Get OR/payment records for a diagnostic or pharmacy charge slip.
     */
    public function payments(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $hpercode = $account->patient?->hpercode;

        if (!$hpercode) {
            return response()->json(['message' => 'No linked hospital record found.'], 404);
        }

        $docointkey = trim((string) $request->query('docointkey', ''));
        $pcchrgcod = trim((string) $request->query('pcchrgcod', ''));

        if ($docointkey === '' && $pcchrgcod === '') {
            return response()->json(['message' => 'docointkey or pcchrgcod is required.'], 400);
        }

        if ($docointkey !== '') {
            $ownedOrder = DB::connection('hospital')->selectOne("
                SELECT TOP 1 hdocord.docointkey
                FROM hospital.dbo.hdocord hdocord WITH (NOLOCK)
                WHERE hdocord.docointkey = ?
                  AND hdocord.hpercode = ?
            ", [$docointkey, $hpercode]);

            if (!$ownedOrder) {
                return response()->json(['message' => 'Charge slip not found.'], 404);
            }

            $payments = DB::connection('hospital')->select("
                SELECT
                    hpay.orno AS or_no,
                    hpay.ordate AS or_date,
                    hpay.amt AS amount,
                    hpay.bal AS balance,
                    hpay.paystat AS pay_stat,
                    hpay.cashier AS cashier,
                    hpay.docointkey AS docointkey
                FROM hospital.dbo.hpay hpay WITH (NOLOCK)
                WHERE hpay.docointkey = ?
                ORDER BY hpay.ordate DESC, hpay.orno DESC
            ", [$docointkey]);

            return response()->json($payments);
        }

        $ownedPharmacySlip = DB::connection('hospital')->selectOne("
            SELECT TOP 1 hrxo.pcchrgcod
            FROM hospital.dbo.hrxo hrxo WITH (NOLOCK)
            WHERE hrxo.pcchrgcod = ?
              AND hrxo.hpercode = ?
        ", [$pcchrgcod, $hpercode]);

        if (!$ownedPharmacySlip) {
            return response()->json(['message' => 'Charge slip not found.'], 404);
        }

        $payments = DB::connection('hospital')->select("
            SELECT
                hpay.orno AS or_no,
                hpay.ordate AS or_date,
                hpay.amt AS amount,
                hpay.bal AS balance,
                hpay.paystat AS pay_stat,
                hpay.cashier AS cashier,
                hpay.docointkey AS docointkey
            FROM hospital.dbo.hpay hpay WITH (NOLOCK)
            INNER JOIN hospital.dbo.hrxo hrxo WITH (NOLOCK)
                ON hrxo.docointkey = hpay.docointkey
            WHERE hrxo.pcchrgcod = ?
              AND hrxo.hpercode = ?
            ORDER BY hpay.ordate DESC, hpay.orno DESC
        ", [$pcchrgcod, $hpercode]);

        return response()->json($payments);
    }
}
