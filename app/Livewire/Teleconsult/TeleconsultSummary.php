<?php

namespace App\Livewire\Teleconsult;

use App\Models\Portal\TeleconsultSession;
use Livewire\Component;
use Mary\Traits\Toast;

class TeleconsultSummary extends Component
{
    use Toast;

    public $sessionId;
    public $session;

    public function mount($sessionId)
    {
        $this->sessionId = $sessionId;
        $this->session = TeleconsultSession::with(['patient', 'note'])->findOrFail($sessionId);
    }

    public function backToLobby()
    {
        return redirect()->route('teleconsult.lobby');
    }

    public function render()
    {
        return view('livewire.teleconsult.teleconsult-summary');
    }
}
