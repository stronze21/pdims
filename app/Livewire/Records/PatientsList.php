<?php

namespace App\Livewire\Records;

use App\Models\Record\Encounters\EncounterLog;
use Livewire\Component;
use Illuminate\Support\Facades\Crypt;
use App\Models\Record\Patients\Patient;
use Mary\Traits\Toast;

class PatientsList extends Component
{
    use Toast;

    // Search filters
    public $searchHpercode = '';
    public $searchFirstName = '';
    public $searchMiddleName = '';
    public $searchLastName = '';
    public $searchDob = '';

    // Selected patient data
    public $selectedPatient = null;
    public $selectedHpercode = null;
    public $patientEncounters = [];

    // New patient form
    public $showNewPatientModal = false;
    public $newPatientFirstName = '';
    public $newPatientMiddleName = '';
    public $newPatientLastName = '';
    public $newPatientSex = 'M';

    // State
    public $searchResults = [];
    public $hasSearched = false;

    public function mount()
    {
        $this->searchResults = collect();
    }

    public function searchPatients()
    {
        $this->hasSearched = true;
        $this->resetSelection();

        // Build query
        $query = Patient::query();

        if ($this->searchHpercode) {
            $query->where('hpercode', 'LIKE', $this->searchHpercode . '%');
        }

        if ($this->searchLastName) {
            $query->where('patlast', 'LIKE', $this->searchLastName . '%');
        }

        if ($this->searchMiddleName) {
            $query->where('patmiddle', 'LIKE', $this->searchMiddleName . '%');
        }

        if ($this->searchFirstName) {
            $query->where('patfirst', 'LIKE', $this->searchFirstName . '%');
        }

        if ($this->searchDob) {
            $query->whereRaw('CONVERT(date, patbdate) = ?', [$this->searchDob]);
        }

        $this->searchResults = $query
            ->orderBy('patlast')
            ->orderBy('patfirst')
            ->limit(100)
            ->get();

        if ($this->searchResults->isEmpty()) {
            $this->warning('No patients found matching your criteria.');
        } else {
            $this->success('Found ' . $this->searchResults->count() . ' patient(s).');

            // Auto-select if only 1 result
            if ($this->searchResults->count() === 1) {
                $this->selectPatient($this->searchResults->first()->hpercode);
            }
        }
    }

    public function selectPatient($hpercode)
    {
        $this->selectedHpercode = $hpercode;
        $this->selectedPatient = Patient::where('hpercode', $hpercode)->first();

        if ($this->selectedPatient) {
            $this->loadPatientEncounters();
            $this->success('Patient selected: ' . $this->selectedPatient->fullname);
        }
    }

    public function loadPatientEncounters()
    {
        $this->patientEncounters = EncounterLog::where('hpercode', $this->selectedHpercode)
            ->where('toecode', '!=', 'WALKN')
            ->where('toecode', '!=', '32')
            ->with([
                'opd:enccode,opddtedis,opdstat',
                'er:enccode,erdtedis,erstat',
                'adm:enccode,disdate,admstat',
                'diagnosis:enccode,diagtext',
                'accountTrack:enccode,billstat'
            ])
            ->orderBy('encdate', 'desc')
            ->get()
            ->map(function ($encounter) {
                return [
                    'enccode' => $encounter->enccode,
                    'toecode' => $encounter->toecode,
                    'encdate' => $encounter->encdate,
                    'encstat' => $encounter->encstat,
                    'diagtext' => $encounter->diagnosis->diagtext ?? null,
                    'billstat' => $encounter->accountTrack->billstat ?? null,
                    'is_discharged' => $this->isEncounterDischarged($encounter),
                    'status_badge' => $this->getEncounterStatus($encounter),
                ];
            });
    }

    private function isEncounterDischarged($encounter)
    {
        return ($encounter->opd && $encounter->opd->opddtedis) ||
            ($encounter->er && $encounter->er->erdtedis) ||
            ($encounter->adm && $encounter->adm->disdate);
    }

    private function getEncounterStatus($encounter)
    {
        if ($this->isEncounterDischarged($encounter)) {
            return 'discharged';
        }

        if ($encounter->accountTrack && in_array($encounter->accountTrack->billstat, ['02', '03'])) {
            return 'billed';
        }

        return 'active';
    }

    public function viewEncounter($enccode)
    {
        $encrypted = Crypt::encrypt(str_replace(' ', '--', $enccode));
        return redirect()->route('dispensing.view.enctr', [$encrypted]);
    }

    public function initiateWalkIn()
    {
        if (!$this->selectedHpercode) {
            $this->error('Please select a patient first.');
            return;
        }

        // Check for existing walk-in encounter
        $existingWalkIn = EncounterLog::where('encstat', 'W')
            ->where('toecode', 'WALKN')
            ->where('hpercode', $this->selectedHpercode)
            ->whereHas('rxo')
            ->latest('encdate')
            ->first();

        if ($existingWalkIn) {
            $this->redirectToEncounter($existingWalkIn->enccode);
            return;
        }

        // Create new walk-in encounter
        $newEnccode = '0000040' . $this->selectedHpercode . date('mdYHis');

        $newEncounter = EncounterLog::create([
            'enccode' => $newEnccode,
            'fhud' => '0000040',
            'hpercode' => $this->selectedHpercode,
            'encdate' => now(),
            'enctime' => now(),
            'toecode' => 'WALKN',
            'sopcode1' => 'SELPA',
            'encstat' => 'W',
            'confdl' => 'N',
        ]);

        $this->success('Walk-in encounter created successfully.');
        $this->redirectToEncounter($newEncounter->enccode);
    }

    private function redirectToEncounter($enccode)
    {
        $encrypted = Crypt::encrypt(str_replace(' ', '--', $enccode));
        return redirect()->route('dispensing.view.enctr', ['enccode' => $encrypted]);
    }

    public function openNewPatientModal()
    {
        $this->showNewPatientModal = true;
        $this->reset(['newPatientFirstName', 'newPatientMiddleName', 'newPatientLastName', 'newPatientSex']);
    }

    public function closeNewPatientModal()
    {
        $this->showNewPatientModal = false;
    }

    public function createNewPatient()
    {
        $this->validate([
            'newPatientFirstName' => 'required|string|max:255',
            'newPatientMiddleName' => 'nullable|string|max:255',
            'newPatientLastName' => 'required|string|max:255',
            'newPatientSex' => 'required|in:M,F',
        ]);

        // Generate new hospital number
        $prefix = 'W' . date('Y');
        $count = Patient::where('hpercode', 'LIKE', $prefix . '%')->count();
        $hpercode = $prefix . sprintf('%07d', $count + 1);

        // Create patient
        $patient = Patient::create([
            'hpercode' => $hpercode,
            'hpatkey' => $hpercode,
            'hpatcode' => $hpercode,
            'patfirst' => $this->newPatientFirstName,
            'patmiddle' => $this->newPatientMiddleName,
            'patlast' => $this->newPatientLastName,
            'patsex' => $this->newPatientSex,
            'hfhudcode' => '0000040',
            'patstat' => 'A',
            'patlock' => 'N',
            'confdl' => 'N',
            'updsw' => 'U',
            'datemod' => now(),
        ]);

        $this->success('New patient created successfully: ' . $patient->fullname);
        $this->closeNewPatientModal();

        // Auto-select the new patient and create walk-in
        $this->selectedHpercode = $hpercode;
        $this->initiateWalkIn();
    }

    public function clearSearch()
    {
        $this->reset([
            'searchHpercode',
            'searchFirstName',
            'searchMiddleName',
            'searchLastName',
            'searchDob',
            'hasSearched'
        ]);
        $this->searchResults = collect();
        $this->resetSelection();
        $this->info('Search cleared.');
    }

    private function resetSelection()
    {
        $this->selectedPatient = null;
        $this->selectedHpercode = null;
        $this->patientEncounters = [];
    }

    public function render()
    {
        return view('livewire.records.patients-list');
    }
}
