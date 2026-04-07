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

    <div class="bg-base-100 rounded-2xl shadow-xl border border-base-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead class="bg-base-200">
                    <tr>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Patient</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Drug / Medication</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Qty</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Status</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Requested</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($refills as $refill)
                        <tr class="hover:bg-base-200/70 transition-colors border-b border-base-300">
                            <td class="py-3 px-4">
                                <div class="font-semibold text-base-content text-sm">{{ $refill->patient?->fullname ?? '-' }}</div>
                                <div class="text-xs text-base-content/60 font-mono">{{ $refill->hpercode ?? 'N/A' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="text-sm text-base-content">{{ $refill->drug_name }}</div>
                                @if($refill->remarks)
                                    <div class="text-xs text-base-content/60 truncate max-w-[200px]">{{ $refill->remarks }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-sm text-base-content/80 font-semibold">{{ number_format($refill->qty_requested) }}</td>
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
                            <td class="py-3 px-4 text-xs text-base-content/60">{{ $refill->created_at?->format('M d, Y h:i A') }}</td>
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
                                    <x-mary-icon name="o-document-text" class="w-16 h-16 text-base-content/30 mb-4" />
                                    <span class="text-xl font-bold text-base-content/60">No refill requests found</span>
                                    <span class="text-sm text-base-content/50 mt-2">Prescription refill requests from the Salun-at app will appear here</span>
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
    <x-mary-modal wire:model="viewModal" title="Refill Request Details" class="backdrop-blur" box-class="max-w-2xl bg-base-100 text-base-content border border-base-300">
        @if($viewRefill)
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Patient</span>
                        <p class="font-bold text-base-content">{{ $viewRefill->patient?->fullname ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">HPerson Code</span>
                        <p class="text-base-content/80 font-mono">{{ $viewRefill->hpercode ?? 'N/A' }}</p>
                    </div>
                    <div class="col-span-2">
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Drug / Medication</span>
                        <p class="font-bold text-base-content">{{ $viewRefill->drug_name }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Quantity Requested</span>
                        <p class="text-base-content/80 font-semibold">{{ number_format($viewRefill->qty_requested) }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Status</span>
                        <p class="font-semibold {{ $viewRefill->status === 'approved' ? 'text-green-600' : ($viewRefill->status === 'denied' ? 'text-red-600' : ($viewRefill->status === 'completed' ? 'text-blue-600' : 'text-yellow-600')) }}">
                            {{ ucfirst($viewRefill->status) }}
                        </p>
                    </div>
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Prescription ID</span>
                        <p class="text-base-content/80 font-mono">{{ $viewRefill->prescription_id ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Requested At</span>
                        <p class="text-base-content/80">{{ $viewRefill->created_at?->format('M d, Y h:i A') }}</p>
                    </div>
                </div>

                {{-- Original Prescription Context from webapp --}}
                @if($prescriptionContext)
                    <div class="p-4 bg-warning/10 rounded-lg border border-warning/20">
                        <h4 class="font-semibold text-warning mb-3 flex items-center gap-2">
                            <x-mary-icon name="o-clipboard-document-list" class="w-4 h-4" />
                            Original Prescription (CDOE)
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-base-content/60">Prescribing Doctor:</span>
                                <p class="font-semibold text-base-content">Dr. {{ $prescriptionContext->doctor_name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <span class="text-base-content/60">Prescribed Date:</span>
                                <p class="text-base-content/80">{{ $prescriptionContext->prescribed_at ? \Carbon\Carbon::parse($prescriptionContext->prescribed_at)->format('M d, Y') : 'N/A' }}</p>
                            </div>
                            @if($prescriptionContext->frequency)
                                <div>
                                    <span class="text-base-content/60">Frequency:</span>
                                    <p class="font-semibold text-base-content">{{ $prescriptionContext->frequency }}</p>
                                </div>
                            @endif
                            @if($prescriptionContext->duration)
                                <div>
                                    <span class="text-base-content/60">Duration:</span>
                                    <p class="font-semibold text-base-content">{{ $prescriptionContext->duration }}</p>
                                </div>
                            @endif
                        </div>
                        <div class="grid grid-cols-3 gap-3 mt-3">
                            <div class="p-2 bg-base-100 rounded text-center border border-base-300">
                                <span class="text-xs text-base-content/60 block">Total Ordered</span>
                                <span class="text-lg font-bold text-base-content">{{ number_format($prescriptionContext->qty_ordered) }}</span>
                            </div>
                            <div class="p-2 bg-base-100 rounded text-center border border-base-300">
                                <span class="text-xs text-success block">Already Dispensed</span>
                                <span class="text-lg font-bold text-success">{{ number_format($prescriptionContext->total_issued) }}</span>
                            </div>
                            <div class="p-2 bg-base-100 rounded text-center border border-base-300">
                                <span class="text-xs text-warning block">Remaining</span>
                                <span class="text-lg font-bold text-warning">{{ number_format($prescriptionContext->remaining_qty) }}</span>
                            </div>
                        </div>
                        @if($prescriptionContext->remark)
                            <p class="text-xs text-base-content/70 mt-2"><strong>Doctor's Remark:</strong> {{ $prescriptionContext->remark }}</p>
                        @endif
                    </div>
                @endif

                @if($viewRefill->remarks)
                    <div class="p-3 bg-base-200 rounded-lg border border-base-300">
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Patient Remarks</span>
                        <p class="text-base-content/80 text-sm mt-1">{{ $viewRefill->remarks }}</p>
                    </div>
                @endif

                @if($viewRefill->admin_remarks)
                    <div class="p-3 bg-info/10 rounded-lg border border-info/20">
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Admin Remarks</span>
                        <p class="text-base-content/80 text-sm mt-1">{{ $viewRefill->admin_remarks }}</p>
                        <p class="text-xs text-base-content/50 mt-2">Processed by {{ $viewRefill->processed_by }} on {{ $viewRefill->processed_at?->format('M d, Y h:i A') }}</p>
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
            <p class="text-base-content/80 mb-4">
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
