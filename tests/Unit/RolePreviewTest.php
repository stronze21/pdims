<?php

namespace Tests\Unit;

use App\Http\Middleware\ApplyRolePreview;
use App\Http\Controllers\RolePreviewController;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RolePreviewTest extends TestCase
{
    private function requestFor(?User $user): Request
    {
        $request = Request::create('/dashboard');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(new Store('preview-test', new ArraySessionHandler(120)));
        $request->session()->put('role_preview_id', 2);

        return $request;
    }

    private function middleware(?Role $role): ApplyRolePreview
    {
        return new class($role) extends ApplyRolePreview {
            public function __construct(private ?Role $role) {}

            protected function findPreviewRole(mixed $id): ?Role
            {
                return $this->role;
            }
        };
    }

    public function test_preview_restricts_roles_and_direct_permissions_and_restores_them(): void
    {
        $admin = new Role(['name' => 'Super Admin', 'guard_name' => 'web']);
        $role = new Role(['name' => 'Viewer', 'guard_name' => 'web']);
        $role->id = 2;
        $allowed = (new Permission)->forceFill(['name' => 'inventory-viewer', 'guard_name' => 'web']);
        $allowed->id = 10;
        $denied = (new Permission)->forceFill(['name' => 'view-settings', 'guard_name' => 'web']);
        $denied->id = 11;
        $allowed->setRelation('roles', new Collection([$role]));
        $denied->setRelation('roles', new Collection);
        $role->setRelation('permissions', new Collection([$allowed]));
        $user = new User;
        $roles = new Collection([$admin]);
        $permissions = new Collection([$denied]);
        $user->setRelation('roles', $roles)->setRelation('permissions', $permissions);
        $request = $this->requestFor($user);

        $this->middleware($role)->handle($request, function ($request) use ($user, $allowed, $denied) {
            $this->assertTrue($request->attributes->get('can_preview_roles'));
            $this->assertTrue($user->hasRole('Viewer'));
            $this->assertFalse($user->hasRole('Super Admin'));
            $this->assertTrue($user->hasPermissionTo($allowed));
            $this->assertFalse($user->hasPermissionTo($denied));

            return new Response;
        });

        $this->assertSame($roles, $user->roles);
        $this->assertSame($permissions, $user->permissions);
    }

    public function test_non_admin_and_guest_cannot_activate_preview_through_session(): void
    {
        $user = new User;
        $user->setRelation('roles', new Collection);
        foreach ([$user, null] as $actor) {
            $request = $this->requestFor($actor);
            $this->middleware(new Role(['name' => 'Viewer']))->handle($request, fn () => new Response);
            $this->assertFalse($request->session()->has('role_preview_id'));
            $this->assertNull($request->attributes->get('preview_role'));
        }
    }

    public function test_deleted_role_clears_preview(): void
    {
        $user = new User;
        $user->setRelation('roles', new Collection([new Role(['name' => 'Super Admin'])]));
        $request = $this->requestFor($user);
        $this->middleware(null)->handle($request, fn () => new Response);
        $this->assertFalse($request->session()->has('role_preview_id'));
        $this->assertTrue($user->hasRole('Super Admin'));
    }

    public function test_roles_are_restored_when_the_previewed_page_throws(): void
    {
        $user = new User;
        $user->setRelation('roles', new Collection([new Role(['name' => 'Super Admin'])]));
        try {
            $this->middleware(new Role(['name' => 'Viewer']))->handle($this->requestFor($user), function () {
                throw new \RuntimeException('Forbidden page');
            });
        } catch (\RuntimeException $error) {
            $this->assertSame('Forbidden page', $error->getMessage());
        }
        $this->assertTrue($user->hasRole('Super Admin'));
        $this->assertFalse($user->relationLoaded('permissions'));
    }

    public function test_exit_clears_session_and_returns_to_dashboard(): void
    {
        $request = $this->requestFor(new User);
        $response = (new RolePreviewController)->destroy($request);
        $this->assertFalse($request->session()->has('role_preview_id'));
        $this->assertSame(route('dashboard'), $response->getTargetUrl());
    }

    public function test_start_rejects_non_admin_using_persisted_membership(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $membership = \Mockery::mock(\Illuminate\Database\Eloquent\Relations\MorphToMany::class);
        $user->shouldReceive('roles')->once()->andReturn($membership);
        $membership->shouldReceive('where')->with('name', 'Super Admin')->once()->andReturnSelf();
        $membership->shouldReceive('exists')->once()->andReturnFalse();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new RolePreviewController)->store($this->requestFor($user));
    }
}
