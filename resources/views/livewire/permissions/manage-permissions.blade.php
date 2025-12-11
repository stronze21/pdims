<div>
    <x-mary-header title="Permissions Management" separator>
        <x-slot:actions>
            <x-mary-button label="Add Permission" icon="o-plus" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Filters --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-mary-input wire:model.live.debounce.300ms="search" placeholder="Search permissions..." icon="o-magnifying-glass"
            clearable />

        <x-mary-select wire:model.live="perPage" :options="[
            ['id' => 15, 'name' => '15 per page'],
            ['id' => 30, 'name' => '30 per page'],
            ['id' => 50, 'name' => '50 per page'],
            ['id' => 100, 'name' => '100 per page'],
        ]" option-value="id" option-label="name" />
    </div>

    {{-- Permissions Table --}}
    <x-mary-card>
        <x-mary-table :headers="[
            ['key' => 'name', 'label' => 'Permission Name'],
            ['key' => 'roles_count', 'label' => 'Assigned Roles'],
            ['key' => 'users_count', 'label' => 'Direct Users'],
            ['key' => 'actions', 'label' => 'Actions', 'class' => 'w-32'],
        ]" :rows="$permissions" with-pagination>

            @scope('cell_name', $permission)
                <div class="flex items-center gap-2">
                    <x-mary-icon name="o-key" class="w-5 h-5 text-warning" />
                    <span class="font-mono text-sm">{{ $permission->name }}</span>
                </div>
            @endscope

            @scope('cell_roles_count', $permission)
                <x-mary-badge value="{{ $permission->roles_count }} {{ Str::plural('role', $permission->roles_count) }}"
                    class="badge-info" />
            @endscope

            @scope('cell_users_count', $permission)
                <x-mary-badge value="{{ $permission->users_count }} {{ Str::plural('user', $permission->users_count) }}"
                    class="badge-success" />
            @endscope

            @scope('cell_actions', $permission)
                <div class="flex gap-2">
                    <x-mary-button icon="o-pencil" wire:click="edit({{ $permission->id }})" tooltip="Edit"
                        class="btn-sm btn-ghost" />

                    @can('delete-permissions')
                        <x-mary-button icon="o-trash" wire:confirm="Are you sure you want to delete this permission?"
                            wire:click="$dispatch('delete-permission', { id: {{ $permission->id }} })" tooltip="Delete"
                            class="btn-sm btn-ghost text-error" />
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- Permission Form Modal --}}
    <x-mary-modal wire:model="permissionModal" title="{{ $permissionId ? 'Edit Permission' : 'Create Permission' }}"
        class="backdrop-blur">
        <x-mary-form wire:submit="save">
            <x-mary-input label="Permission Name" wire:model="name" icon="o-key"
                placeholder="e.g., view-users, create-orders" hint="Use kebab-case format (lowercase with dashes)"
                required />

            <x-mary-input label="Guard Name" wire:model="guard_name" icon="o-lock-closed" value="web" readonly
                hint="Authentication guard (usually 'web')" />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('permissionModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
