<?php

namespace App\Livewire\Pharmacy\Prescriptions;

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

        // Get waiting
        $waiting = PrescriptionQueue::forLocation($this->locationCode)
            ->waiting()
            ->whereDate('queued_at', today())
            ->orderByPriority()
            ->limit(max(5, $this->displaySettings->display_limit - $ready->count()))
            ->get();

        $this->showingQueues = [
            'charging' => $charging,
            'ready' => $ready,
            'waiting' => $waiting,
        ];

        // Get last called (most recently changed to ready)
        $this->lastCalledQueue = PrescriptionQueue::forLocation($this->locationCode)
            ->ready()
            ->whereDate('queued_at', today())
            ->orderBy('ready_at', 'desc')
            ->first();
    }

    public function render()
    {
        return view('livewire.pharmacy.prescriptions.prescription-queue-display')
            ->layout('layouts.queuing');
    }
}
