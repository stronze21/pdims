<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ApplyRolePreview
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            $request->session()->forget('role_preview_id');

            return $next($request);
        }

        $roles = $user->roles;
        $eligible = $roles->contains('name', 'Super Admin');
        $request->attributes->set('can_preview_roles', $eligible);
        $role = $eligible && $request->session()->has('role_preview_id')
            ? $this->findPreviewRole($request->session()->get('role_preview_id'))
            : null;

        if (! $role) {
            $request->session()->forget('role_preview_id');

            return $next($request);
        }

        $permissions = $user->getRelations()['permissions'] ?? null;
        $request->attributes->set('preview_role', $role);
        // Request-local relations make Gate, @role and Spatie checks agree.
        // Never assign or sync roles: the account's stored access stays intact.
        $user->setRelation('roles', new Collection([$role]));
        $user->setRelation('permissions', new Collection);

        try {
            return $next($request);
        } finally {
            $user->setRelation('roles', $roles);
            if ($permissions !== null) {
                $user->setRelation('permissions', $permissions);
            } else {
                $user->unsetRelation('permissions');
            }
        }
    }

    protected function findPreviewRole(mixed $id): ?Role
    {
        return Role::where('guard_name', 'web')->where('name', '!=', 'Super Admin')->find($id);
    }
}
