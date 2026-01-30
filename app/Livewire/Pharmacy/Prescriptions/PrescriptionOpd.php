<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

#[Layout('layouts.app')]
#[Title('OPD Prescriptions')]
class PrescriptionOpd extends Component
{
    public $filter_date;
    public $search = '';
    public $rx_tag_filter = 'all';  // Single filter: 'all', 'basic', 'g24', 'or'

    public function mount()
    {
        $this->filter_date = date('Y-m-d');
    }

    public function setRxTagFilter($filter)
    {
        $this->rx_tag_filter = $filter;
    }

    public function render()
    {
        $from = Carbon::parse($this->filter_date)->startOfDay();
        $to = Carbon::parse($this->filter_date)->endOfDay();

        $prescriptions = DB::select("
            SELECT
                enctr.enccode,
                opd.opddate,
                opd.opdtime,
                enctr.hpercode,
                pt.patfirst,
                pt.patmiddle,
                pt.patlast,
                pt.patsuffix,
                mss.mssikey,
                ser.tsdesc,
                (SELECT COUNT(qty)
                 FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                 WHERE rx.id = data.presc_id
                 AND data.stat = 'A'
                 AND (data.order_type = '' OR data.order_type IS NULL)) AS basic,
                (SELECT COUNT(qty)
                 FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                 WHERE rx.id = data.presc_id
                 AND data.stat = 'A'
                 AND data.order_type = 'G24') AS g24,
                (SELECT COUNT(qty)
                 FROM webapp.dbo.prescription_data data WITH (NOLOCK)
                 WHERE rx.id = data.presc_id
                 AND data.stat = 'A'
                 AND data.order_type = 'OR') AS 'or'
            FROM hospital.dbo.henctr enctr WITH (NOLOCK)
                RIGHT JOIN webapp.dbo.prescription rx WITH (NOLOCK) ON enctr.enccode = rx.enccode
                LEFT JOIN hospital.dbo.hopdlog opd WITH (NOLOCK) ON enctr.enccode = opd.enccode
                RIGHT JOIN hospital.dbo.hperson pt WITH (NOLOCK) ON enctr.hpercode = pt.hpercode
                LEFT JOIN hospital.dbo.hpatmss mss WITH (NOLOCK) ON enctr.enccode = mss.enccode
                LEFT JOIN hospital.dbo.htypser ser WITH (NOLOCK) ON opd.tscode = ser.tscode
            WHERE opdtime BETWEEN ? AND ?
                AND toecode = 'OPD'
                AND rx.stat = 'A'
            ORDER BY pt.patlast ASC, pt.patfirst ASC, pt.patmiddle ASC, rx.created_at DESC
        ", [$from, $to]);

        return view('livewire.pharmacy.prescriptions.prescription-opd', [
            'prescriptions' => $prescriptions,
        ]);
    }

    public function viewEncounter($enccode)
    {
        $enccode = Crypt::encrypt(str_replace(' ', '--', $enccode));
        return redirect()->route('dispensing.view.enctr', ['enccode' => $enccode]);
    }
}
