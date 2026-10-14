<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RolePreviewController extends Controller
{
    public function store(Request $request)
    {
        // Check persisted membership, even while the effective role is restricted.
        abort_unless($request->user()->roles()->where('name', 'Super Admin')->exists(), 403);
        $data = $request->validate(['role_id' => ['required', 'integer']]);
        $role = Role::where('guard_name', 'web')->where('name', '!=', 'Super Admin')
            ->findOrFail($data['role_id']);
        $request->session()->put('role_preview_id', $role->id);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request)
    {
        $request->session()->forget('role_preview_id');

        return redirect()->route('dashboard');
    }
}
