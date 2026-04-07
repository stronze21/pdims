<?php

namespace App\Livewire\Portal;

use Livewire\Component;

class ManagePortalManual extends Component
{
    public bool $tourOpen = false;

    public int $tourStep = 1;

    public function startTour(): void
    {
        $this->tourOpen = true;
        $this->tourStep = 1;
    }

    public function nextTourStep(): void
    {
        if ($this->tourStep < 5) {
            $this->tourStep++;
        }
    }

    public function previousTourStep(): void
    {
        if ($this->tourStep > 1) {
            $this->tourStep--;
        }
    }

    public function closeTour(): void
    {
        $this->tourOpen = false;
        $this->tourStep = 1;
    }

    public function render()
    {
        return view('livewire.portal.manage-portal-manual')
            ->layout('layouts.portal');
    }
}
