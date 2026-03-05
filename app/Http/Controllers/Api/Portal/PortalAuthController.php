<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\PortalPatient;
use App\Models\Portal\PortalUserAccount;
use App\Models\Record\Patients\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PortalAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'pin' => 'required|string',
            'device_name' => 'sometimes|string',
        ]);

        $account = PortalUserAccount::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();

        // Normalize $2a$ bcrypt hashes to $2y$ (PHP-compatible variant)
        $pin = $account?->pin;
        if ($pin && str_starts_with($pin, '$2a$')) {
            $pin = '$2y$' . substr($pin, 4);
        }

        if (!$account || !Hash::check($request->pin, $pin)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials are incorrect.'],
            ]);
        }

        $account->load('patient');

        $deviceName = $request->device_name ?? 'Salun-at App';
        $token = $account->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $account,
            'patient' => $account->patient,
            'token' => $token,
            'message' => 'Login successful',
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'hospital_no' => 'required|string|max:50',
            'patbdate' => 'required|date',
            'patcontactno' => 'nullable|string|max:100',
            'email' => 'required|email|max:150|unique:portal.app_user_accounts,email',
            'username' => 'required|string|max:100|unique:portal.app_user_accounts,username',
            'pin' => 'required|string|min:4|confirmed',
        ]);

        // Verify against the hospital database using Hospital Number + Birthdate
        $hospitalPatient = Patient::where('hpatcode', $request->hospital_no)
            ->where('patbdate', $request->patbdate)
            ->first();

        if (!$hospitalPatient) {
            throw ValidationException::withMessages([
                'hospital_no' => ['No matching hospital record found. Please verify your hospital number and birth date.'],
            ]);
        }

        // Check if this patient already has a portal account
        $existingPortalPatient = PortalPatient::where('hpercode', $hospitalPatient->hpercode)->first();

        if ($existingPortalPatient && $existingPortalPatient->userAccount) {
            throw ValidationException::withMessages([
                'hospital_no' => ['An account already exists for this hospital record.'],
            ]);
        }

        // Sync patient data from hospital to portal database
        $portalPatient = $existingPortalPatient ?? PortalPatient::create([
            'hpercode' => $hospitalPatient->hpercode,
            'patlast' => $hospitalPatient->patlast,
            'patfirst' => $hospitalPatient->patfirst,
            'patmiddle' => $hospitalPatient->patmiddle,
            'patbdate' => $hospitalPatient->patbdate,
            'patgender' => $hospitalPatient->patsex === 'M' ? 'Male' : 'Female',
            'patcontactno' => $request->patcontactno ?? $hospitalPatient->pattelno,
            'patemail' => $request->email,
        ]);

        // Create user account
        PortalUserAccount::create([
            'patient_id' => $portalPatient->id,
            'username' => $request->username,
            'email' => $request->email,
            'pin' => Hash::make($request->pin),
        ]);

        return response()->json([
            'message' => 'Registration successful. Your hospital record has been verified and linked.',
        ], 201);
    }

    public function user(Request $request)
    {
        $account = $request->user();
        $account->load('patient');

        return response()->json([
            'user' => $account,
            'patient' => $account->patient,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
