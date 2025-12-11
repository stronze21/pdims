<?php

namespace App\Livewire\Roles;

use App\Models\Role;
use Mary\Traits\Toast;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use Spatie\Permission\Models\Permission;

class ManageRoles extends Component
{
    use WithPagination;
    use Toast;

    public $search = '';
    public $perPage = 10;

    // Role form
    public $roleModal = false;
    public $roleId = null;
    public $name = '';
    public $guard_name = 'web';

    // Permission assignment modal
    public $permissionModal = false;
    public $permissionRoleId = null;
    public $selectedPermissions = [];

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function mount() {}

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function getRolesProperty()
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function getPermissionsProperty()
    {
        return Permission::orderBy('name')->get();
    }

    public function create()
    {
        $this->resetForm();
        $this->roleModal = true;
    }

    public function edit(Role $role)
    {
        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->guard_name = $role->guard_name;
        $this->roleModal = true;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255|unique:roles,name,' . $this->roleId,
            'guard_name' => 'required|string',
        ];

        $this->validate($rules);

        $roleData = [
            'name' => $this->name,
            'guard_name' => $this->guard_name,
        ];

        if ($this->roleId) {
            $role = Role::findOrFail($this->roleId);
            $role->update($roleData);
            $message = 'Role updated successfully';
        } else {
            Role::create($roleData);
            $message = 'Role created successfully';
        }

        $this->success($message);
        $this->resetForm();
        $this->roleModal = false;
    }

    public function managePermissions(Role $role)
    {
        $this->permissionRoleId = $role->id;
        $this->selectedPermissions = $role->permissions->pluck('id')->toArray();
        $this->permissionModal = true;
    }

    public function savePermissions()
    {

        $this->validate([
            'selectedPermissions' => 'array',
        ]);

        $role = Role::findOrFail($this->permissionRoleId);
        $role->syncPermissions($this->selectedPermissions);

        $this->success('Permissions updated successfully');
        $this->permissionModal = false;
    }

    #[On('delete-role')]
    public function delete($id)
    {
        $this->authorize('delete-roles');

        $role = Role::findOrFail($id);

        if ($role->users()->count() > 0) {
            $this->error('Cannot delete role that is assigned to users');
            return;
        }

        $role->delete();
        $this->success('Role deleted successfully');
    }

    public function resetForm()
    {
        $this->roleId = null;
        $this->name = '';
        $this->guard_name = 'web';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.roles.manage-roles', [
            'roles' => $this->roles,
            'permissions' => $this->permissions,
        ]);
    }
}
