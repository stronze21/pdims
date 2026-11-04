<?php

namespace App\Livewire\Pharmacy;

use App\Services\Pharmacy\DrugClinicalService;
use App\Services\Pharmacy\DrugEquivalenceService;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class DrugClinicalPanel extends Component
{
    #[Reactive]
    public ?string $comb = null;

    #[Reactive]
    public ?string $ctr = null;

    #[Reactive]
    public ?string $location = null;

    #[Reactive]
    public ?array $check = null;

    public function render()
    {
        abort_unless(auth()->user()?->can('view-drug-clinical'), 403);
        $details = null;
        $equivalents = collect();
        $failed = false;
        $names = collect();
        try {
            if ($this->comb !== null && $this->comb !== ''
                && $this->ctr !== null && $this->ctr !== '') {
                $details = app(DrugClinicalService::class)->details($this->comb, $this->ctr, $this->location ?: null);
                $names = \Illuminate\Support\Facades\DB::connection('hospital')->table('pharm_clinical_ingredients')->pluck('name', 'id');
                $service = app(DrugEquivalenceService::class);
                $group = $service->groupFor($this->comb, $this->ctr);
                if ($group) {
                    $equivalents = $this->location ? $service->substitutionCandidates($this->comb, $this->ctr, $this->location) : $service->members($group->id);
                    $equivalents = $equivalents->reject(fn ($r) => $r->dmdcomb === $this->comb && (string) $r->dmdctr === $this->ctr);
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $failed = true;
        }

        return view('livewire.pharmacy.drug-clinical-panel', compact('details', 'equivalents', 'failed', 'names'));
    }
}
