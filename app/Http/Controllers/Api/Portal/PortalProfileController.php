<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\PortalPatient;
use App\Models\Portal\PortalPatientFamily;
use App\Models\Record\Patients\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PortalProfileController extends Controller
{
    /**
     * Get the authenticated patient's full profile.
     */
    public function profile(Request $request)
    {
        $account = $request->user();
        $account->load('patient');
        $patient = $account->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        return response()->json([
            'patient' => $patient,
            'addresses' => $patient->addresses,
            'family' => $patient->families,
            'vitals' => $this->getVitals($patient),
            'medical_info' => $patient->additionalMedInfos,
        ]);
    }

    /**
     * Get patient addresses.
     */
    public function addresses(Request $request)
    {
        $patient = $request->user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        return response()->json($patient->addresses);
    }

    /**
     * Get patient family members.
     */
    public function family(Request $request)
    {
        $patient = $request->user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        return response()->json($patient->families);
    }

    /**
     * Get patient vitals with vital sign details.
     * Combines eclinic patient_vitals and hospital hvitalsign records.
     */
    public function vitals(Request $request)
    {
        $patient = $request->user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        $allVitals = $this->getVitals($patient);

        return response()->json($allVitals);
    }

    /**
     * Fetch vitals from eclinic and hospital databases, merged and sorted.
     */
    private function getVitals($patient)
    {
        // Eclinic vitals (patient_vitals table)
        $portalVitals = $patient->vitals()
            ->with('vitalSign')
            ->orderByDesc('vitals_date')
            ->get()
            ->map(function ($vital) {
                return [
                    'id' => $vital->id,
                    'vital_sign' => $vital->vitalSign?->vital_sign,
                    'description' => $vital->vitalSign?->description,
                    'value' => $vital->value,
                    'unit' => $vital->vitalSign?->unit,
                    'vitals_date' => $vital->vitals_date,
                    'remarks' => $vital->remarks,
                    'source' => 'eclinic',
                ];
            });

        // Hospital vitals (hvitalsign table) — keyed by hpercode
        $hospitalVitals = collect();
        if ($patient->hpercode) {
            $hvitals = DB::connection('hospital')->select("
                SELECT
                    enccode,
                    hpercode,
                    datelog,
                    timelog,
                    vsbp,
                    vstemp,
                    vspulse,
                    vsresp
                FROM hospital.dbo.hvitalsign WITH (NOLOCK)
                WHERE hpercode = ?
                ORDER BY datelog DESC, timelog DESC
            ", [$patient->hpercode]);

            foreach ($hvitals as $hv) {
                $dateTime = $hv->datelog;

                if (!empty($hv->vsbp)) {
                    $hospitalVitals->push([
                        'id' => null,
                        'vital_sign' => 'BP',
                        'description' => 'Blood Pressure',
                        'value' => $hv->vsbp,
                        'unit' => 'mmHg',
                        'vitals_date' => $dateTime,
                        'remarks' => null,
                        'source' => 'hospital',
                    ]);
                }
                if (!empty($hv->vstemp)) {
                    $hospitalVitals->push([
                        'id' => null,
                        'vital_sign' => 'Temp',
                        'description' => 'Temperature',
                        'value' => $hv->vstemp,
                        'unit' => '°C',
                        'vitals_date' => $dateTime,
                        'remarks' => null,
                        'source' => 'hospital',
                    ]);
                }
                if (!empty($hv->vspulse)) {
                    $hospitalVitals->push([
                        'id' => null,
                        'vital_sign' => 'Pulse',
                        'description' => 'Pulse Rate',
                        'value' => $hv->vspulse,
                        'unit' => 'bpm',
                        'vitals_date' => $dateTime,
                        'remarks' => null,
                        'source' => 'hospital',
                    ]);
                }
                if (!empty($hv->vsresp)) {
                    $hospitalVitals->push([
                        'id' => null,
                        'vital_sign' => 'Resp',
                        'description' => 'Respiratory Rate',
                        'value' => $hv->vsresp,
                        'unit' => 'breaths/min',
                        'vitals_date' => $dateTime,
                        'remarks' => null,
                        'source' => 'hospital',
                    ]);
                }
            }
        }

        return $portalVitals->concat($hospitalVitals)
            ->sortByDesc('vitals_date')
            ->values();
    }

    /**
     * Get patient additional medical info.
     */
    public function medicalInfo(Request $request)
    {
        $patient = $request->user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        return response()->json($patient->additionalMedInfos);
    }

    /**
     * Update patient profile (contact, email, address).
     */
    public function update(Request $request)
    {
        $request->validate([
            'patcontactno' => 'nullable|string|max:100',
            'patemail' => 'nullable|email|max:150',
            'pataddress' => 'nullable|string|max:500',
        ]);

        $patient = $request->user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        $patient->update($request->only(['patcontactno', 'patemail', 'pataddress']));

        return response()->json([
            'message' => 'Profile updated successfully.',
            'patient' => $patient->fresh(),
        ]);
    }

    /**
     * Add a family member.
     * First searches eclinic patients table, then falls back to hospital hperson table.
     */
    public function addFamily(Request $request)
    {
        $request->validate([
            'hospital_no' => 'required|string|max:50',
            'patbdate' => 'required|date',
            'relation' => 'required|string|max:20',
        ]);

        $patient = $request->user()->patient;

        if (!$patient) {
            return response()->json(['message' => 'No linked patient record found.'], 404);
        }

        // Step 1: Search in eclinic patients table (portal connection)
        $familyPatient = PortalPatient::where('hpercode', $request->hospital_no)
            ->where('patbdate', $request->patbdate)
            ->first();

        $name = null;
        $birthdate = null;
        $civilstat = null;

        if ($familyPatient) {
            $name = trim("{$familyPatient->patlast}, {$familyPatient->patfirst}" .
                ($familyPatient->patmiddle ? ' ' . mb_substr($familyPatient->patmiddle, 0, 1) . '.' : ''));
            $birthdate = $familyPatient->patbdate;
            $civilstat = $familyPatient->patcivilstat ?? 'N/A';
        } else {
            // Step 2: Fallback to hospital hperson table using hpatcode (hospital number)
            $hospitalPatient = Patient::where('hpatcode', $request->hospital_no)
                ->where('patbdate', $request->patbdate)
                ->first();

            if (!$hospitalPatient) {
                throw ValidationException::withMessages([
                    'hospital_no' => ['No matching patient record found. Please verify the hospital number and birth date.'],
                ]);
            }

            $name = trim("{$hospitalPatient->patlast}, {$hospitalPatient->patfirst}" .
                ($hospitalPatient->patmiddle ? ' ' . mb_substr($hospitalPatient->patmiddle, 0, 1) . '.' : ''));
            $birthdate = $hospitalPatient->patbdate;
            $civilstat = $hospitalPatient->csstat();
        }

        // Check if this family member already exists
        $existing = PortalPatientFamily::where('patient_id', $patient->id)
            ->where('name', $name)
            ->where('birthdate', $birthdate)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'hospital_no' => ['This family member is already linked to your profile.'],
            ]);
        }

        $familyMember = PortalPatientFamily::create([
            'patient_id' => $patient->id,
            'name' => $name,
            'birthdate' => $birthdate,
            'civilstat' => $civilstat,
            'relation' => $request->relation,
        ]);

        return response()->json([
            'message' => 'Family member added successfully.',
            'family_member' => $familyMember,
        ], 201);
    }
}
