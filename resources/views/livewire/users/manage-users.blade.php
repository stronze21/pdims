<div>
    <x-mary-header title="User Management" separator>
        <x-slot:actions>
            @can('create-users')
                <x-button label="Add User" icon="o-plus" class="btn-primary" wire:click="create" />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    {{-- Filters --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-mary-input wire:model.live.debounce.300ms="search" placeholder="Search users..." icon="o-magnifying-glass"
            clearable />

        <x-mary-select wire:model.live="locationFilter" :options="$locations" placeholder="All Locations" option-value="id"
            option-label="description" icon="o-map-pin" clearable />

        <x-mary-select wire:model.live="roleFilter" :options="$roles" placeholder="All Roles" option-value="id"
            option-label="name" icon="o-shield-check" clearable />

        <x-mary-select wire:model.live="perPage" :options="[
            ['id' => 10, 'name' => '10 per page'],
            ['id' => 25, 'name' => '25 per page'],
            ['id' => 50, 'name' => '50 per page'],
            ['id' => 100, 'name' => '100 per page'],
        ]" option-value="id" option-label="name" />
    </div>

    {{-- Users Table --}}
    <x-mary-card>
        <x-mary-table :headers="[
            ['key' => 'employeeid', 'label' => 'Employee ID'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'roles', 'label' => 'Roles'],
            ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-32'],
        ]" :rows="$users" with-pagination>

            @scope('cell_employeeid', $user)
                <span class="font-semibold">{{ $user->employeeid }}</span>
            @endscope

            @scope('cell_name', $user)
                <div class="flex items-center gap-2">
                    <x-mary-avatar :image="$user->profile_photo_url" class="!w-8 !h-8" />
                    <span>{{ $user->name }}</span>
                </div>
            @endscope

            @scope('cell_location', $user)
                @if ($user->location)
                    <x-mary-badge value="{{ $user->location->description }}" class="badge-ghost" />
                @else
                    <span class="text-gray-400">N/A</span>
                @endif
            @endscope

            @scope('cell_roles', $user)
                <div class="flex flex-wrap gap-1">
                    @forelse($user->roles as $role)
                        <x-mary-badge value="{{ $role->name }}" class="badge-primary badge-sm" />
                    @empty
                        <span class="text-gray-400 text-sm">No roles</span>
                    @endforelse
                </div>
            @endscope

            @scope('cell_actions', $user)
                <div class="flex gap-2">
                    <x-mary-button icon="o-shield-check" wire:click="manageRoles({{ $user->id }})"
                        tooltip="Manage Roles" class="btn-sm btn-ghost" />

                    <x-mary-button icon="o-pencil" wire:click="edit({{ $user->id }})" tooltip="Edit"
                        class="btn-sm btn-ghost" />

                    @can('delete-users')
                        @if ($user->id !== auth()->id())
                            <x-mary-button icon="o-trash" wire:confirm="Are you sure you want to delete this user?"
                                wire:click="$dispatch('delete-user', { id: {{ $user->id }} })" tooltip="Delete"
                                class="btn-sm btn-ghost text-error" />
                        @endif
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- User Form Modal --}}
    <x-mary-modal wire:model="userModal" title="{{ $userId ? 'Edit User' : 'Create User' }}" class="backdrop-blur">
        <x-mary-form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-mary-input label="Employee ID" wire:model="employeeid" icon="o-identification" required />

                <x-mary-input label="Name" wire:model="name" icon="o-user" required />

                <x-mary-input label="Email" wire:model="email" type="email" icon="o-envelope" class="md:col-span-2"
                    required />

                <x-mary-select label="Location" wire:model="pharm_location_id" :options="$locations" option-value="id"
                    option-label="description" icon="o-map-pin" class="md:col-span-2" required />

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-2">Roles</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($roles as $role)
                            <x-mary-checkbox label="{{ $role->name }}" wire:model="selectedRoles"
                                value="{{ $role->id }}" />
                        @endforeach
                    </div>
                </div>

                <x-mary-input label="Password {{ $userId ? '(leave blank to keep current)' : '' }}"
                    wire:model="password" type="password" icon="o-lock-closed"
                    hint="{{ $userId ? 'Leave blank to keep current password' : 'Minimum 8 characters' }}" />

                <x-mary-input label="Confirm Password" wire:model="password_confirmation" type="password"
                    icon="o-lock-closed" />
            </div>

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('userModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Role Assignment Modal --}}
    <x-mary-modal wire:model="roleModal" title="Manage User Roles" class="backdrop-blur">
        <div class="space-y-3">
            @foreach ($roles as $role)
                <x-mary-checkbox label="{{ $role->name }}" wire:model="userRoles" value="{{ $role->id }}" />
            @endforeach
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('roleModal', false)" />
            <x-mary-button label="Save Roles" wire:click="saveRoles" class="btn-primary" spinner="saveRoles" />
        </x-slot:actions>
    </x-mary-modal>
</div>
