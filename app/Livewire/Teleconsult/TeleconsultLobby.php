<?php

namespace App\Livewire\Teleconsult;

use App\Models\Portal\TeleconsultSession;
use App\Services\WebexService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class TeleconsultLobby extends Component
{
    use Toast, WithPagination;

    public $search = '';
    public $statusFilter = 'today';
    public $selectedSession = null;
    public $showCreateModal = false;

    // Create session form
    public $appointmentId = null;
    public $scheduledDate = '';
    public $scheduledTime = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function createSession()
    {
        $this->validate([
            'appointmentId' => 'required|integer',
            'scheduledDate' => 'required|date',
            'scheduledTime' => 'required|string',
        ]);

        $appointment = DB::connection('portal')->table('appointments')
            ->where('id', $this->appointmentId)
            ->whereNull('deleted_at')
            ->first();

        if (!$appointment) {
            $this->error('Appointment not found.');
            return;
        }

        $scheduledAt = $this->scheduledDate . ' ' . $this->scheduledTime;

        // Get doctor info from current user
        $user = auth()->user();

        // Create meeting via Webex middleware
        $webex = new WebexService();
        $meeting = $webex->createMeeting([
            'title' => 'Teleconsult - Appointment #' . $appointment->ref_no,
            'start' => $scheduledAt,
            'end' => date('Y-m-d H:i:s', strtotime($scheduledAt) + 1800), // 30 min default
        ]);

        $session = TeleconsultSession::create([
            'appointment_id' => $this->appointmentId,
            'patient_id' => $appointment->patient_id,
            'doctor_employee_id' => $user->employeeid ?? $user->id,
            'doctor_name' => $user->name,
            'webex_meeting_id' => $meeting['id'] ?? null,
            'webex_meeting_link' => $meeting['webLink'] ?? $meeting['join_link'] ?? null,
            'webex_sip_address' => $meeting['sipAddress'] ?? null,
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ]);

        $this->showCreateModal = false;
        $this->reset(['appointmentId', 'scheduledDate', 'scheduledTime']);
        $this->success('Teleconsult session created.');
    }

    public function startSession($sessionId)
    {
        return redirect()->route('teleconsult.room', ['sessionId' => $sessionId]);
    }

    public function cancelSession($sessionId)
    {
        $session = TeleconsultSession::find($sessionId);

        if (!$session) {
            $this->error('Session not found.');
            return;
        }

        if ($session->webex_meeting_id) {
            $webex = new WebexService();
            $webex->deleteMeeting($session->webex_meeting_id);
        }

        $session->update(['status' => 'cancelled']);
        $this->success('Teleconsult session cancelled.');
    }

    public function getStatusCountsProperty()
    {
        $today = now()->startOfDay();

        return [
            'today' => TeleconsultSession::whereDate('scheduled_at', $today)->count(),
            'upcoming' => TeleconsultSession::where('scheduled_at', '>', now())->whereIn('status', ['scheduled'])->count(),
            'in_progress' => TeleconsultSession::whereIn('status', ['waiting', 'in_progress'])->count(),
            'completed' => TeleconsultSession::where('status', 'completed')->count(),
        ];
    }

    public function render()
    {
        $query = TeleconsultSession::with('patient')
            ->when($this->search, function ($q) {
                $q->where(function ($q2) {
                    $q2->where('doctor_name', 'LIKE', "%{$this->search}%")
                        ->orWhereHas('patient', function ($pq) {
                            $pq->where('patlast', 'LIKE', "%{$this->search}%")
                                ->orWhere('patfirst', 'LIKE', "%{$this->search}%");
                        });
                });
            });

        switch ($this->statusFilter) {
            case 'today':
                $query->whereDate('scheduled_at', now()->startOfDay());
                break;
            case 'upcoming':
                $query->where('scheduled_at', '>', now())->where('status', 'scheduled');
                break;
            case 'in_progress':
                $query->whereIn('status', ['waiting', 'in_progress']);
                break;
            case 'completed':
                $query->where('status', 'completed');
                break;
        }

        $sessions = $query->orderBy('scheduled_at', 'desc')->paginate(15);

        // Get confirmed teleconsult appointments for the create modal
        $appointments = DB::connection('portal')->table('appointments')
            ->leftJoin('appointment_types', 'appointments.appointment_type', '=', 'appointment_types.id')
            ->leftJoin('patients', 'appointments.patient_id', '=', 'patients.id')
            ->where('appointments.confirmed_by', '!=', null)
            ->whereNull('appointments.deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('teleconsult_sessions')
                    ->whereColumn('teleconsult_sessions.appointment_id', 'appointments.id')
                    ->whereIn('teleconsult_sessions.status', ['scheduled', 'waiting', 'in_progress']);
            })
            ->where('appointments.appointment_date', '>=', now()->startOfDay())
            ->orderBy('appointments.appointment_date')
            ->select(
                'appointments.id',
                'appointments.ref_no',
                'appointments.appointment_date',
                'appointment_types.name as type_name',
                'patients.patlast',
                'patients.patfirst',
                'patients.patmiddle'
            )
            ->limit(50)
            ->get()
            ->map(function ($a) {
                $name = trim(($a->patlast ?? '') . ', ' . ($a->patfirst ?? ''));
                return [
                    'id' => $a->id,
                    'name' => $a->ref_no . ' - ' . $name . ' (' . ($a->type_name ?? 'General') . ')',
                ];
            });

        return view('livewire.teleconsult.teleconsult-lobby', [
            'sessions' => $sessions,
            'appointments' => $appointments,
        ]);
    }
}
