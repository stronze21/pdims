<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalBillingController extends Controller
{
    /**
     * Get OR/payment records for a diagnostic charge slip via docointkey.
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

        if ($docointkey === '') {
            return response()->json(['message' => 'docointkey is required.'], 400);
        }

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
}
