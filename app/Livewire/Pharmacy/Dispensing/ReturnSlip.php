<?php

namespace App\Livewire\Pharmacy\Dispensing;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ReturnSlip extends Component
{
    public $patient;
    public $hpercode;

    public function mount($hpercode)
    {
        $this->hpercode = $hpercode;
        $this->patient = collect(DB::select("SELECT * FROM hperson WHERE hpercode = ?", [$hpercode]))->first();
    }

    public function render()
    {
        $items = DB::select("
            SELECT rxo.pcchrgcod, rxi.issuedte, drug.drug_concat, rxi.qty total_issued,
                   rxr.qty as total_returns, rxo.pchrgup, (rxo.pchrgup * rxi.qty) pchrgamt
            FROM hrxo rxo
            LEFT JOIN hrxoissue rxi ON rxo.docointkey = rxi.docointkey
            INNER JOIN hrxoreturn rxr ON rxo.docointkey = rxr.docointkey
            JOIN hdmhdr drug ON rxo.dmdcomb = drug.dmdcomb AND rxo.dmdctr = drug.dmdctr
            WHERE rxo.hpercode = ?
            ORDER BY rxo.dodate DESC
        ", [$this->hpercode]);

        return view('livewire.pharmacy.dispensing.return-slip', compact('items'))
            ->layout('layouts.print');
    }
}
