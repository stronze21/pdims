<div class="flex flex-col px-5 py-5 mx-auto max-w-screen">
    <x-mary-header title="Manage Zero Billing Charges" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center space-x-2 px-3 py-1 bg-white rounded-lg shadow-sm border border-gray-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-semibold text-gray-700">{{ $charges->count() }} Configured</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-button label="Add Zero-billing Charge" icon="o-plus"
                class="btn-sm btn-primary shadow-md hover:shadow-lg transition-shadow" wire:click="openAddModal" />
        </x-slot:actions>
    </x-mary-header>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                    <tr>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Source of Fund</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Description</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Status</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Created By</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Updated By</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($charges as $charge)
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100">
                            <td class="py-4 px-4">
                                <div class="flex items-center space-x-2">
                                    <div
                                        class="w-2 h-2 rounded-full {{ $charge->is_active ? 'bg-green-500' : 'bg-gray-400' }}">
                                    </div>
                                    <span class="font-bold text-gray-900 text-sm">{{ $charge->chrgcode }}</span>
                                </div>
                            </td>
                            <td class="py-4 px-4">
                                <span class="text-gray-700 text-sm">{{ $charge->chargeCode->chrgdesc }}</span>
                            </td>
                            <td class="py-4 px-4">
                                <button wire:click="toggleActive({{ $charge->id }})"
                                    class="badge badge-sm shadow-sm {{ $charge->is_active ? 'badge-success' : 'badge-ghost' }}">
                                    {{ $charge->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="py-4 px-4">
                                <span class="text-gray-600 text-xs">{{ $charge->createdBy->name ?? 'System' }}</span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="text-gray-600 text-xs">{{ $charge->updatedBy->name ?? 'N/A' }}</span>
                            </td>
                            <td class="py-4 px-4">
                                <div class="flex justify-center space-x-2">
                                    <button class="btn btn-xs btn-warning hover:scale-105 shadow-md transition-all"
                                        wire:click="openEditModal({{ $charge->id }})">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button class="btn btn-xs btn-error hover:scale-105 shadow-md transition-all"
                                        wire:click="openDeleteModal({{ $charge->id }})">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-300 mb-4"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <span class="text-xl font-bold text-gray-400">No zero billing charges
                                        configured</span>
                                    <span class="text-sm text-gray-400 mt-2">Click "Add Charge Code" to get
                                        started</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Add Modal --}}
    <x-mary-modal wire:model="addModal" title="Add Zero Billing Charge" class="backdrop-blur">
        <x-mary-form wire:submit="save">
            <x-mary-select label="Charge Code" wire:model="chrgcode" :options="$available_charge_codes->map(
                fn($c) => [
                    'id' => $c->chrgcode,
                    'name' => $c->chrgcode . ' - ' . $c->chrgdesc,
                ],
            )" icon="o-tag"
                placeholder="Select fund source" placeholder-value="0" searchable required />

            <x-mary-checkbox label="Active" wire:model="is_active" />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('addModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Edit Modal --}}
    <x-mary-modal wire:model="editModal" title="Edit Zero Billing Charge: {{ $chrgcode }}" class="backdrop-blur">
        <x-mary-form wire:submit="update">
            <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                <span class="text-sm font-semibold text-gray-700">Charge Code: </span>
                <span class="text-sm font-bold text-gray-900">{{ $chrgcode }}</span>
            </div>

            <x-mary-input label="Description (Optional)" wire:model="description" icon="o-document-text"
                placeholder="Additional notes..." />

            <x-mary-checkbox label="Active" wire:model="is_active" />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('editModal', false)" />
                <x-mary-button label="Update" type="submit" class="btn-primary" spinner="update" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Delete Confirmation Modal --}}
    <x-mary-modal wire:model="deleteModal" title="Confirm Deletion" class="backdrop-blur">
        <div class="p-4">
            <p class="text-gray-700">Are you sure you want to delete this zero billing charge configuration?</p>
            <p class="text-sm text-gray-500 mt-2">This action cannot be undone.</p>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('deleteModal', false)" />
            <x-mary-button label="Delete" wire:click="delete" class="btn-error" spinner="delete" />
        </x-slot:actions>
    </x-mary-modal>
</div>
