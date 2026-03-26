<?php

namespace App\Livewire\Teleconsult;

use App\Models\Portal\TeleconsultSession;
use App\Services\JitsiService;
use App\Services\LiveKitService;
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
    public $platform = '';

    // Searchable appointment options
    public $appointmentOptions = [];

    // Platform options for dropdown
    public function getPlatformOptionsProperty(): array
    {
        return [
            ['id' => 'jitsi', 'name' => 'Jitsi Meet (Self-hosted)'],
            ['id' => 'livekit', 'name' => 'LiveKit (Self-hosted)'],
            ['id' => 'webex', 'name' => 'Cisco Webex'],
        ];
    }

    public function mount()
    {
        $this->platform = config('services.teleconsult.default_platform', 'jitsi');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    /**
     * Server-side search for appointments by ref_no, patient name, or hpercode.
     * Supports "Last, First" format (e.g. "Ednilao, Christian").
     */
    public function searchAppointments(string $value = '')
    {
        $query = DB::connection('portal')->table('appointments')
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
            ->where('appointments.appointment_date', '>=', now()->startOfDay());

        if (strlen($value) >= 2) {
            // Check if search contains a comma ("Last, First" format)
            if (str_contains($value, ',')) {
                $parts = array_map('trim', explode(',', $value, 2));
                $lastName = $parts[0];
                $firstName = $parts[1] ?? '';

                $query->where(function ($q) use ($lastName, $firstName) {
                    $q->where('patients.patlast', 'LIKE', "%{$lastName}%");
                    if ($firstName) {
                        $q->where('patients.patfirst', 'LIKE', "%{$firstName}%");
                    }
                });
            } else {
                // Single term: search across all fields
                $query->where(function ($q) use ($value) {
                    $q->where('appointments.ref_no', 'LIKE', "%{$value}%")
                        ->orWhere('patients.patlast', 'LIKE', "%{$value}%")
                        ->orWhere('patients.patfirst', 'LIKE', "%{$value}%")
                        ->orWhere('patients.hpercode', 'LIKE', "%{$value}%")
                        ->orWhereRaw("CONCAT(patients.patlast, ', ', patients.patfirst) LIKE ?", ["%{$value}%"])
                        ->orWhereRaw("CONCAT(patients.patfirst, ' ', patients.patlast) LIKE ?", ["%{$value}%"]);
                });
            }
        }

        $this->appointmentOptions = $query
            ->orderBy('appointments.appointment_date')
            ->select(
                'appointments.id',
                'appointments.ref_no',
                'appointments.appointment_date',
                'appointment_types.name as type_name',
                'patients.patlast',
                'patients.patfirst',
                'patients.patmiddle',
                'patients.hpercode'
            )
            ->limit(30)
            ->get()
            ->map(function ($a) {
                $name = trim(($a->patlast ?? '') . ', ' . ($a->patfirst ?? ''));
                $hpercode = $a->hpercode ? " [{$a->hpercode}]" : '';
                $type = $a->type_name ?? 'General';
                $date = date('M d, Y h:i A', strtotime($a->appointment_date));

                return [
                    'id' => $a->id,
                    'name' => "{$a->ref_no} - {$name}{$hpercode} ({$type}) - {$date}",
                ];
            })
            ->toArray();
    }

    /**
     * Auto-populate date/time when appointment is selected.
     */
    public function updatedAppointmentId($value)
    {
        if (!$value) {
            $this->scheduledDate = '';
            $this->scheduledTime = '';
            return;
        }

        $appointment = DB::connection('portal')->table('appointments')
            ->where('id', $value)
            ->first();

        if ($appointment && $appointment->appointment_date) {
            $this->scheduledDate = date('Y-m-d', strtotime($appointment->appointment_date));
            $this->scheduledTime = date('H:i', strtotime($appointment->appointment_date));
        }
    }

    public function createSession()
    {
        $this->validate([
            'appointmentId' => 'required|integer',
            'scheduledDate' => 'required|date',
            'scheduledTime' => 'required|string',
            'platform' => 'required|in:webex,jitsi,livekit',
        ]);

        $appointment = DB::connection('portal')->table('appointments')
            ->leftJoin('patients', 'appointments.patient_id', '=', 'patients.id')
            ->where('appointments.id', $this->appointmentId)
            ->whereNull('appointments.deleted_at')
            ->select('appointments.*', 'patients.hpercode')
            ->first();

        if (!$appointment) {
            $this->error('Appointment not found.');
            return;
        }

        // Check if a session already exists for this appointment
        $existing = TeleconsultSession::where('appointment_id', $this->appointmentId)
            ->whereIn('status', ['scheduled', 'waiting', 'in_progress'])
            ->exists();

        if ($existing) {
            $this->error('An active teleconsult session already exists for this appointment.');
            return;
        }

        $scheduledAt = $this->scheduledDate . ' ' . $this->scheduledTime;
        $title = 'Teleconsult - ' . ($appointment->ref_no ?? 'Appointment #' . $appointment->id);

        // Get doctor info from current user
        $user = auth()->user();

        $sessionData = [
            'appointment_id' => $this->appointmentId,
            'patient_id' => $appointment->patient_id,
            'doctor_employee_id' => $user->employeeid ?? $user->id,
            'doctor_name' => $user->name,
            'platform' => $this->platform,
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ];

        if ($this->platform === 'jitsi') {
            $jitsi = new JitsiService();
            $meeting = $jitsi->createMeeting(['title' => $title]);

            $sessionData['jitsi_room_name'] = $meeting['room_name'] ?? null;
            $sessionData['jitsi_meeting_link'] = $meeting['meeting_link'] ?? null;
        } elseif ($this->platform === 'livekit') {
            $livekit = new LiveKitService();
            $meeting = $livekit->createMeeting(['title' => $title]);

            $sessionData['livekit_room_name'] = $meeting['room_name'] ?? null;

            // Generate doctor token immediately
            $doctorToken = $livekit->generateParticipantToken(
                $meeting['room_name'],
                $user->name ?? 'Doctor',
                true
            );
            $sessionData['livekit_token'] = $doctorToken;
        } else {
            $webex = new WebexService();
            $meeting = $webex->createMeeting([
                'title' => $title,
                'start' => $scheduledAt,
                'end' => date('Y-m-d H:i:s', strtotime($scheduledAt) + 1800),
            ]);

            $sessionData['webex_meeting_id'] = $meeting['id'] ?? null;
            $sessionData['webex_meeting_link'] = $meeting['meetingLink'] ?? $meeting['joinLink'] ?? null;
            $sessionData['webex_sip_address'] = $meeting['sipAddress'] ?? null;
            $sessionData['webex_meeting_number'] = $meeting['meetingNumber'] ?? null;
            $sessionData['webex_meeting_password'] = $meeting['password'] ?? null;
            $sessionData['webex_host_key'] = $meeting['hostKey'] ?? null;
        }

        $session = TeleconsultSession::create($sessionData);

        $this->showCreateModal = false;
        $this->reset(['appointmentId', 'scheduledDate', 'scheduledTime', 'appointmentOptions']);
        $this->platform = config('services.teleconsult.default_platform', 'jitsi');
        $this->success('Teleconsult session created for ' . ($appointment->ref_no ?? 'Appointment #' . $appointment->id) . '.');
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

        // Clean up meeting on the platform side
        if ($session->platform === 'webex' && $session->webex_meeting_id) {
            $webex = new WebexService();
            $webex->deleteMeeting($session->webex_meeting_id);
        } elseif ($session->platform === 'livekit' && $session->livekit_room_name) {
            $livekit = new LiveKitService();
            $livekit->deleteRoom($session->livekit_room_name);
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
                                ->orWhere('patfirst', 'LIKE', "%{$this->search}%")
                                ->orWhere('hpercode', 'LIKE', "%{$this->search}%");
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

        return view('livewire.teleconsult.teleconsult-lobby', [
            'sessions' => $sessions,
        ]);
    }
}
