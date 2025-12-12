<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApiAuthController extends Controller
{
    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Load user relationships
        $user->load(['location', 'roles.permissions', 'permissions']);

        // Create token
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'employeeid' => $user->employeeid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile_photo_url' => $user->profile_photo_url,
                    'location' => $user->location,
                    'roles' => $user->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                        ];
                    }),
                    'permissions' => $user->getAllPermissions()->map(function ($permission) {
                        return $permission->name;
                    })->values(),
                ],
            ],
        ], 200);
    }

    /**
     * Get authenticated user information
     */
    public function user(Request $request)
    {
        $user = $request->user();
        $user->load(['location', 'roles.permissions', 'permissions']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'employeeid' => $user->employeeid,
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo_url' => $user->profile_photo_url,
                'location' => $user->location,
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                }),
                'permissions' => $user->getAllPermissions()->map(function ($permission) {
                    return $permission->name;
                })->values(),
            ],
        ], 200);
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ], 200);
    }

    /**
     * Revoke all tokens for the user
     */
    public function logoutAll(Request $request)
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully',
        ], 200);
    }

    /**
     * Check if user has permission
     */
    public function checkPermission(Request $request)
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $hasPermission = $request->user()->hasPermissionTo($request->permission);

        return response()->json([
            'success' => true,
            'data' => [
                'has_permission' => $hasPermission,
            ],
        ], 200);
    }

    /**
     * Check if user has role
     */
    public function checkRole(Request $request)
    {
        $request->validate([
            'role' => 'required|string',
        ]);

        $hasRole = $request->user()->hasRole($request->role);

        return response()->json([
            'success' => true,
            'data' => [
                'has_role' => $hasRole,
            ],
        ], 200);
    }
}
