<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use App\Models\Record\Prescriptions\PrescriptionData;
use App\Services\Pharmacy\PrescriptionReactivationService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Layout('layouts.app')]
#[Title('Reordered Today')]
class PrescriptionReactivatedToday extends Component
{
    use Toast, WithPagination;

    public string $search = '';
    public string $reference_date = '';
    public string $issued_filter = 'all';
    public string $balance_filter = 'all';
    public int $perPage = 15;

    protected PrescriptionReactivationService $reactivationService;

    public function boot(PrescriptionReactivationService $reactivationService): void
    {
        $this->reactivationService = $reactivationService;
    }

    public function mount(): void
    {
        $this->reference_date = now('Asia/Manila')->toDateString();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingReferenceDate(): void
    {
        $this->resetPage();
    }

    public function updatingIssuedFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBalanceFilter(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function togglePrescriptionStatus(int $id): void
    {
        $item = PrescriptionData::query()->find($id);

        if (!$item) {
            $this->error('Prescription item not found.');
            return;
        }

        if ((int) ($item->archive ?? 0) === 1) {
            $this->warning('Discontinued prescription items cannot be toggled.');
            return;
        }

        if (!$this->reactivationService->hasActiveEncounter((int) $item->id)) {
            $this->warning('Only prescription items from active admitted encounters can be reordered.');
            return;
        }

        $item->stat = $item->stat === 'A' ? 'I' : 'A';
        if ($item->stat === 'A') {
            $reorderedAt = now('Asia/Manila');
            $item->active_date = $reorderedAt;
        } else {
            $reorderedAt = null;
        }
        $item->updated_at = now('Asia/Manila');
        $item->save();

        if ($item->stat === 'A' && $reorderedAt) {
            $this->reactivationService->logReorder(
                (int) $item->id,
                'manual',
                $reorderedAt,
                'Manual reorder from Rx/Orders page',
                auth()->user()?->name ?? (string) auth()->id()
            );
        }

        $this->success($item->stat === 'A'
            ? 'Prescription item reordered manually.'
            : "Prescription item status updated to {$item->stat}.");
    }

    public function discontinuePrescription(int $id): void
    {
        $item = PrescriptionData::query()->find($id);

        if (!$item) {
            $this->error('Prescription item not found.');
            return;
        }

        if ((int) ($item->archive ?? 0) === 1) {
            $this->warning('Prescription item is already marked as discontinued.');
            return;
        }

        $item->archive = 1;
        $item->stat = 'I';
        $item->updated_at = now('Asia/Manila');
        $item->save();

        $this->warning('Prescription item is now marked as discontinued.');
    }

    public function render()
    {
        $referenceDate = Carbon::parse($this->reference_date, 'Asia/Manila');

        $items = $this->reactivationService
            ->reactivatedToday($referenceDate)
            ->map(fn (array $item) => (object) $item)
            ->values();

        $search = trim(mb_strtolower($this->search));
        if ($search !== '') {
            $items = $items->filter(function (object $item) use ($search) {
                foreach ([
                    $item->patient_name ?? '',
                    $item->hpercode ?? '',
                    $item->drug_concat ?? '',
                    $item->dmdcomb ?? '',
                    $item->encounter_type_label ?? '',
                ] as $value) {
                    if (str_contains(mb_strtolower((string) $value), $search)) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        if ($this->issued_filter === 'with_issued') {
            $items = $items->filter(fn (object $item) => (float) ($item->qty_issued ?? 0) > 0)->values();
        } elseif ($this->issued_filter === 'without_issued') {
            $items = $items->filter(fn (object $item) => (float) ($item->qty_issued ?? 0) <= 0)->values();
        }

        if ($this->balance_filter === 'with_remaining') {
            $items = $items->filter(fn (object $item) => (float) ($item->computed_remaining_qty ?? 0) > 0)->values();
        } elseif ($this->balance_filter === 'fully_issued') {
            $items = $items->filter(fn (object $item) => (float) ($item->computed_remaining_qty ?? 0) <= 0)->values();
        }

        $page = $this->getPage();
        $paginated = new LengthAwarePaginator(
            $items->forPage($page, $this->perPage)->values(),
            $items->count(),
            $this->perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page']
        );

        return view('livewire.pharmacy.prescriptions.prescription-reactivated-today', [
            'items' => $paginated,
        ]);
    }
}
