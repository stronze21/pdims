<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalAppointmentController extends Controller
{
    /**
     * Get available appointment types.
     */
    public function types()
    {
        try {
            $types = DB::connection('portal')->table('appointment_types')
                ->whereNull('deleted_at')
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get();

            return response()->json($types);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /**
     * Get clinics that have appointment capacity configured.
     */
    public function clinics()
    {
        try {
            $clinics = DB::connection('portal')->table('clinics')
                ->whereNull('deleted_at')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('clinic_max_appointments')
                        ->whereColumn('clinic_max_appointments.tscode', 'clinics.tscode')
                        ->where('clinic_max_appointments.max', '>', 0);
                })
                ->select('tscode', 'clinic', 'contact_no')
                ->orderBy('clinic')
                ->get();

            return response()->json($clinics);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /**
     * Get availability for a clinic in a given month.
     * Returns dates with available/unavailable status.
     */
    public function availability(Request $request)
    {
        $request->validate([
            'clinic' => 'required|string',
            'year' => 'required|integer',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $clinic = $request->query('clinic');
        $year = (int) $request->query('year');
        $month = (int) $request->query('month');

        try {
            // Get daily summary rows (hr=0) grouped by day of week
            $dailySummaries = DB::connection('portal')->table('clinic_max_appointments')
                ->where('tscode', $clinic)
                ->where('hr', 0)
                ->where('max', '>', 0)
                ->pluck('max', 'day'); // day => max

            // Also get hourly configs (hr > 0) for today's past-hour filtering
            $hourlyConfigs = DB::connection('portal')->table('clinic_max_appointments')
                ->where('tscode', $clinic)
                ->where('hr', '>', 0)
                ->where('max', '>', 0)
                ->get()
                ->groupBy('day');

            $now = now();
            $today = $now->copy()->startOfDay();
            $currentHour = (int) $now->format('H');

            $startDate = \Carbon\Carbon::create($year, $month, 1);
            $daysInMonth = $startDate->daysInMonth;
            $dates = [];

            // Batch: get all appointment counts for this clinic+month in one query
            $monthlyCounts = DB::connection('portal')->table('appointments')
                ->where('attending_clinic', $clinic)
                ->whereNull('deleted_at')
                ->whereYear('appointment_date', $year)
                ->whereMonth('appointment_date', $month)
                ->selectRaw('DATE(appointment_date) as appt_date, COUNT(*) as cnt')
                ->groupByRaw('DATE(appointment_date)')
                ->pluck('cnt', 'appt_date');

            // For today, also get hourly breakdown
            $todayHourlyCounts = collect();
            if ($month == $now->month && $year == $now->year) {
                $todayHourlyCounts = DB::connection('portal')->table('appointments')
                    ->where('attending_clinic', $clinic)
                    ->whereNull('deleted_at')
                    ->whereDate('appointment_date', $today->format('Y-m-d'))
                    ->selectRaw('HOUR(appointment_date) as hr, COUNT(*) as cnt')
                    ->groupByRaw('HOUR(appointment_date)')
                    ->pluck('cnt', 'hr');
            }

            // Get holidays for this month
            $holidays = DB::connection('portal')->table('holidays')
                ->whereYear('holiday_date', $year)
                ->whereMonth('holiday_date', $month)
                ->pluck('holiday', 'holiday_date'); // date => holiday name

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $date = \Carbon\Carbon::create($year, $month, $d);
                $dateStr = $date->format('Y-m-d');
                $dayOfWeek = (int) $date->dayOfWeek; // 0=Sun, 1=Mon ... 6=Sat

                // Past dates are unavailable
                if ($date->lt($today)) {
                    $dates[] = ['date' => $dateStr, 'available' => false, 'total_slots' => 0];
                    continue;
                }

                // Holidays are unavailable
                if ($holidays->has($dateStr)) {
                    $dates[] = ['date' => $dateStr, 'available' => false, 'total_slots' => 0, 'holiday' => $holidays->get($dateStr)];
                    continue;
                }

                // Check if clinic has capacity for this day of week
                $dailyMax = $dailySummaries->get($dayOfWeek);
                if (!$dailyMax) {
                    $dates[] = ['date' => $dateStr, 'available' => false, 'total_slots' => 0];
                    continue;
                }

                $isToday = $date->eq($today);

                if ($isToday) {
                    // For today: check each future hour individually
                    $dayHourConfigs = $hourlyConfigs->get($dayOfWeek, collect());
                    $remaining = 0;
                    foreach ($dayHourConfigs as $hc) {
                        if ($hc->hr <= $currentHour) continue;
                        $booked = $todayHourlyCounts->get($hc->hr, 0);
                        $remaining += max(0, $hc->max - $booked);
                    }
                    $dates[] = ['date' => $dateStr, 'available' => $remaining > 0, 'total_slots' => $remaining];
                } else {
                    // For future dates: use daily summary row vs total booked
                    $booked = $monthlyCounts->get($dateStr, 0);
                    $remaining = max(0, $dailyMax - $booked);
                    $dates[] = ['date' => $dateStr, 'available' => $remaining > 0, 'total_slots' => $remaining];
                }
            }

            return response()->json($dates);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to load availability: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get available time slots for a clinic on a specific date.
     */
    public function slots(Request $request)
    {
        $request->validate([
            'clinic' => 'required|string',
            'date' => 'required|date',
        ]);

        $clinic = $request->query('clinic');
        $date = $request->query('date');
        $dateObj = \Carbon\Carbon::parse($date);
        $dayOfWeek = (int) $dateObj->dayOfWeek; // 0=Sun, 1=Mon ... 6=Sat
        $now = now();
        $isToday = $dateObj->isSameDay($now);
        $currentHour = (int) $now->format('H');

        try {
            // Get hour configs for this day of week (exclude hr=0 summary rows)
            $hourConfigs = DB::connection('portal')->table('clinic_max_appointments')
                ->where('tscode', $clinic)
                ->where('day', $dayOfWeek)
                ->where('hr', '>', 0)
                ->where('max', '>', 0)
                ->orderBy('hr')
                ->get();

            // Batch: get all hourly counts for this date in one query
            $hourlyCounts = DB::connection('portal')->table('appointments')
                ->where('attending_clinic', $clinic)
                ->whereNull('deleted_at')
                ->whereDate('appointment_date', $date)
                ->selectRaw('HOUR(appointment_date) as hr, COUNT(*) as cnt')
                ->groupByRaw('HOUR(appointment_date)')
                ->pluck('cnt', 'hr');

            $slots = [];
            foreach ($hourConfigs as $config) {
                $hour = $config->hr;

                // Skip past hours on today's date
                if ($isToday && $hour <= $currentHour) {
                    continue;
                }

                $booked = $hourlyCounts->get($hour, 0);
                $available = max(0, $config->max - $booked);
                if ($available > 0) {
                    $timeLabel = \Carbon\Carbon::createFromTime($hour, 0)->format('h:i A');
                    $slots[] = [
                        'hour' => $hour,
                        'time' => $timeLabel,
                        'available' => $available,
                        'max' => $config->max,
                    ];
                }
            }

            return response()->json($slots);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to load time slots.'], 500);
        }
    }

    /**
     * Book a new appointment.
     */
    public function store(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        $request->validate([
            'appointment_type' => 'required|integer',
            'attending_clinic' => 'required|string',
            'appointment_date' => 'required|date',
            'contact_number' => 'required|string',
            'email' => 'required|email',
            'doctor' => 'nullable|string|max:50',
            'diagnosis' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:255',
        ]);

        try {
            // Generate unique ref_no
            $refNo = 'PA-' . now()->format('Ymd') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

            // Ensure unique
            while (DB::connection('portal')->table('appointments')->where('ref_no', $refNo)->exists()) {
                $refNo = 'PA-' . now()->format('Ymd') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            }

            $id = DB::connection('portal')->table('appointments')->insertGetId([
                'patient_id' => $patient->id,
                'appointment_date' => $request->input('appointment_date'),
                'appointment_type' => $request->input('appointment_type'),
                'attending_clinic' => $request->input('attending_clinic'),
                'doctor' => $request->input('doctor'),
                'remarks' => $request->input('remarks'),
                'ref_no' => $refNo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Appointment booked successfully.',
                'appointment_id' => $id,
                'ref_no' => $refNo,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to book appointment. Please try again.'], 500);
        }
    }

    /**
     * Get patient's appointment history from eclinic database.
     * Joins with appointment_types and clinics for enriched data.
     */
    public function index(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        try {
            // Collect all portal patient IDs that share the same hospital number (hpercode)
            // This handles cases where appointments were linked to a different portal patient record
            $patientIds = [$patient->id];
            if ($patient->hpercode) {
                $linkedIds = DB::connection('portal')->table('patients')
                    ->where('hpercode', $patient->hpercode)
                    ->pluck('id')
                    ->toArray();
                $patientIds = array_unique(array_merge($patientIds, $linkedIds));
            }

            $appointments = DB::connection('portal')->table('appointments')
                ->leftJoin('appointment_types', 'appointments.appointment_type', '=', 'appointment_types.id')
                ->leftJoin('clinics', 'appointments.attending_clinic', '=', 'clinics.tscode')
                ->leftJoin('patients', 'appointments.patient_id', '=', 'patients.id')
                ->whereIn('appointments.patient_id', $patientIds)
                ->whereNull('appointments.deleted_at')
                ->orderByDesc('appointments.appointment_date')
                ->select(
                    'appointments.id',
                    'appointments.appointment_date',
                    'appointment_types.name as appointment_type_name',
                    'appointment_types.description as appointment_type_description',
                    'appointments.attending_clinic as clinic_code',
                    'clinics.clinic as clinic_name',
                    'clinics.contact_no as clinic_contact',
                    'appointments.doctor',
                    'appointments.remarks',
                    'appointments.plan',
                    'appointments.ref_no',
                    'appointments.queue_no',
                    'appointments.confirmed_by',
                    'appointments.created_at',
                    'patients.hpercode',
                    'patients.patlast',
                    'patients.patfirst',
                    'patients.patmiddle'
                )
                ->get()
                ->map(function ($appt) {
                    $patientName = trim(
                        ($appt->patlast ?? '') . ', ' .
                        ($appt->patfirst ?? '') . ' ' .
                        ($appt->patmiddle ?? '')
                    );

                    return [
                        'id' => $appt->id,
                        'appointment_date' => $appt->appointment_date,
                        'appointment_type' => $appt->appointment_type_name ?? 'General',
                        'appointment_type_description' => $appt->appointment_type_description,
                        'clinic_code' => $appt->clinic_code,
                        'clinic_name' => $appt->clinic_name,
                        'clinic_contact' => $appt->clinic_contact,
                        'attending_clinic' => $appt->clinic_name ?? $appt->clinic_code,
                        'doctor' => $appt->doctor,
                        'remarks' => $appt->remarks,
                        'plan' => $appt->plan,
                        'ref_no' => $appt->ref_no,
                        'queue_no' => $appt->queue_no,
                        'status' => $appt->confirmed_by ? 'Confirmed' : 'Pending',
                        'confirmed_by' => $appt->confirmed_by,
                        'created_at' => $appt->created_at,
                        'patient_name' => $patientName,
                        'hospital_number' => $appt->hpercode,
                    ];
                });

            return response()->json($appointments);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /**
     * Get details of a specific appointment including reschedules, files, and item charges.
     */
    public function show(Request $request, $id)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        try {
            $appointment = DB::connection('portal')->table('appointments')
                ->leftJoin('appointment_types', 'appointments.appointment_type', '=', 'appointment_types.id')
                ->leftJoin('clinics', 'appointments.attending_clinic', '=', 'clinics.tscode')
                ->leftJoin('patients', 'appointments.patient_id', '=', 'patients.id')
                ->where('appointments.id', $id)
                ->where('appointments.patient_id', $patient->id)
                ->whereNull('appointments.deleted_at')
                ->select(
                    'appointments.id',
                    'appointments.appointment_date',
                    'appointment_types.name as appointment_type_name',
                    'appointment_types.description as appointment_type_description',
                    'appointments.attending_clinic as clinic_code',
                    'clinics.clinic as clinic_name',
                    'clinics.contact_no as clinic_contact',
                    'appointments.doctor',
                    'appointments.remarks',
                    'appointments.plan',
                    'appointments.ref_no',
                    'appointments.queue_no',
                    'appointments.confirmed_by',
                    'appointments.created_at',
                    'patients.hpercode',
                    'patients.patlast',
                    'patients.patfirst',
                    'patients.patmiddle'
                )
                ->first();

            if (!$appointment) {
                return response()->json(['message' => 'Appointment not found.'], 404);
            }

            // Get reschedule history
            $reschedules = DB::connection('portal')->table('appointment_reschedules')
                ->where('appointment_id', $id)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->select('id', 'date_time as original_date', 'new_date_time as new_date', 'status', 'created_at')
                ->get()
                ->map(function ($r) {
                    return [
                        'id' => $r->id,
                        'original_date' => $r->original_date,
                        'new_date' => $r->new_date,
                        'status' => match ((int) $r->status) {
                            1 => 'Approved',
                            2 => 'Rejected',
                            default => 'Pending',
                        },
                        'created_at' => $r->created_at,
                    ];
                });

            // Get attached files
            $files = DB::connection('portal')->table('appointment_files')
                ->where('appointment_id', $id)
                ->select('id', 'file_type', 'url', 'created_at')
                ->get();

            // Get item charges with service details
            $itemCharges = DB::connection('portal')->table('appointment_item_charges')
                ->leftJoin('service_items', 'appointment_item_charges.service_item_id', '=', 'service_items.id')
                ->leftJoin('service_prices', function ($join) {
                    $join->on('service_prices.service_id', '=', 'service_items.id')
                        ->whereNull('service_prices.deleted_at');
                })
                ->where('appointment_item_charges.appointment_id', $id)
                ->select(
                    'appointment_item_charges.id',
                    'service_items.service_name',
                    'service_items.category',
                    'service_items.description as service_description',
                    'appointment_item_charges.qty',
                    'service_prices.price'
                )
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'service_name' => $item->service_name,
                        'category' => $item->category,
                        'description' => $item->service_description,
                        'quantity' => $item->qty,
                        'unit_price' => $item->price ? number_format((float) $item->price, 2, '.', '') : null,
                        'total_price' => $item->price ? number_format((float) $item->price * $item->qty, 2, '.', '') : null,
                    ];
                });

            $patientName = trim(
                ($appointment->patlast ?? '') . ', ' .
                ($appointment->patfirst ?? '') . ' ' .
                ($appointment->patmiddle ?? '')
            );

            return response()->json([
                'appointment' => [
                    'id' => $appointment->id,
                    'appointment_date' => $appointment->appointment_date,
                    'appointment_type' => $appointment->appointment_type_name ?? 'General',
                    'appointment_type_description' => $appointment->appointment_type_description,
                    'clinic_code' => $appointment->clinic_code,
                    'clinic_name' => $appointment->clinic_name,
                    'clinic_contact' => $appointment->clinic_contact,
                    'attending_clinic' => $appointment->clinic_name ?? $appointment->clinic_code,
                    'doctor' => $appointment->doctor,
                    'remarks' => $appointment->remarks,
                    'plan' => $appointment->plan,
                    'ref_no' => $appointment->ref_no,
                    'queue_no' => $appointment->queue_no,
                    'status' => $appointment->confirmed_by ? 'Confirmed' : 'Pending',
                    'confirmed_by' => $appointment->confirmed_by,
                    'created_at' => $appointment->created_at,
                    'patient_name' => $patientName,
                    'hospital_number' => $appointment->hpercode,
                ],
                'reschedules' => $reschedules,
                'files' => $files,
                'item_charges' => $itemCharges,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to load appointment details.'], 500);
        }
    }
}
