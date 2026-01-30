<?php

namespace App\Livewire\Pharmacy\Prescriptions\Queueing;

use Livewire\Component;
use Mary\Traits\Toast;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueDisplaySetting;
use App\Models\PharmLocation;

class QueueDisplaySettings extends Component
{
    use Toast;

    public $locationCode;
    public $autoRefreshSeconds = 30;
    public $displayLimit = 10;
    public $pharmacyWindows = 3;
    public $requireCashier = true;
    public $cashierLocation = '';
    public $showPatientName = false;
    public $playSoundAlert = true;
    public $showEstimatedWait = true;

    public function mount()
    {
        $this->locationCode = auth()->user()->pharm_location_id ?? '1';
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $settings = PrescriptionQueueDisplaySetting::getForLocation($this->locationCode);

        $this->autoRefreshSeconds = $settings->auto_refresh_seconds;
        $this->displayLimit = $settings->display_limit;
        $this->pharmacyWindows = $settings->pharmacy_windows;
        $this->requireCashier = $settings->require_cashier;
        $this->cashierLocation = $settings->cashier_location ?? '';
        $this->showPatientName = $settings->show_patient_name;
        $this->playSoundAlert = $settings->play_sound_alert;
        $this->showEstimatedWait = $settings->show_estimated_wait;
    }

    public function save()
    {
        $this->validate([
            'autoRefreshSeconds' => 'required|integer|min:3|max:300',
            'displayLimit' => 'required|integer|min:5|max:50',
            'pharmacyWindows' => 'required|integer|min:1|max:10',
            'cashierLocation' => 'nullable|string|max:100',
        ]);

        $settings = PrescriptionQueueDisplaySetting::updateOrCreate(
            ['location_code' => $this->locationCode],
            [
                'auto_refresh_seconds' => $this->autoRefreshSeconds,
                'display_limit' => $this->displayLimit,
                'pharmacy_windows' => $this->pharmacyWindows,
                'require_cashier' => $this->requireCashier,
                'cashier_location' => $this->cashierLocation,
                'show_patient_name' => $this->showPatientName,
                'play_sound_alert' => $this->playSoundAlert,
                'show_estimated_wait' => $this->showEstimatedWait,
            ]
        );

        $this->success('Display settings updated successfully!');
    }

    public function render()
    {
        return view('livewire.pharmacy.prescriptions.queueing.queue-display-settings', [
            'locations' => PharmLocation::orderBy('description')->get(),
        ]);
    }
}
