<?php

namespace App\Livewire\Users;

use App\Models\PharmLocation;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class ManageUsers extends Component
{
    use WithPagination;
    use Toast;

    public $search = '';
    public $locationFilter = null;
    public $roleFilter = null;
    public $perPage = 10;

    // User form
    public $userModal = false;
    public $userId = null;
    public $employeeid = '';
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $pharm_location_id = null;
    public $selectedRoles = [];

    // Role assignment modal
    public $roleModal = false;
    public $roleUserId = null;
    public $userRoles = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'locationFilter' => ['except' => null],
        'roleFilter' => ['except' => null],
    ];

    public function mount() {}

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingLocationFilter()
    {
        $this->resetPage();
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
    }

    public function getLocationsProperty()
    {
        return PharmLocation::orderBy('description')->get();
    }

    public function getRolesProperty()
    {
        return Role::orderBy('name')->get();
    }

    public function getUsersProperty()
    {
        return User::query()
            ->with(['location', 'roles'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('employeeid', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->locationFilter, function ($query) {
                $query->where('pharm_location_id', $this->locationFilter);
            })
            ->when($this->roleFilter, function ($query) {
                $query->whereHas('roles', function ($q) {
                    $q->where('id', $this->roleFilter);
                });
            })
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function create()
    {
        $this->resetForm();
        $this->userModal = true;
    }

    public function edit(User $user)
    {
        $this->userId = $user->id;
        $this->employeeid = $user->employeeid;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->pharm_location_id = $user->pharm_location_id;
        $this->selectedRoles = $user->roles->pluck('id')->toArray();
        $this->password = '';
        $this->password_confirmation = '';
        $this->userModal = true;
    }

    public function save()
    {
        $rules = [
            'employeeid' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:hospital2.dbo.pharm_users,email,' . $this->userId,
            'pharm_location_id' => 'required|exists:hospital2.dbo.pharm_locations,id',
            'selectedRoles' => 'array',
        ];

        if ($this->userId) {
            $rules['password'] = 'nullable|min:6|confirmed';
        } else {
            $rules['password'] = 'required|min:6|confirmed';
        }

        $this->validate($rules);

        $userData = [
            'employeeid' => $this->employeeid,
            'name' => $this->name,
            'email' => $this->email,
            'pharm_location_id' => $this->pharm_location_id,
        ];

        if ($this->password) {
            $userData['password'] = Hash::make($this->password);
        }

        if ($this->userId) {
            $user = User::findOrFail($this->userId);
            $user->update($userData);
            $message = 'User updated successfully';
        } else {
            $user = User::create($userData);
            $message = 'User created successfully';
        }

        // Sync roles
        $user->syncRoles($this->selectedRoles);

        $this->success($message);
        $this->resetForm();
        $this->userModal = false;
    }

    public function manageRoles(User $user)
    {
        $this->roleUserId = $user->id;
        $this->userRoles = $user->roles->pluck('id')->toArray();
        $this->roleModal = true;
    }

    public function saveRoles()
    {

        $this->validate([
            'userRoles' => 'array',
        ]);

        $user = User::findOrFail($this->roleUserId);
        $user->syncRoles($this->userRoles);

        $this->success('Roles updated successfully');
        $this->roleModal = false;
    }

    #[On('delete-user')]
    public function delete($id)
    {
        $this->authorize('delete-users');

        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            $this->error('You cannot delete your own account');
            return;
        }

        $user->delete();
        $this->success('User deleted successfully');
    }

    public function resetForm()
    {
        $this->userId = null;
        $this->employeeid = '';
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->pharm_location_id = null;
        $this->selectedRoles = [];
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.users.manage-users', [
            'users' => $this->users,
            'locations' => $this->locations,
            'roles' => $this->roles,
        ]);
    }
}
