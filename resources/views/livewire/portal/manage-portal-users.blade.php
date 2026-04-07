<div class="flex flex-col px-5 py-5 mx-auto max-w-screen">
    <x-mary-header title="Portal User Management" subtitle="Manage Salun-at patient portal accounts" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="Search name, username, email, hpercode..." wire:model.live.debounce.300ms="search"
                icon="o-magnifying-glass" clearable class="w-72" />
        </x-slot:middle>
    </x-mary-header>

    <div class="bg-base-100 rounded-2xl shadow-xl border border-base-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead class="bg-base-200">
                    <tr>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Patient Name</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Username</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Email</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">HPerson Code</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Gender</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Registered</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr class="hover:bg-base-200/70 transition-colors border-b border-base-300">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-base-content text-sm">{{ $account->patient?->fullname ?? '-' }}</div>
                                <div class="text-xs text-base-content/60">
                                    @if($account->patient?->patbdate)
                                        {{ $account->patient->patbdate->format('M d, Y') }}
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-sm text-base-content/80 font-mono">{{ $account->username ?? '-' }}</td>
                            <td class="py-3 px-4 text-sm text-base-content/80">{{ $account->email }}</td>
                            <td class="py-3 px-4 text-sm text-base-content/80 font-mono">
                                @if($account->patient?->hpercode)
                                    <span class="badge badge-sm badge-success">{{ $account->patient->hpercode }}</span>
                                @else
                                    <span class="badge badge-sm badge-warning">Not Linked</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-sm text-base-content/80">{{ $account->patient?->patgender ?? '-' }}</td>
                            <td class="py-3 px-4 text-xs text-base-content/60">{{ $account->created_at?->format('M d, Y h:i A') }}</td>
                            <td class="py-3 px-4">
                                <div class="flex justify-center gap-1">
                                    <button class="btn btn-xs btn-info" wire:click="openViewModal({{ $account->id }})" title="View Details">
                                        <x-mary-icon name="o-eye" class="w-3 h-3" />
                                    </button>
                                    <button class="btn btn-xs btn-error" wire:click="openDeleteModal({{ $account->id }})" title="Delete">
                                        <x-mary-icon name="o-trash" class="w-3 h-3" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <x-mary-icon name="o-users" class="w-16 h-16 text-base-content/30 mb-4" />
                                    <span class="text-xl font-bold text-base-content/60">No portal accounts found</span>
                                    <span class="text-sm text-base-content/50 mt-2">Accounts from the Salun-at app will appear here</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $accounts->links() }}
        </div>
    </div>

    {{-- View Details Modal --}}
    <x-mary-modal wire:model="viewModal" title="Portal Account Details" class="backdrop-blur" box-class="max-w-2xl bg-base-100 text-base-content border border-base-300">
        @if($viewUser)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Username</span>
                        <p class="font-bold text-base-content font-mono">{{ $viewUser->username }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Email</span>
                        <p class="text-base-content/80">{{ $viewUser->email }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Email Verified</span>
                        <p class="text-base-content/80">{{ $viewUser->email_verified_at ? $viewUser->email_verified_at->format('M d, Y') : 'Not verified' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Registered At</span>
                        <p class="text-base-content/80">{{ $viewUser->created_at?->format('M d, Y h:i A') }}</p>
                    </div>
                </div>

                @if($viewPatient)
                    <div class="mt-4 p-4 bg-primary/10 rounded-lg border border-primary/20">
                        <h4 class="font-semibold text-primary mb-3">Portal Patient Record</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div><span class="text-base-content/60">Name:</span> {{ $viewPatient->fullname }}</div>
                            <div><span class="text-base-content/60">HPerson Code:</span> {{ $viewPatient->hpercode ?? 'N/A' }}</div>
                            <div><span class="text-base-content/60">Birth Date:</span> {{ $viewPatient->patbdate?->format('M d, Y') ?? 'N/A' }}</div>
                            <div><span class="text-base-content/60">Gender:</span> {{ $viewPatient->patgender ?? 'N/A' }}</div>
                            <div><span class="text-base-content/60">Contact:</span> {{ $viewPatient->patcontactno ?? 'N/A' }}</div>
                            <div><span class="text-base-content/60">Email:</span> {{ $viewPatient->patemail ?? 'N/A' }}</div>
                            <div class="col-span-2"><span class="text-base-content/60">Address:</span> {{ $viewPatient->pataddress ?? 'N/A' }}</div>
                        </div>
                    </div>
                @endif

                @if($matchedHospitalPatient)
                    <div class="mt-2 p-4 bg-success/10 rounded-lg border border-success/20">
                        <h4 class="font-semibold text-success mb-3">Linked Hospital Record (hperson)</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div><span class="text-base-content/60">Name:</span> {{ $matchedHospitalPatient->fullname }}</div>
                            <div><span class="text-base-content/60">HPerson Code:</span> {{ $matchedHospitalPatient->hpercode }}</div>
                            <div><span class="text-base-content/60">Birth Date:</span> {{ $matchedHospitalPatient->patbdate?->format('M d, Y') ?? 'N/A' }}</div>
                            <div><span class="text-base-content/60">Sex:</span> {{ $matchedHospitalPatient->gender() }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="$set('viewModal', false)" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Delete Confirmation Modal --}}
    <x-mary-modal wire:model="deleteModal" title="Delete Portal Account" class="backdrop-blur">
        <div class="p-4">
            <p class="text-base-content/80">Are you sure you want to delete this portal user account?</p>
            <p class="text-sm text-base-content/60 mt-2">This will soft-delete the account. The patient record will remain.</p>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('deleteModal', false)" />
            <x-mary-button label="Delete" wire:click="deleteAccount" class="btn-error" spinner="deleteAccount" />
        </x-slot:actions>
    </x-mary-modal>
</div>
