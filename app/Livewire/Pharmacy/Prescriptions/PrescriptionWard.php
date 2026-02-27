<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Hospital\Ward;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

#[Layout('layouts.app')]
#[Title('Ward Prescriptions')]
class PrescriptionWard extends Component
{
    public $wardcode = '';
    public $wards = [];
    public $search = '';
    public $rx_tag_filter = 'all';  // Single filter: 'all', 'basic', 'g24', 'or'

    public function mount()
    {
        $this->wards = Ward::where('wardstat', 'A')
            ->orderBy('wardname')
            ->get();
    }

    public function setRxTagFilter($filter)
    {
        $this->rx_tag_filter = $filter;
    }

    public function render()
    {
        $wardFilter = $this->wardcode ? "AND ward.wardcode = ?" : "";
        $params = $this->wardcode ? [$this->wardcode] : [];

        $prescriptions = DB::select("
            SELECT
                enctr.enccode,
                adm.admdate,
                enctr.hpercode,
                pt.patfirst,
                pt.patmiddle,
                pt.patlast,
                pt.patsuffix,
                room.rmname,
                ward.wardname,
                ward.wardcode,
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
            FROM hospital2.dbo.henctr enctr WITH (NOLOCK)
                RIGHT JOIN webapp.dbo.prescription rx WITH (NOLOCK) ON enctr.enccode = rx.enccode
                LEFT JOIN hospital2.dbo.hadmlog adm WITH (NOLOCK) ON enctr.enccode = adm.enccode
                RIGHT JOIN hospital2.dbo.hpatroom pat_room WITH (NOLOCK) ON rx.enccode = pat_room.enccode
                RIGHT JOIN hospital2.dbo.hroom room WITH (NOLOCK) ON pat_room.rmintkey = room.rmintkey
                RIGHT JOIN hospital2.dbo.hward ward WITH (NOLOCK) ON pat_room.wardcode = ward.wardcode
                RIGHT JOIN hospital2.dbo.hperson pt WITH (NOLOCK) ON enctr.hpercode = pt.hpercode
                LEFT JOIN hospital2.dbo.hpatmss mss WITH (NOLOCK) ON enctr.enccode = mss.enccode
            WHERE (toecode = 'ADM' OR toecode = 'OPDAD' OR toecode = 'ERADM')
                AND pat_room.patrmstat = 'A'
                AND rx.stat = 'A'
                {$wardFilter}
            ORDER BY pt.patlast ASC, pt.patfirst ASC, pt.patmiddle ASC, rx.created_at DESC
        ", $params);

        return view('livewire.pharmacy.prescriptions.prescription-ward', [
            'prescriptions' => $prescriptions,
        ]);
    }

    public function viewEncounter($enccode)
    {
        $enccode = Crypt::encrypt(str_replace(' ', '--', $enccode));
        return redirect()->route('dispensing.view.enctr', ['enccode' => $enccode]);
    }
}
