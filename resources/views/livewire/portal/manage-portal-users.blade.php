<div class="flex flex-col px-5 py-5 mx-auto max-w-screen">
    <x-mary-header title="Portal User Management" subtitle="Manage patient portal registrants" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-mary-input placeholder="Search name, email, hospital no..." wire:model.live.debounce.300ms="search"
                    icon="o-magnifying-glass" clearable class="w-64" />
                <select wire:model.live="statusFilter" class="select select-bordered select-sm">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="verified">Verified</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
        </x-slot:middle>
    </x-mary-header>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                    <tr>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Name</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Email</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Contact</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Hospital No.</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Hospital Record</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Status</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Registered</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-gray-900 text-sm">{{ $user->fullname }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $user->patsex === 'M' ? 'Male' : 'Female' }}
                                    @if($user->patbdate)
                                        | {{ $user->patbdate->format('M d, Y') }}
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-700">{{ $user->email }}</td>
                            <td class="py-3 px-4 text-sm text-gray-700">{{ $user->contact_no ?? '-' }}</td>
                            <td class="py-3 px-4 text-sm text-gray-700 font-mono">{{ $user->hospital_no ?? '-' }}</td>
                            <td class="py-3 px-4">
                                @if($user->hpercode)
                                    <span class="badge badge-sm badge-success">Linked</span>
                                @else
                                    <span class="badge badge-sm badge-warning">Not Found</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @if($user->status === 'pending')
                                    <span class="badge badge-sm badge-warning">Pending</span>
                                @elseif($user->status === 'verified')
                                    <span class="badge badge-sm badge-success">Verified</span>
                                @else
                                    <span class="badge badge-sm badge-error">Rejected</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-500">{{ $user->created_at->format('M d, Y h:i A') }}</td>
                            <td class="py-3 px-4">
                                <div class="flex justify-center gap-1">
                                    <button class="btn btn-xs btn-info" wire:click="openViewModal({{ $user->id }})" title="View Details">
                                        <x-mary-icon name="o-eye" class="w-3 h-3" />
                                    </button>
                                    @if($user->status === 'pending')
                                        <button class="btn btn-xs btn-success" wire:click="openVerifyModal({{ $user->id }})" title="Verify">
                                            <x-mary-icon name="o-check" class="w-3 h-3" />
                                        </button>
                                        <button class="btn btn-xs btn-error" wire:click="openRejectModal({{ $user->id }})" title="Reject">
                                            <x-mary-icon name="o-x-mark" class="w-3 h-3" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <x-mary-icon name="o-users" class="w-16 h-16 text-gray-300 mb-4" />
                                    <span class="text-xl font-bold text-gray-400">No portal registrants found</span>
                                    <span class="text-sm text-gray-400 mt-2">Registrants from the Salun-at app will appear here</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $users->links() }}
        </div>
    </div>

    {{-- View Details Modal --}}
    <x-mary-modal wire:model="viewModal" title="Portal User Details" class="backdrop-blur" box-class="max-w-2xl">
        @if($viewUser)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Full Name</span>
                        <p class="font-bold text-gray-900">{{ $viewUser->fullname }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Email</span>
                        <p class="text-gray-700">{{ $viewUser->email }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Contact No.</span>
                        <p class="text-gray-700">{{ $viewUser->contact_no ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Birth Date</span>
                        <p class="text-gray-700">{{ $viewUser->patbdate ? $viewUser->patbdate->format('M d, Y') : 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Sex</span>
                        <p class="text-gray-700">{{ $viewUser->patsex === 'M' ? 'Male' : 'Female' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Status</span>
                        <p>
                            @if($viewUser->status === 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @elseif($viewUser->status === 'verified')
                                <span class="badge badge-success">Verified</span>
                            @else
                                <span class="badge badge-error">Rejected</span>
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Hospital No.</span>
                        <p class="text-gray-700 font-mono">{{ $viewUser->hospital_no ?? 'Not Assigned' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">HPerson Code</span>
                        <p class="text-gray-700 font-mono">{{ $viewUser->hpercode ?? 'Not Linked' }}</p>
                    </div>
                </div>

                @if($matchedPatient)
                    <div class="mt-4 p-4 bg-green-50 rounded-lg border border-green-200">
                        <h4 class="font-semibold text-green-800 mb-2">Matched Hospital Record</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div><span class="text-gray-500">Name:</span> {{ $matchedPatient->fullname }}</div>
                            <div><span class="text-gray-500">HPerson Code:</span> {{ $matchedPatient->hpercode }}</div>
                            <div><span class="text-gray-500">Birth Date:</span> {{ $matchedPatient->patbdate ? $matchedPatient->patbdate->format('M d, Y') : 'N/A' }}</div>
                            <div><span class="text-gray-500">Sex:</span> {{ $matchedPatient->gender() }}</div>
                        </div>
                    </div>
                @elseif($viewUser->hpercode)
                    <div class="mt-4 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-yellow-800 text-sm">HPerson code is set but hospital record was not found.</p>
                    </div>
                @else
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="text-gray-600 text-sm">No matching hospital record found. Hospital number must be assigned during verification.</p>
                    </div>
                @endif

                @if($viewUser->status === 'rejected' && $viewUser->reject_reason)
                    <div class="mt-4 p-4 bg-red-50 rounded-lg border border-red-200">
                        <h4 class="font-semibold text-red-800 mb-1">Rejection Reason</h4>
                        <p class="text-red-700 text-sm">{{ $viewUser->reject_reason }}</p>
                    </div>
                @endif

                @if($viewUser->verified_by)
                    <div class="text-xs text-gray-400 mt-2">
                        {{ $viewUser->status === 'verified' ? 'Verified' : 'Processed' }} by {{ $viewUser->verified_by }}
                        on {{ $viewUser->verified_at?->format('M d, Y h:i A') }}
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="$set('viewModal', false)" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Verify Modal --}}
    <x-mary-modal wire:model="verifyModal" title="Verify Portal User" class="backdrop-blur">
        @if($selectedUser)
            <div class="mb-4 p-4 bg-blue-50 rounded-lg">
                <p class="font-semibold text-gray-800">{{ $selectedUser->fullname }}</p>
                <p class="text-sm text-gray-600">{{ $selectedUser->email }}</p>
            </div>

            <x-mary-form wire:submit="verify">
                <x-mary-input label="Hospital Number" wire:model="hospital_no" icon="o-identification"
                    placeholder="Enter unique hospital number" required
                    hint="Provide the patient's unique hospital number for identification" />

                <x-slot:actions>
                    <x-mary-button label="Cancel" wire:click="$set('verifyModal', false)" />
                    <x-mary-button label="Verify User" type="submit" class="btn-success" spinner="verify" icon="o-check" />
                </x-slot:actions>
            </x-mary-form>
        @endif
    </x-mary-modal>

    {{-- Reject Modal --}}
    <x-mary-modal wire:model="rejectModal" title="Reject Portal User" class="backdrop-blur">
        @if($selectedUser)
            <div class="mb-4 p-4 bg-red-50 rounded-lg">
                <p class="font-semibold text-gray-800">{{ $selectedUser->fullname }}</p>
                <p class="text-sm text-gray-600">{{ $selectedUser->email }}</p>
            </div>

            <x-mary-form wire:submit="reject">
                <x-mary-textarea label="Reason for Rejection" wire:model="reject_reason"
                    placeholder="Explain why this registration is being rejected..." required rows="3" />

                <x-slot:actions>
                    <x-mary-button label="Cancel" wire:click="$set('rejectModal', false)" />
                    <x-mary-button label="Reject User" type="submit" class="btn-error" spinner="reject" icon="o-x-mark" />
                </x-slot:actions>
            </x-mary-form>
        @endif
    </x-mary-modal>
</div>
