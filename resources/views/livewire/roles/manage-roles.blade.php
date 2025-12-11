<div>
    <x-mary-header title="Roles & Permissions" separator>
        <x-slot:actions>
            @can('create-roles')
                <x-mary-button label="Add Role" icon="o-plus" class="btn-primary" wire:click="create" />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    {{-- Filters --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-mary-input wire:model.live.debounce.300ms="search" placeholder="Search roles..." icon="o-magnifying-glass"
            clearable />

        <x-mary-select wire:model.live="perPage" :options="[
            ['id' => 10, 'name' => '10 per page'],
            ['id' => 25, 'name' => '25 per page'],
            ['id' => 50, 'name' => '50 per page'],
            ['id' => 100, 'name' => '100 per page'],
        ]" option-value="id" option-label="name" />
    </div>

    {{-- Roles Table --}}
    <x-mary-card>
        <x-mary-table :headers="[
            ['key' => 'name', 'label' => 'Role Name'],
            ['key' => 'users_count', 'label' => 'Users'],
            ['key' => 'permissions_count', 'label' => 'Permissions'],
            ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-40'],
        ]" :rows="$roles" with-pagination>

            @scope('cell_name', $role)
                <div class="flex items-center gap-2">
                    <x-mary-icon name="o-shield-check" class="w-5 h-5 text-primary" />
                    <span class="font-semibold">{{ $role->name }}</span>
                </div>
            @endscope

            @scope('cell_users_count', $role)
                <x-mary-badge value="{{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}"
                    class="badge-info" />
            @endscope

            @scope('cell_permissions_count', $role)
                <x-mary-badge
                    value="{{ $role->permissions_count }} {{ Str::plural('permission', $role->permissions_count) }}"
                    class="badge-success" />
            @endscope

            @scope('cell_actions', $role)
                <div class="flex gap-2">
                    <x-mary-button icon="o-key" wire:click="managePermissions({{ $role->id }})"
                        tooltip="Manage Permissions" class="btn-sm btn-ghost" />

                    <x-mary-button icon="o-pencil" wire:click="edit({{ $role->id }})" tooltip="Edit"
                        class="btn-sm btn-ghost" />

                    @can('delete-roles')
                        <x-mary-button icon="o-trash" wire:confirm="Are you sure you want to delete this role?"
                            wire:click="$dispatch('delete-role', { id: {{ $role->id }} })" tooltip="Delete"
                            class="btn-sm btn-ghost text-error" />
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- Role Form Modal --}}
    <x-mary-modal wire:model="roleModal" title="{{ $roleId ? 'Edit Role' : 'Create Role' }}" class="backdrop-blur">
        <x-mary-form wire:submit="save">
            <x-mary-input label="Role Name" wire:model="name" icon="o-shield-check"
                placeholder="e.g., Pharmacist, Admin" hint="Use a descriptive name for the role" required />

            <x-mary-input label="Guard Name" wire:model="guard_name" icon="o-lock-closed" value="web" readonly
                hint="Authentication guard (usually 'web')" />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('roleModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Permission Assignment Modal --}}
    <x-mary-modal wire:model="permissionModal" title="Manage Role Permissions" class="backdrop-blur"
        box-class="max-w-3xl">
        <div class="space-y-4">
            <x-mary-input placeholder="Search permissions..." icon="o-magnifying-glass" x-data="{ search: '' }"
                x-model="search" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-96 overflow-y-auto">
                @foreach ($permissions->groupBy(fn($p) => Str::before($p->name, '-')) as $group => $groupPermissions)
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold text-sm uppercase text-gray-600 mb-3">{{ Str::title($group) }}</h3>
                        <div class="space-y-2">
                            @foreach ($groupPermissions as $permission)
                                <x-mary-checkbox label="{{ Str::title(Str::replace('-', ' ', $permission->name)) }}"
                                    wire:model="selectedPermissions" value="{{ $permission->id }}"
                                    x-show="'{{ strtolower($permission->name) }}'.includes(search.toLowerCase())" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('permissionModal', false)" />
            <x-mary-button label="Save Permissions" wire:click="savePermissions" class="btn-primary"
                spinner="savePermissions" />
        </x-slot:actions>
    </x-mary-modal>
</div>
