<?php

namespace App\Http\Controllers;

use App\Livewire\Pharmacy\Training\OpdWalkthrough;
use Illuminate\Http\Request;

class OpdTrainingPrintController
{
    public function __invoke(Request $request)
    {
        $remaining = $request->boolean('remaining', true);
        $patient = (object) [
            'patlast' => 'TRAINING',
            'patfirst' => 'Alex',
            'patmiddle' => '',
            'patsuffix' => '',
            'patsex' => 'F',
            'patbdate' => now()->subYears(35),
            'primaryAddress' => null,
        ];
        $items = collect([(object) [
            'drug_concat' => OpdWalkthrough::MEDICATION,
            'print_qty' => $remaining ? 0 : 1,
            'deduct_issued' => $remaining,
            'qty_issued' => 1,
            'order_type' => 'Basic',
            'qty_per_administration' => 1,
            'schedule_text' => 'Once daily',
            'days_to_cover' => 1,
            'addtl_remarks' => OpdWalkthrough::REMARKS,
        ]]);

        return view('pharmacy.dispensing.print-prescription', [
            'patient' => $patient,
            'items' => $items,
            'wardRoom' => 'OPD',
            'opdTraining' => true,
            'prescriptionSource' => OpdWalkthrough::resolvePrescriptionSource($request),
        ]);
    }
}
