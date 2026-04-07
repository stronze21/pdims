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
    public $showCreateDrawer = false;

    // Create session form
    public $appointmentId = null;
    public $scheduledDate = '';
    public $scheduledTime = '';
    public $selectedAppointment = [];

    // Searchable appointment rows for the drawer table
    public $appointmentSearch = '';
    public $appointmentRows = [];

    public function mount()
    {
        $this->loadAppointmentRows();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedAppointmentSearch()
    {
        $this->loadAppointmentRows();
    }

    public function openCreateDrawer()
    {
        $this->resetCreateForm();
        $this->appointmentSearch = '';
        $this->showCreateDrawer = true;
        $this->loadAppointmentRows();
    }

    public function closeCreateDrawer()
    {
        $this->showCreateDrawer = false;
        $this->resetCreateForm();
        $this->appointmentSearch = '';
        $this->loadAppointmentRows();
    }

    protected function resetCreateForm(): void
    {
        $this->reset(['appointmentId', 'scheduledDate', 'scheduledTime', 'selectedAppointment']);
    }

    /**
     * Load appointments for the drawer table, filtered by the search term.
     */
    public function loadAppointmentRows(): void
    {
        $value = trim($this->appointmentSearch);

        $query = DB::connection('portal')->table('appointments')
            ->leftJoin('appointment_types', 'appointments.appointment_type', '=', 'appointment_types.id')
            ->leftJoin('patients', 'appointments.patient_id', '=', 'patients.id')
            ->whereNotNull('appointments.confirmed_by')
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

        $this->appointmentRows = $query
            ->orderBy('appointments.appointment_date')
            ->select(
                'appointments.id',
                'appointments.ref_no',
                'appointments.appointment_date',
                'appointment_types.name as type_name',
                'appointments.doctor',
                'patients.patlast',
                'patients.patfirst',
                'patients.patmiddle',
                'patients.hpercode'
            )
            ->limit(30)
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'ref_no' => $a->ref_no,
                    'appointment_date' => $a->appointment_date,
                    'patient_name' => trim(($a->patlast ?? '-') . ', ' . ($a->patfirst ?? '')),
                    'hpercode' => $a->hpercode,
                    'type_name' => $a->type_name ?? 'General',
                    'doctor' => $a->doctor,
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

        $appointment = collect($this->appointmentRows)->firstWhere('id', (int) $value);

        if (!$appointment) {
            $appointment = DB::connection('portal')->table('appointments')
                ->where('id', $value)
                ->whereNull('deleted_at')
                ->select('id', 'appointment_date')
                ->first();
        }

        $appointmentDate = data_get($appointment, 'appointment_date');

        if ($appointmentDate) {
            $this->scheduledDate = date('Y-m-d', strtotime($appointmentDate));
            $this->scheduledTime = date('H:i', strtotime($appointmentDate));
        }
    }

    public function selectAppointment($appointmentId): void
    {
        $appointment = collect($this->appointmentRows)->firstWhere('id', (int) $appointmentId);

        if (!$appointment) {
            $appointment = DB::connection('portal')->table('appointments')
                ->leftJoin('appointment_types', 'appointments.appointment_type', '=', 'appointment_types.id')
                ->leftJoin('patients', 'appointments.patient_id', '=', 'patients.id')
                ->where('appointments.id', $appointmentId)
                ->whereNull('appointments.deleted_at')
                ->select(
                    'appointments.id',
                    'appointments.ref_no',
                    'appointments.appointment_date',
                    'appointment_types.name as type_name',
                    'appointments.doctor',
                    'patients.patlast',
                    'patients.patfirst',
                    'patients.patmiddle',
                    'patients.hpercode'
                )
                ->first();
        }

        if (!$appointment) {
            $this->error('Appointment not found.');
            return;
        }

        $appointmentIdValue = data_get($appointment, 'id');
        $appointmentDate = data_get($appointment, 'appointment_date');

        if (!$appointmentIdValue || !$appointmentDate) {
            $this->error('Appointment details are incomplete.');
            return;
        }

        $this->appointmentId = (int) $appointmentIdValue;
        $this->scheduledDate = date('Y-m-d', strtotime($appointmentDate));
        $this->scheduledTime = date('H:i', strtotime($appointmentDate));
        $this->selectedAppointment = [
            'id' => (int) $appointmentIdValue,
            'ref_no' => data_get($appointment, 'ref_no', '-'),
            'patient_name' => data_get($appointment, 'patient_name')
                ?? trim((data_get($appointment, 'patlast', '-') ?? '-') . ', ' . (data_get($appointment, 'patfirst', '') ?? '')),
            'hpercode' => data_get($appointment, 'hpercode'),
            'appointment_date' => $appointmentDate,
            'type_name' => data_get($appointment, 'type_name', 'General'),
            'doctor' => data_get($appointment, 'doctor'),
        ];
    }

    public function createSession()
    {
        $this->validate([
            'appointmentId' => 'required|integer',
            'scheduledDate' => 'required|date',
            'scheduledTime' => 'required|string',
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
            'platform' => 'jitsi',
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt,
        ];

        $jitsi = new JitsiService();
        $meeting = $jitsi->createMeeting(['title' => $title]);

        $sessionData['jitsi_room_name'] = $meeting['room_name'] ?? null;
        $sessionData['jitsi_meeting_link'] = $meeting['meeting_link'] ?? null;

        $session = TeleconsultSession::create($sessionData);

        $this->closeCreateDrawer();
        $this->appointmentSearch = '';
        $this->loadAppointmentRows();
        $this->success('Teleconsult session created for ' . ($appointment->ref_no ?? 'Appointment #' . $appointment->id) . '.');
    }

    public function startSession($sessionId)
    {
        $routeName = request()->routeIs('portal.teleconsult.*') ? 'portal.teleconsult.room' : 'teleconsult.room';

        return redirect()->route($routeName, ['sessionId' => $sessionId]);
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

        $layout = request()->routeIs('portal.teleconsult.*') ? 'layouts.portal' : 'layouts.app';

        return view('livewire.teleconsult.teleconsult-lobby', [
            'sessions' => $sessions,
        ])->layout($layout, [
            'title' => 'Teleconsult',
        ]);
    }
}
