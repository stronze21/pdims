<?php

namespace App\Livewire\Pharmacy\Prescriptions\Queueing;

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueueDisplaySetting;

class PrescriptionQueueDisplay extends Component
{
    public $locationCode;
    public $displaySettings;
    public $showingQueues = [];
    public $currentlyServing = [];
    public $lastCalledQueue = null;

    public function mount($locationCode = null)
    {
        $this->locationCode = $locationCode ?? auth()->user()->pharm_location_id;
        $this->displaySettings = PrescriptionQueueDisplaySetting::getForLocation($this->locationCode);
        $this->loadQueues();
    }

    #[On('refresh-display')]
    public function loadQueues()
    {
        // Get currently preparing
        $this->currentlyServing = PrescriptionQueue::forLocation($this->locationCode)
            ->preparing()
            ->whereDate('queued_at', today())
            ->orderByPriority()
            ->get();

        // Get charging (at cashier)
        $charging = PrescriptionQueue::forLocation($this->locationCode)
            ->charging()
            ->whereDate('queued_at', today())
            ->orderBy('charging_at', 'desc')
            ->limit($this->displaySettings->display_limit)
            ->get();

        // Get ready for dispensing
        $ready = PrescriptionQueue::forLocation($this->locationCode)
            ->ready()
            ->whereDate('queued_at', today())
            ->orderBy('ready_at', 'desc')
            ->limit($this->displaySettings->display_limit)
            ->get();

        $this->showingQueues = [
            'charging' => $charging,
            'ready' => $ready,
        ];

        if ($this->displaySettings->show_waiting_column) {
            $this->showingQueues['waiting'] = PrescriptionQueue::forLocation($this->locationCode)
                ->waiting()
                ->whereNull('assigned_window')
                ->whereNull('called_at')
                ->whereDate('queued_at', today())
                ->orderByPriority()
                ->orderBy('id')
                ->limit($this->displaySettings->display_limit)
                ->get();
        }

        // Get last called (most recently changed to ready)
        $this->lastCalledQueue = PrescriptionQueue::forLocation($this->locationCode)
            ->ready()
            ->whereDate('queued_at', today())
            ->orderBy('ready_at', 'desc')
            ->first();
    }

    public function render()
    {
        return view('livewire.pharmacy.prescriptions.queueing.prescription-queue-display')
            ->layout('layouts.queuing');
    }
}
