<div class="flex flex-col px-5 py-5 mx-auto max-w-screen">
    <x-mary-header title="Prescription Refill Requests" subtitle="Manage prescription refill requests from Salun-at portal" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input placeholder="Search drug, patient, hpercode..." wire:model.live.debounce.300ms="search"
                icon="o-magnifying-glass" clearable class="w-72" />
        </x-slot:middle>
    </x-mary-header>

    {{-- Status filter tabs --}}
    <div class="flex gap-2 mb-4">
        @foreach(['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'denied' => 'Denied', 'completed' => 'Completed'] as $value => $label)
            <button wire:click="$set('statusFilter', '{{ $value }}')"
                class="btn btn-sm {{ $statusFilter === $value ? 'btn-primary' : 'btn-ghost' }}">
                {{ $label }}
                @if($value === 'pending' && $pendingCount > 0)
                    <span class="badge badge-sm badge-warning ml-1">{{ $pendingCount }}</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700">
                    <tr>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Patient</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Drug / Medication</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Qty</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Status</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase">Requested</th>
                        <th class="py-4 px-4 text-white text-xs font-bold uppercase text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($refills as $refill)
                        <tr class="hover:bg-blue-50 transition-colors border-b border-gray-100">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-gray-900 text-sm">{{ $refill->patient?->fullname ?? '-' }}</div>
                                <div class="text-xs text-gray-500 font-mono">{{ $refill->hpercode ?? 'N/A' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-sm text-gray-900">{{ $refill->drug_name }}</div>
                                @if($refill->remarks)
                                    <div class="text-xs text-gray-500 truncate max-w-[200px]">{{ $refill->remarks }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-700 font-semibold">{{ number_format($refill->qty_requested) }}</td>
                            <td class="py-3 px-4">
                                @switch($refill->status)
                                    @case('pending')
                                        <span class="badge badge-sm badge-warning">Pending</span>
                                        @break
                                    @case('approved')
                                        <span class="badge badge-sm badge-success">Approved</span>
                                        @break
                                    @case('denied')
                                        <span class="badge badge-sm badge-error">Denied</span>
                                        @break
                                    @case('completed')
                                        <span class="badge badge-sm badge-info">Completed</span>
                                        @break
                                @endswitch
                            </td>
                            <td class="py-3 px-4 text-xs text-gray-500">{{ $refill->created_at?->format('M d, Y h:i A') }}</td>
                            <td class="py-3 px-4">
                                <div class="flex justify-center gap-1">
                                    <button class="btn btn-xs btn-info" wire:click="openViewModal({{ $refill->id }})" title="View Details">
                                        <x-mary-icon name="o-eye" class="w-3 h-3" />
                                    </button>
                                    @if($refill->status === 'pending')
                                        <button class="btn btn-xs btn-success" wire:click="openProcessModal({{ $refill->id }}, 'approved')" title="Approve">
                                            <x-mary-icon name="o-check" class="w-3 h-3" />
                                        </button>
                                        <button class="btn btn-xs btn-error" wire:click="openProcessModal({{ $refill->id }}, 'denied')" title="Deny">
                                            <x-mary-icon name="o-x-mark" class="w-3 h-3" />
                                        </button>
                                    @endif
                                    @if($refill->status === 'approved')
                                        <button class="btn btn-xs btn-primary" wire:click="markCompleted({{ $refill->id }})" title="Mark Completed">
                                            <x-mary-icon name="o-check-circle" class="w-3 h-3" />
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-16 text-center">
                                <div class="flex flex-col items-center">
                                    <x-mary-icon name="o-document-text" class="w-16 h-16 text-gray-300 mb-4" />
                                    <span class="text-xl font-bold text-gray-400">No refill requests found</span>
                                    <span class="text-sm text-gray-400 mt-2">Prescription refill requests from the Salun-at app will appear here</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4">
            {{ $refills->links() }}
        </div>
    </div>

    {{-- View Details Modal --}}
    <x-mary-modal wire:model="viewModal" title="Refill Request Details" class="backdrop-blur" box-class="max-w-2xl">
        @if($viewRefill)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Patient</span>
                        <p class="font-bold text-gray-900">{{ $viewRefill->patient?->fullname ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">HPerson Code</span>
                        <p class="text-gray-700 font-mono">{{ $viewRefill->hpercode ?? 'N/A' }}</p>
                    </div>
                    <div class="col-span-2">
                        <span class="text-xs text-gray-500 uppercase font-semibold">Drug / Medication</span>
                        <p class="font-bold text-gray-900">{{ $viewRefill->drug_name }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Quantity Requested</span>
                        <p class="text-gray-700 font-semibold">{{ number_format($viewRefill->qty_requested) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Status</span>
                        <p class="font-semibold {{ $viewRefill->status === 'approved' ? 'text-green-600' : ($viewRefill->status === 'denied' ? 'text-red-600' : ($viewRefill->status === 'completed' ? 'text-blue-600' : 'text-yellow-600')) }}">
                            {{ ucfirst($viewRefill->status) }}
                        </p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Prescription ID</span>
                        <p class="text-gray-700 font-mono">{{ $viewRefill->prescription_id ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-semibold">Requested At</span>
                        <p class="text-gray-700">{{ $viewRefill->created_at?->format('M d, Y h:i A') }}</p>
                    </div>
                </div>

                @if($viewRefill->remarks)
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <span class="text-xs text-gray-500 uppercase font-semibold">Patient Remarks</span>
                        <p class="text-gray-700 text-sm mt-1">{{ $viewRefill->remarks }}</p>
                    </div>
                @endif

                @if($viewRefill->admin_remarks)
                    <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                        <span class="text-xs text-gray-500 uppercase font-semibold">Admin Remarks</span>
                        <p class="text-gray-700 text-sm mt-1">{{ $viewRefill->admin_remarks }}</p>
                        <p class="text-xs text-gray-400 mt-2">Processed by {{ $viewRefill->processed_by }} on {{ $viewRefill->processed_at?->format('M d, Y h:i A') }}</p>
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <x-mary-button label="Close" wire:click="$set('viewModal', false)" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Process Modal --}}
    <x-mary-modal wire:model="processModal" title="{{ $processAction === 'approved' ? 'Approve' : 'Deny' }} Refill Request" class="backdrop-blur">
        <div class="p-4">
            <p class="text-gray-700 mb-4">
                Are you sure you want to <strong>{{ $processAction === 'approved' ? 'approve' : 'deny' }}</strong> this refill request?
            </p>
            <x-mary-textarea label="Remarks (optional)" wire:model="adminRemarks" placeholder="Add any notes about this decision..." rows="3" />
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('processModal', false)" />
            <x-mary-button
                label="{{ $processAction === 'approved' ? 'Approve' : 'Deny' }}"
                wire:click="processRefill"
                class="{{ $processAction === 'approved' ? 'btn-success' : 'btn-error' }}"
                spinner="processRefill" />
        </x-slot:actions>
    </x-mary-modal>
</div>
