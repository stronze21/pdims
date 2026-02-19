<?php

namespace App\Livewire\Dashboard;

use Livewire\Component;
use App\Models\Pharmacy\PharmLocation;
use App\Models\Pharmacy\Prescriptions\PrescriptionQueue;

class QueueDetails extends Component
{
    public string $location_id = 'all';
    public string $status_filter = 'all';
    public string $search = '';
    public array $location_options = [];

    public function mount()
    {
        $this->location_id = request('location_id', 'all');
        $this->status_filter = request('status', 'all');
        $this->loadLocationOptions();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedLocationId()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    private function loadLocationOptions()
    {
        $this->location_options = PharmLocation::orderBy('description')
            ->get()
            ->map(fn($loc) => ['id' => (string) $loc->id, 'description' => $loc->description])
            ->toArray();
    }

    public function render()
    {
        $query = PrescriptionQueue::whereDate('queued_at', today())
            ->when($this->location_id !== 'all', fn($q) => $q->where('location_code', $this->location_id))
            ->when($this->status_filter !== 'all', fn($q) => $q->where('queue_status', $this->status_filter))
            ->with(['patient'])
            ->orderBy('queued_at', 'desc');

        $records = $query->get();

        // Get summary counts
        $allQueues = PrescriptionQueue::whereDate('queued_at', today())
            ->when($this->location_id !== 'all', fn($q) => $q->where('location_code', $this->location_id));

        $summary = [
            'total' => (clone $allQueues)->count(),
            'waiting' => (clone $allQueues)->where('queue_status', 'waiting')->count(),
            'preparing' => (clone $allQueues)->where('queue_status', 'preparing')->count(),
            'charging' => (clone $allQueues)->where('queue_status', 'charging')->count(),
            'ready' => (clone $allQueues)->where('queue_status', 'ready')->count(),
            'dispensed' => (clone $allQueues)->where('queue_status', 'dispensed')->count(),
            'cancelled' => (clone $allQueues)->where('queue_status', 'cancelled')->count(),
        ];

        return view('livewire.dashboard.queue-details', [
            'records' => $records,
            'summary' => $summary,
        ])->layout('layouts.app', ['title' => 'Queue Details']);
    }
}
