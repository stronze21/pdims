<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\PortalUser;
use App\Models\Record\Patients\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PortalAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'sometimes|string',
        ]);

        $user = PortalUser::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->isPending()) {
            return response()->json([
                'message' => 'Your account is still pending verification. Please wait for hospital staff to verify your registration.',
            ], 403);
        }

        if ($user->isRejected()) {
            return response()->json([
                'message' => 'Your registration has been rejected. Reason: ' . ($user->reject_reason ?? 'N/A'),
            ], 403);
        }

        $deviceName = $request->device_name ?? 'Salun-at App';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful',
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'patlast' => 'required|string|max:50',
            'patfirst' => 'required|string|max:50',
            'patmiddle' => 'nullable|string|max:50',
            'patsuffix' => 'nullable|string|max:10',
            'email' => 'required|email|max:100|unique:portal.portal_users,email',
            'contact_no' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'patbdate' => 'required|date',
            'patsex' => 'required|in:M,F',
        ]);

        // Try to find existing patient record in hospital database
        $patient = Patient::where('patlast', 'LIKE', $request->patlast)
            ->where('patfirst', 'LIKE', $request->patfirst)
            ->where('patbdate', $request->patbdate)
            ->first();

        $portalUser = PortalUser::create([
            'hpercode' => $patient?->hpercode,
            'patlast' => $request->patlast,
            'patfirst' => $request->patfirst,
            'patmiddle' => $request->patmiddle,
            'patsuffix' => $request->patsuffix,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'password' => $request->password,
            'patbdate' => $request->patbdate,
            'patsex' => $request->patsex,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => $patient
                ? 'Registration successful. Your hospital record was found. Please wait for verification.'
                : 'Registration successful. No existing hospital record found. Hospital staff will assign your hospital number upon verification.',
            'has_existing_record' => (bool) $patient,
        ], 201);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
