<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

#[Layout('layouts.app')]
#[Title('ER Prescriptions')]
class PrescriptionEr extends Component
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
        $from = Carbon::parse($this->filter_date)->subDay()->startOfDay();
        $to = Carbon::parse($this->filter_date)->endOfDay();

        $prescriptions = DB::select("
            SELECT
                enctr.enccode,
                er.erdate,
                er.ertime,
                enctr.hpercode,
                pt.patfirst,
                pt.patmiddle,
                pt.patlast,
                pt.patsuffix,
                ser.tsdesc,
                emp.lastname,
                emp.firstname,
                emp.empprefix,
                emp.middlename,
                mss.mssikey,
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
                LEFT JOIN webapp.dbo.prescription rx WITH (NOLOCK) ON enctr.enccode = rx.enccode
                LEFT JOIN hospital.dbo.herlog er WITH (NOLOCK) ON enctr.enccode = er.enccode
                LEFT JOIN hospital.dbo.hperson pt WITH (NOLOCK) ON enctr.hpercode = pt.hpercode
                LEFT JOIN hospital.dbo.htypser ser WITH (NOLOCK) ON er.tscode = ser.tscode
                LEFT JOIN hospital.dbo.hprovider prov WITH (NOLOCK) ON er.licno = prov.licno
                LEFT JOIN hospital.dbo.hpersonal emp WITH (NOLOCK) ON prov.employeeid = emp.employeeid
                LEFT JOIN hospital.dbo.hpatmss mss WITH (NOLOCK) ON enctr.enccode = mss.enccode
            WHERE erdate BETWEEN ? AND ?
                AND toecode = 'ER'
                AND erstat = 'A'
            ORDER BY pt.patlast ASC, pt.patfirst ASC, pt.patmiddle ASC
        ", [$from, $to]);

        return view('livewire.pharmacy.prescriptions.prescription-er', [
            'prescriptions' => $prescriptions,
        ]);
    }

    public function viewEncounter($enccode)
    {
        $enccode = Crypt::encrypt(str_replace(' ', '--', $enccode));
        return redirect()->route('dispensing.view.enctr', ['enccode' => $enccode]);
    }
}
