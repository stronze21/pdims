<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use App\Models\Record\Prescriptions\PrescriptionData;
use App\Models\Record\Prescriptions\PrescriptionDataIssued;
use App\Services\Pharmacy\PrescriptionReactivationService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Prescription Balances')]
class PrescriptionBalances extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $connection = DB::connection('webapp');
        $issued = $connection->table((new PrescriptionDataIssued)->getTable())
            ->select('presc_data_id')
            ->selectRaw('SUM(qtyissued) AS total_issued')
            ->groupBy('presc_data_id');

        $ordered = 'TRY_CONVERT(decimal(19, 6), pd.qty)';
        $totalIssued = 'COALESCE(issued.total_issued, 0)';

        $prescriptions = $connection->table((new PrescriptionData)->getTable().' as pd')
            ->join('webapp.dbo.prescription as rx', 'rx.id', '=', 'pd.presc_id')
            ->leftJoin('hospital.dbo.henctr as encounter', 'encounter.enccode', '=', 'rx.enccode')
            ->leftJoin('hospital.dbo.hperson as patient', 'patient.hpercode', '=', 'encounter.hpercode')
            ->leftJoin('hospital.dbo.hdmhdr as drug', function ($join) {
                $join->on('drug.dmdcomb', '=', 'pd.dmdcomb')
                    ->on('drug.dmdctr', '=', 'pd.dmdctr');
            })
            ->leftJoinSub($issued, 'issued', 'issued.presc_data_id', '=', 'pd.id')
            ->select([
                'pd.id', 'pd.presc_id', 'pd.stat', 'pd.created_at', 'pd.dmdcomb', 'pd.dmdctr',
                'pd.entry_source', 'pd.qty_per_intake', 'pd.remark', 'pd.frequency', 'pd.duration', 'pd.addtl_remarks',
                'rx.enccode', 'encounter.hpercode', 'encounter.toecode',
                'patient.patfirst', 'patient.patlast', 'drug.drug_concat',
            ])
            ->selectRaw("$ordered AS ordered_qty, $totalIssued AS issued_qty, $ordered - $totalIssued AS balance")
            ->whereIn('pd.stat', ['A', 'I'])
            ->whereRaw("$ordered > $totalIssued")
            ->when(in_array($this->status, ['A', 'I'], true), fn ($query) => $query->where('pd.stat', $this->status))
            ->when(trim($this->search) !== '', function ($query) {
                $search = '%'.trim($this->search).'%';

                $query->where(function ($query) use ($search) {
                    $query->where('encounter.hpercode', 'like', $search)
                        ->orWhere('rx.enccode', 'like', $search)
                        ->orWhere('patient.patfirst', 'like', $search)
                        ->orWhere('patient.patlast', 'like', $search)
                        ->orWhere('drug.drug_concat', 'like', $search);
                });
            })
            ->orderByDesc('pd.created_at')
            ->orderByDesc('pd.id')
            ->simplePaginate(25);

        $schedules = app(PrescriptionReactivationService::class);
        $prescriptions->through(function ($item) use ($schedules) {
            $item->instruction_days = $schedules->durationDays($item);
            $item->instruction_qty = $this->quantityPerIntake($item, $schedules);

            return $item;
        });

        return view('livewire.pharmacy.prescriptions.prescription-balances', compact('prescriptions'));
    }

    private function quantityPerIntake(object $item, PrescriptionReactivationService $schedules): ?float
    {
        $source = strtoupper(trim((string) $item->entry_source));

        if ($source === 'EMR2') {
            return is_numeric($item->qty_per_intake) && $item->qty_per_intake > 0
                ? (float) $item->qty_per_intake
                : null;
        }

        if ($source !== '' || $item->instruction_days === null
            || preg_match('/\b(?:prn|as\s+needed)\b/i', (string) $item->remark)) {
            return null;
        }

        $dosesPerDay = $schedules->administrationsPerDay($item->remark);

        return $dosesPerDay > 0
            ? (float) $item->ordered_qty / ($dosesPerDay * $item->instruction_days)
            : null;
    }
}
