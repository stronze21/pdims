<?php

namespace App\Livewire\Permissions;

use Mary\Traits\Toast;
use Livewire\Component;
use App\Models\Permission;
use Livewire\Attributes\On;
use Livewire\WithPagination;

class ManagePermissions extends Component
{
    use WithPagination;
    use Toast;

    public $search = '';
    public $perPage = 15;

    // Permission form
    public $permissionModal = false;
    public $permissionId = null;
    public $name = '';
    public $guard_name = 'web';

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function mount() {}

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function getPermissionsProperty()
    {
        return Permission::query()
            ->withCount(['roles', 'users'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function create()
    {
        $this->resetForm();
        $this->permissionModal = true;
    }

    public function edit(Permission $permission)
    {
        $this->permissionId = $permission->id;
        $this->name = $permission->name;
        $this->guard_name = $permission->guard_name;
        $this->permissionModal = true;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255|unique:permissions,name,' . $this->permissionId,
            'guard_name' => 'required|string',
        ];

        $this->validate($rules);

        $permissionData = [
            'name' => $this->name,
            'guard_name' => $this->guard_name,
        ];

        if ($this->permissionId) {
            $permission = Permission::findOrFail($this->permissionId);
            $permission->update($permissionData);
            $message = 'Permission updated successfully';
        } else {
            Permission::create($permissionData);
            $message = 'Permission created successfully';
        }

        $this->success($message);
        $this->resetForm();
        $this->permissionModal = false;
    }

    #[On('delete-permission')]
    public function delete($id)
    {
        $this->authorize('delete-permissions');

        $permission = Permission::findOrFail($id);

        if ($permission->roles()->count() > 0 || $permission->users()->count() > 0) {
            $this->error('Cannot delete permission that is assigned to roles or users');
            return;
        }

        $permission->delete();
        $this->success('Permission deleted successfully');
    }

    public function resetForm()
    {
        $this->permissionId = null;
        $this->name = '';
        $this->guard_name = 'web';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.permissions.manage-permissions', [
            'permissions' => $this->permissions,
        ]);
    }
}
