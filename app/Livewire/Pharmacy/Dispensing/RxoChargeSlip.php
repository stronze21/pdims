<?php

namespace App\Livewire\Pharmacy\Dispensing;

use Livewire\Component;
use App\Models\Hospital\Room;
use App\Models\Hospital\Ward;
use App\Models\Pharmacy\Dispensing\DrugOrder;
use App\Models\Pharmacy\Dispensing\DrugOrderReturn;
use Illuminate\Support\Facades\DB;

class RxoChargeSlip extends Component
{
    public $pcchrgcod;
    public $view_returns = false;
    public $returned_qty = 0;
    public $wardname, $room_name, $toecode;

    public function mount($pcchrgcod)
    {
        $this->pcchrgcod = $pcchrgcod;
    }

    public function updatedViewReturns()
    {
        $this->reset('returned_qty');
    }

    public function render()
    {
        $rxo = DrugOrder::where('pcchrgcod', $this->pcchrgcod)
            ->with('dm', 'patient', 'prescription_data')
            ->latest('dodate')
            ->get();

        if ($rxo->isEmpty()) {
            abort(404);
        }

        $rxo_header = $rxo->first();
        $this->toecode = $rxo_header->enctr ? $rxo_header->enctr->toecode : '';

        if ($this->view_returns) {
            $this->returned_qty = DrugOrderReturn::where('pcchrgcod', $this->pcchrgcod)->count();
        }

        $patient_room = DB::selectOne("SELECT TOP 1 * FROM hpatroom WHERE enccode = ? ORDER BY hprdate DESC", [$rxo_header->enccode]);

        if ($patient_room) {
            $this->wardname = Ward::select('wardname')->where('wardcode', $patient_room->wardcode)->first();
            $this->room_name = Room::select('rmname')->where('rmintkey', $patient_room->rmintkey)->first();
        }

        return view('livewire.pharmacy.dispensing.rxo-charge-slip', [
            'rxo_header' => $rxo_header,
            'rxo' => $rxo,
        ])->layout('layouts.print');
    }
}
