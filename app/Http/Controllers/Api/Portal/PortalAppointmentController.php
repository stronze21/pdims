<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalAppointmentController extends Controller
{
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
            $appointments = DB::connection('portal')->table('appointments')
                ->leftJoin('appointment_types', 'appointments.appointment_type', '=', 'appointment_types.id')
                ->leftJoin('clinics', 'appointments.attending_clinic', '=', 'clinics.tscode')
                ->leftJoin('patients', 'appointments.patient_id', '=', 'patients.id')
                ->where('appointments.patient_id', $patient->id)
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
