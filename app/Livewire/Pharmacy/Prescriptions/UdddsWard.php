<?php

namespace App\Livewire\Pharmacy\Prescriptions;

use Carbon\Carbon;
use Livewire\Component;
use App\Models\Hospital\Ward;
use Illuminate\Support\Facades\Crypt;
use Mary\Traits\Toast;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Services\Pharmacy\UdddsService;

#[Layout('layouts.app')]
#[Title('UDDDS (Wards)')]
class UdddsWard extends Component
{
    use Toast;

    public $wardcode;
    public $selected_date;
    public $wards = [];
    public $selected_items = [];
    public $selected_print_patients = [];
    public $print_range = '';
    public $printSelectionProblem;
    public $printModalOpen = false;
    public $queueLoaded = false;
    public $queue_view = 'active';
    public $status_filter = 'all';
    public $lastBatchPrintUrl;
    public $processingProblem;
    public $fallback_sources = [];
    public $fundModalOpen = false;

    public function mount()
    {
        $this->selected_date = now('Asia/Manila')->toDateString();
        $this->wards = Ward::where('wardstat', 'A')->orderBy('wardname')->get();
    }

    public function updatedFallbackSources($value, $name)
    {
        $parts = explode('.', $name);
        if (count($parts) === 2 && ctype_digit($parts[1])) {
            $key = $parts[0];
            // A changed earlier choice invalidates the choices that followed it.
            $this->fallback_sources[$key] = array_slice((array) ($this->fallback_sources[$key] ?? []), 0, (int) $parts[1] + 1);
        }
    }

    public function prepareCharge($enccode = null)
    {
        if ($this->queue_view !== 'active') return;
        if ($enccode !== null) {
            $this->selected_items = [];
            foreach ($this->filteredItems() as $item) {
                if ($item->enccode === $enccode && $item->is_actionable) $this->selected_items[] = (string) $item->docointkey;
            }
        }
        $this->reviewFunding(false);
    }

    public function continueCharge()
    {
        $this->reviewFunding(true);
    }

    protected function reviewFunding(bool $requireCoverage)
    {
        $service = app(UdddsService::class);
        $items = $this->filteredItems();
        $allowed = array_map(fn ($item) => (string) $item->docointkey, array_filter($items, fn ($item) => (bool) $item->is_actionable));
        $this->selected_items = array_values(array_intersect($this->selected_items, $allowed));
        if (!$this->selected_items) {
            $this->processingProblem = 'Select pending items first.';
            return;
        }
        $items = $service->annotatePendingStock($items, auth()->user()->pharm_location_id);
        $groups = $service->fundSelectionGroups($items, $this->selected_items);
        $uncovered = $service->uncoveredFundGroups($groups, $this->fallback_sources);
        $this->processingProblem = null;
        if ($uncovered) {
            $this->fundModalOpen = true;
            if ($requireCoverage) $this->processingProblem = 'Selected funds still cannot cover the shortage. Choose another available fund before continuing.';
            return;
        }
        $this->fundModalOpen = false;
        $this->dispatch('uddds-confirm-issue', count: count($this->selected_items));
    }

    public function closeFundModal()
    {
        $this->fundModalOpen = false;
    }

    public function loadQueue()
    {
        $this->queueLoaded = true;
    }

    public function updatingStatusFilter()
    {
        $this->reset('selected_items', 'processingProblem', 'fallback_sources', 'fundModalOpen', 'selected_print_patients', 'print_range', 'printSelectionProblem', 'printModalOpen');
        $this->dispatch('uddds-selection-cleared');
    }

    public function updatingQueueView()
    {
        $this->reset('selected_items', 'processingProblem', 'fallback_sources', 'fundModalOpen', 'selected_print_patients', 'print_range', 'printSelectionProblem', 'printModalOpen');
    }

    public function updatingWardcode()
    {
        $this->reset('selected_items', 'processingProblem', 'fallback_sources', 'fundModalOpen', 'selected_print_patients', 'print_range', 'printSelectionProblem', 'printModalOpen');
    }

    public function updatingSelectedDate()
    {
        $this->reset('selected_items', 'processingProblem', 'fallback_sources', 'fundModalOpen', 'selected_print_patients', 'print_range', 'printSelectionProblem', 'printModalOpen');
    }

    public function showToday()
    {
        $this->selected_date = now('Asia/Manila')->toDateString();
        $this->reset('selected_items', 'processingProblem', 'fallback_sources', 'fundModalOpen', 'selected_print_patients', 'print_range', 'printSelectionProblem', 'printModalOpen');
    }

    public function render()
    {
        $items = $this->filteredItems();
        if ($this->queue_view === 'active') {
            $items = app(UdddsService::class)->annotatePendingStock($items, auth()->user()->pharm_location_id);
        }
        $patients = $this->groupPatients($items);
        $printablePatients = array_values(array_map(fn ($patient) => (string) $patient['enccode'], array_filter($patients, fn ($patient) => !empty($patient['reprint_url']))));
        $patientNumbers = array_column($patients, 'number', 'enccode');
        $selectedPrintCodes = app(UdddsService::class)->selectedPatientChargeCodes($items, $this->selected_print_patients, $this->selected_date);
        $actionableKeys = collect($items)
            ->filter(fn ($item) => (bool) $item->is_actionable)
            ->pluck('docointkey')
            ->map(fn ($key) => (string) $key)
            ->values()
            ->all();
        $udddsReady = !$this->queueLoaded || UdddsService::hasHrxoColumns();

        return view('livewire.pharmacy.prescriptions.uddds-ward', [
            'items' => $items,
            'patients' => $patients,
            'fundGroups' => array_filter(app(UdddsService::class)->fundSelectionGroups($items, $this->selected_items), fn ($group) => $group['item']->current_available < $group['qty']),
            'hasPrintablePatients' => !empty($printablePatients),
            'allPrintPatientsSelected' => !empty($printablePatients) && !array_diff($printablePatients, $this->selected_print_patients),
            'selectedPrintUrl' => $this->numberedPrintUrl($selectedPrintCodes, $items, $patientNumbers),
            'selectedPrintCount' => count(array_intersect($printablePatients, $this->selected_print_patients)),
            'hasActionableItems' => !empty($actionableKeys),
            'actionableKeys' => $actionableKeys,
            'allSelected' => !empty($actionableKeys) && !array_diff($actionableKeys, $this->selected_items),
            'displayDate' => Carbon::parse($this->selected_date)->format('F j, Y'),
            'isToday' => $this->selected_date === now('Asia/Manila')->toDateString(),
            'eligibleCount' => collect($items)->where('queue_status', 'eligible')->count(),
            'billableCount' => collect($items)->where('is_billable', 1)->count(),
            'issuedCount' => collect($items)->filter(fn ($item) => !empty($item->is_source_issued_for_date)
                || ($item->estatus === 'S' && !empty($item->uddds_source_docointkey)))->count(),
            'udddsReady' => $udddsReady,
            'udddsMessage' => $udddsReady ? null : app(UdddsService::class)->schemaMissingMessage(),
        ]);
    }

    public function readyToBill($enccode)
    {
        $items = $this->filteredItems();
        $keys = [];
        foreach ($items as $item) {
            if ($item->enccode === $enccode && $item->is_actionable) {
                $keys[] = $item->docointkey;
            }
        }

        $this->processKeys($keys);
    }

    public function openPrintModal()
    {
        $this->printModalOpen = true;
    }

    public function closePrintModal()
    {
        $this->printModalOpen = false;
    }

    public function applyPrintRange()
    {
        $this->printSelectionProblem = null;
        $patients = array_values($this->groupPatients($this->filteredItems()));
        try {
            $numbers = \App\Services\Pharmacy\UdddsPrintSelection::parse($this->print_range, count($patients));
            $encounters = [];
            foreach ($numbers as $number) {
                $patient = $patients[$number - 1];
                if (!$patient['reprint_url']) throw new \InvalidArgumentException('Patient #' . $number . ' has no existing charge slip under these filters.');
                $encounters[] = (string) $patient['enccode'];
            }
            $this->selected_print_patients = $encounters;
        } catch (\InvalidArgumentException $e) {
            $this->printSelectionProblem = $e->getMessage();
        }
    }

    public function togglePrintPatients()
    {
        $patients = $this->groupPatients($this->filteredItems());
        $keys = array_values(array_map(fn ($patient) => (string) $patient['enccode'], array_filter($patients, fn ($patient) => !empty($patient['reprint_url']))));
        $this->selected_print_patients = $keys && !array_diff($keys, $this->selected_print_patients) ? [] : $keys;
    }

    public function selectPending()
    {
        $this->selected_items = $this->selectableKeys();
    }

    public function toggleSelectAll()
    {
        $keys = $this->selectableKeys();
        $this->selected_items = $keys && !array_diff($keys, $this->selected_items) ? [] : $keys;
    }

    protected function selectableKeys(): array
    {
        $keys = [];
        foreach ($this->filteredItems() as $item) {
            if ($item->is_actionable) $keys[] = (string) $item->docointkey;
        }
        return array_values(array_unique($keys));
    }

    public function processSelected(array $keys = [])
    {
        $this->processKeys($keys ?: $this->selected_items);
    }

    public function view_enctr($enccode)
    {
        $enccode = Crypt::encrypt(str_replace(' ', '--', $enccode));

        return redirect()->route('dispensing.view.enctr', ['enccode' => $enccode]);
    }

    protected function processKeys(array $keys)
    {
        if ($this->queue_view === 'processed') {
            return;
        }
        $udddsService = app(UdddsService::class);
        $queueItems = $this->filteredItems();
        $patientNumbersBefore = array_column($this->groupPatients($queueItems), 'number', 'enccode');
        $allowed = collect($queueItems)->filter(fn ($item) => (bool) $item->is_actionable)->pluck('docointkey')->all();
        $keys = array_values(array_intersect(array_unique($keys), $allowed));
        $this->processingProblem = null;
        $keys = $udddsService->materializeDailyItems($keys, $this->selected_date);
        $result = $udddsService->chargeAndIssue($keys, auth()->user()->pharm_location_id, [
            'employeeid' => auth()->user()->employeeid,
            'user_id' => auth()->id(),
            'consumption_id' => null,
            'toecode' => 'ADM',
        ], $this->fallback_sources);

        if (!empty($result['pcchrgcods'])) {
            $printItems = $udddsService->wardItemsForDate($this->wardcode, auth()->user()->pharm_location_id, $this->selected_date);
            $this->lastBatchPrintUrl = $this->numberedPrintUrl($result['pcchrgcods'], $printItems, $patientNumbersBefore);
            $this->dispatch('uddds-print', url: $this->lastBatchPrintUrl);
        }

        if (!$result['ok']) {
            $this->processingProblem = $result['message'];
            $this->error($result['message']);
            return;
        }

        $this->reset('selected_items', 'processingProblem', 'fallback_sources', 'fundModalOpen', 'selected_print_patients', 'print_range', 'printSelectionProblem', 'printModalOpen');
        $this->dispatch('uddds-selection-cleared');
        $this->success($result['message']);

    }

    protected function groupPatients(array $items)
    {
        $patients = [];

        foreach ($items as $item) {
            $name = trim(($item->patlast ?? '') . ', ' . ($item->patfirst ?? '') . ' ' . ($item->patmiddle ?? ''));
            if (!isset($patients[$item->enccode])) {
                $patients[$item->enccode] = [
                    'enccode' => $item->enccode,
                    'hpercode' => $item->hpercode,
                    'name' => $name,
                    'wardname' => $item->wardname,
                    'rmname' => $item->rmname,
                    'items' => [],
                    'keys' => [],
                ];
            }
            $patients[$item->enccode]['items'][] = $item;
            if ($item->is_actionable) {
                $patients[$item->enccode]['keys'][] = $item->docointkey;
            }
        }

        $number = 0;
        foreach ($patients as &$patient) {
            $patient['number'] = ++$number;
            $patient['reprint_url'] = $this->numberedPrintUrl(app(UdddsService::class)->reprintChargeCodes($patient['items'], $this->selected_date), $patient['items'], [$patient['enccode'] => $number]);
        }
        unset($patient);

        return $patients;
    }

    protected function numberedPrintUrl(array $codes, array $items, array $patientNumbers): ?string
    {
        if (!$codes) return null;
        $numbers = [];
        foreach ($codes as $code) {
            $number = 0;
            foreach ($items as $item) {
                if ($item->pcchrgcod === $code) { $number = $patientNumbers[$item->enccode] ?? 0; break; }
            }
            $numbers[] = $number;
        }
        return route('dispensing.uddds.chargeslips', ['codes' => implode(',', $codes), 'numbers' => implode(',', $numbers)]);
    }

    protected function reprintUrl(array $items): ?string
    {
        $codes = app(UdddsService::class)->reprintChargeCodes($items, $this->selected_date);

        return $codes ? route('dispensing.uddds.chargeslips', ['codes' => implode(',', $codes)]) : null;
    }

    protected function filteredItems(): array
    {
        if (!$this->queueLoaded) {
            return [];
        }

        $service = app(UdddsService::class);
        $processed = $this->queue_view === 'processed';
        $items = $processed
            ? $service->processedWardItemsForDate($this->wardcode, auth()->user()->pharm_location_id, $this->selected_date)
            : $service->wardItemsForDate($this->wardcode, auth()->user()->pharm_location_id, $this->selected_date);

        return $service->filterWardItemsByStatus($items, $this->status_filter, $processed);
    }
}
