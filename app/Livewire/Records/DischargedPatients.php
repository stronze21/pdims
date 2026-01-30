<?php

namespace App\Livewire\Records;

use Carbon\Carbon;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

#[Title('Discharged Patients')]
class DischargedPatients extends Component
{
    public $date_from;
    public $date_to;
    public $search = '';
    public $perPage = 25;
    public $page = 1;

    public function loadMore()
    {
        $this->page++;
    }

    public function mount()
    {
        $this->date_from = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->date_to = Carbon::now()->endOfWeek()->endOfDay()->format('Y-m-d');
    }

    public function updatingSearch()
    {
        $this->page = 1;
    }

    public function updatingDateFrom()
    {
        $this->page = 1;
    }

    public function updatingDateTo()
    {
        $this->page = 1;
    }

    public function render()
    {
        // Convert datetime format for SQL Server with end of day for date_to
        $dateFrom = Carbon::parse($this->date_from)->startOfDay()->format('Y-m-d H:i:s');
        $dateTo = Carbon::parse($this->date_to)->endOfDay()->format('Y-m-d H:i:s');

        $searchTerm = '%' . $this->search . '%';

        // Calculate total records to load (all pages up to current)
        $limit = $this->page * $this->perPage;

        // Get total count
        $totalCount = DB::selectOne("
            SELECT COUNT(*) as total
            FROM henctr enctr WITH (NOLOCK)
                LEFT JOIN hadmlog adm WITH (NOLOCK) ON enctr.enccode = adm.enccode
                RIGHT JOIN hpatroom pat_room WITH (NOLOCK) ON enctr.enccode = pat_room.enccode
                RIGHT JOIN hroom room WITH (NOLOCK) ON pat_room.rmintkey = room.rmintkey
                RIGHT JOIN hward ward WITH (NOLOCK) ON pat_room.wardcode = ward.wardcode
                RIGHT JOIN hperson pt WITH (NOLOCK) ON enctr.hpercode = pt.hpercode
                LEFT JOIN hpatmss mss WITH (NOLOCK) ON enctr.enccode = mss.enccode
                RIGHT JOIN htypser serv WITH (NOLOCK) ON adm.tscode = serv.tscode
            WHERE adm.disdate BETWEEN ? AND ?
                AND (toecode = 'ADM' OR toecode = 'OPDAD' OR toecode = 'ERADM')
                AND adm.disdate IS NOT NULL
                AND pat_room.updsw = 'Y'
                AND (
                    enctr.hpercode LIKE ?
                    OR pt.patlast LIKE ?
                    OR pt.patfirst LIKE ?
                    OR pt.patmiddle LIKE ?
                    OR ward.wardname LIKE ?
                    OR room.rmname LIKE ?
                    OR serv.tsdesc LIKE ?
                )
        ", [$dateFrom, $dateTo, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);

        // Get all records up to current page
        $patients = DB::select("
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
                mss.mssikey,
                serv.tsdesc,
                adm.condcode,
                adm.disdate
            FROM henctr enctr WITH (NOLOCK)
                LEFT JOIN hadmlog adm WITH (NOLOCK) ON enctr.enccode = adm.enccode
                RIGHT JOIN hpatroom pat_room WITH (NOLOCK) ON enctr.enccode = pat_room.enccode
                RIGHT JOIN hroom room WITH (NOLOCK) ON pat_room.rmintkey = room.rmintkey
                RIGHT JOIN hward ward WITH (NOLOCK) ON pat_room.wardcode = ward.wardcode
                RIGHT JOIN hperson pt WITH (NOLOCK) ON enctr.hpercode = pt.hpercode
                LEFT JOIN hpatmss mss WITH (NOLOCK) ON enctr.enccode = mss.enccode
                RIGHT JOIN htypser serv WITH (NOLOCK) ON adm.tscode = serv.tscode
            WHERE adm.disdate BETWEEN ? AND ?
                AND (toecode = 'ADM' OR toecode = 'OPDAD' OR toecode = 'ERADM')
                AND adm.disdate IS NOT NULL
                AND pat_room.updsw = 'Y'
                AND (
                    enctr.hpercode LIKE ?
                    OR pt.patlast LIKE ?
                    OR pt.patfirst LIKE ?
                    OR pt.patmiddle LIKE ?
                    OR ward.wardname LIKE ?
                    OR room.rmname LIKE ?
                    OR serv.tsdesc LIKE ?
                )
            ORDER BY pt.patlast ASC, pt.patfirst ASC, pt.patmiddle ASC
            OFFSET 0 ROWS
            FETCH NEXT ? ROWS ONLY
        ", [$dateFrom, $dateTo, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);

        $hasMore = count($patients) < $totalCount->total;

        return view('livewire.records.discharged-patients', [
            'patients' => $patients,
            'totalCount' => $totalCount->total,
            'hasMore' => $hasMore,
        ]);
    }

    public function viewEncounter($enccode)
    {
        $encrypted = Crypt::encrypt(str_replace(' ', '--', $enccode));
        return $this->redirect(route('dispensing.view.enctr', ['enccode' => $encrypted]), navigate: false);
    }

    public function getConditionStatus($condcode)
    {
        return match ($condcode) {
            'RECOV' => 'Recovered',
            'DIEMI' => '< 48 hours Autopsied',
            'DIENA' => 'Died < 48 hours Not Autopsied',
            'DIEPO' => 'Died > 48 hours Autopsied',
            'DPONA' => 'Died > 48 hours Not Autopsied',
            'IMPRO' => 'Improved',
            'UNIMP' => 'Unimproved',
            default => '---',
        };
    }

    public function getMssClass($mssikey)
    {
        return match ($mssikey) {
            'MSSA11111999', 'MSSB11111999' => 'Pay',
            'MSSC111111999' => 'PP1',
            'MSSC211111999' => 'PP2',
            'MSSC311111999' => 'PP3',
            'MSSD11111999' => 'Indigent',
            default => '---',
        };
    }
}
