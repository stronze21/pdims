<?php

namespace App\Livewire\Pharmacy\Dispensing;

use Livewire\Component;
use App\Models\Hospital\Room;
use App\Models\Hospital\Ward;
use App\Models\Admission\PatientRoom;
use App\Models\Record\Encounters\EncounterLog;
use App\Models\Pharmacy\Dispensing\DrugOrder;

class UdddsChargeSlipBatch extends Component
{
    public $codes = [];
    public $patient_numbers = [];

    public function mount()
    {
        $requestedNumbers = explode(',', (string) request('numbers', ''));
        $requestedCodes = array_map('trim', explode(',', (string) request('codes', '')));
        foreach ($requestedCodes as $index => $code) {
            $number = $requestedNumbers[$index] ?? '';
            if ($code !== '' && ctype_digit($number) && (int) $number > 0) $this->patient_numbers[$code] = (int) $number;
        }
        $this->codes = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) request('codes', ''))))));
    }

    public function loadSlip($pcchrgcod): array
    {
        $this->skipRender();
        if (!in_array($pcchrgcod, $this->codes, true)) {
            return ['ok' => false, 'message' => 'Charge slip is not in this batch.'];
        }

        $rxo = DrugOrder::where('pcchrgcod', $pcchrgcod)
            ->with('dm', 'patient', 'prescription_data.employee', 'employee', 'user', 'encounter')
            ->latest('dodate')
            ->get();

        if ($rxo->isEmpty()) {
            return ['ok' => false, 'message' => 'No records found for this charge slip.'];
        }

        $rxo_header = $rxo->first();
        $displayEnccode = $rxo_header->original_enccode ?: $rxo_header->enccode;
        $displayEncounter = EncounterLog::select('enccode', 'toecode')->where('enccode', $displayEnccode)->first();
        $patient_room = PatientRoom::where('enccode', $displayEnccode)->latest('hprdate')->first();

        $slip = [
            'pcchrgcod' => $pcchrgcod,
            'patient_number' => $this->patient_numbers[$pcchrgcod] ?? null,
            'batch_index' => array_search($pcchrgcod, $this->codes, true) + 1,
            'batch_total' => count($this->codes),
            'rxo' => $rxo,
            'rxo_header' => $rxo_header,
            'toecode' => optional($displayEncounter)->toecode ?: optional($rxo_header->encounter)->toecode,
            'encounter_suffix' => $rxo_header->original_enccode ? 'MGH' : null,
            'wardname' => $patient_room ? Ward::select('wardname')->where('wardcode', $patient_room->wardcode)->first() : null,
            'room_name' => $patient_room ? Room::select('rmname')->where('rmintkey', $patient_room->rmintkey)->first() : null,
        ];
        return ['ok' => true, 'html' => view('livewire.pharmacy.dispensing.uddds-charge-slip', ['slip' => $slip])->render()];
    }

    public function render()
    {
        return view('livewire.pharmacy.dispensing.uddds-charge-slip-batch')->layout('layouts.print');
    }
}
