<div class="flex flex-col px-5 py-5 mx-auto max-w-screen">
    <x-mary-header title="Prescription Refill Requests" subtitle="Manage prescription refill requests from Salun-at portal" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center gap-2">
                <x-mary-input placeholder="Search drug, patient, hpercode..." wire:model.live.debounce.300ms="search"
                    icon="o-magnifying-glass" clearable class="w-72" />
                <button class="btn btn-primary" wire:click="openManualRefillModal">
                    <x-mary-icon name="o-plus" class="w-4 h-4" />
                    Record Manual Refill
                </button>
            </div>
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

    @unless($refillTableAvailable)
        <div class="alert mb-4 border border-warning/30 bg-warning/10 text-warning-content">
            <x-mary-icon name="o-exclamation-triangle" class="h-5 w-5 shrink-0" />
            <span>
                Prescription refill requests are implemented, but the `prescription_refill_requests` table is not present yet on the shared Portal database.
                Run the Portal refill migration to activate this module.
            </span>
        </div>
    @endunless

    <div class="bg-base-100 rounded-2xl shadow-xl border border-base-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead class="bg-base-200">
                    <tr>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Patient</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Drug / Medication</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Qty</th>
                        <th class="py-4 px-4 text-base-content text-xs font-bold uppercase">Source</th>
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
                                <span class="badge badge-sm badge-outline">
                                    {{ ($refill->request_source ?? 'patient') === 'pharmacy_manual' ? 'Pharmacy Manual' : 'Patient Request' }}
                                </span>
                            </td>
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
                            <td colspan="7" class="py-16 text-center">
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
                        <span class="text-xs text-base-content/60 uppercase font-semibold">Source</span>
                        <p class="text-base-content/80">
                            {{ ($viewRefill->request_source ?? 'patient') === 'pharmacy_manual' ? 'Pharmacy Manual' : 'Patient Request' }}
                        </p>
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
                            @if($prescriptionContext->schedule_text)
                                <div>
                                    <span class="text-base-content/60">Schedule:</span>
                                    <p class="font-semibold text-base-content">{{ $prescriptionContext->schedule_text }}</p>
                                </div>
                            @endif
                            @if($prescriptionContext->days_to_cover)
                                <div>
                                    <span class="text-base-content/60">Days to Cover:</span>
                                    <p class="font-semibold text-base-content">{{ $prescriptionContext->days_to_cover }}</p>
                                </div>
                            @endif
                        </div>
                        <div class="grid grid-cols-3 gap-3 mt-3">
                            <div class="p-2 bg-base-100 rounded text-center border border-base-300">
                                <span class="text-xs text-base-content/60 block">Qty per Administration</span>
                                <span class="text-lg font-bold text-base-content">{{ number_format($prescriptionContext->qty_per_administration) }}</span>
                            </div>
                            <div class="p-2 bg-base-100 rounded text-center border border-base-300">
                                <span class="text-xs text-success block">Already Dispensed</span>
                                <span class="text-lg font-bold text-success">{{ number_format($prescriptionContext->qty_issued) }}</span>
                            </div>
                            <div class="p-2 bg-base-100 rounded text-center border border-base-300">
                                <span class="text-xs text-warning block">Remaining</span>
                                <span class="text-lg font-bold text-warning">{{ number_format($prescriptionContext->computed_remaining_qty ?? 0) }}</span>
                            </div>
                        </div>
                        @if($prescriptionContext->computed_total_qty !== null)
                            <p class="text-xs text-base-content/70 mt-2"><strong>Computed Total Qty:</strong> {{ number_format($prescriptionContext->computed_total_qty) }}</p>
                        @endif
                        @if($prescriptionContext->single_allowable_dispense_qty !== null)
                            <p class="text-xs text-base-content/70 mt-1"><strong>Single Allowable Dispense:</strong> {{ number_format($prescriptionContext->single_allowable_dispense_qty) }} @if(($prescriptionContext->days_to_cover ?? 0) > 30)<span class="text-warning">(30-day cap applied)</span>@endif</p>
                        @endif
                        @if(($prescriptionContext->refill_after_30_days_qty ?? 0) > 0)
                            <p class="text-xs text-base-content/70 mt-1"><strong>For Later Refill:</strong> {{ number_format($prescriptionContext->refill_after_30_days_qty) }}</p>
                        @endif
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

    <x-mary-modal wire:model="manualRefillModal" title="Record Manual Refill" class="backdrop-blur" box-class="max-w-3xl bg-base-100 text-base-content border border-base-300">
        <div class="space-y-4">
            <div>
                <label class="label"><span class="label-text">Find Patient</span></label>
                <x-mary-input
                    wire:model.live.debounce.300ms="manualPatientSearch"
                    placeholder="Search by patient name or hpercode..."
                    icon="o-magnifying-glass" />

                @if (!empty($manualPatientResults))
                    <div class="mt-2 rounded-xl border border-base-300 bg-base-200 overflow-hidden">
                        @foreach ($manualPatientResults as $patient)
                            <button
                                type="button"
                                wire:click="selectManualPatient({{ $patient['id'] }})"
                                class="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-base-300 transition">
                                <span class="font-medium">{{ $patient['fullname'] }}</span>
                                <span class="text-xs font-mono text-base-content/60">{{ $patient['hpercode'] }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($manualPatient)
                <div class="rounded-xl border border-base-300 bg-base-200 p-4">
                    <div class="font-semibold">{{ $manualPatient->fullname }}</div>
                    <div class="text-xs font-mono text-base-content/60">{{ $manualPatient->hpercode }}</div>
                </div>

                <div>
                    <label class="label"><span class="label-text">Prescription Item</span></label>
                    <select class="select select-bordered w-full" wire:model.live="manualPrescriptionDataId">
                        <option value="">Select refillable medication</option>
                        @foreach ($manualPrescriptionItems as $item)
                            <option value="{{ $item['id'] }}">
                                {{ $item['drug_name'] ?: str_replace('_,', ' ', $item['drug_concat'] ?? 'Medication') }}
                                | Remaining {{ number_format($item['computed_remaining_qty']) }}
                                | Allowable {{ number_format($item['allowable_request_qty']) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @php
                    $selectedManualItem = collect($manualPrescriptionItems)->firstWhere('id', (int) $manualPrescriptionDataId);
                @endphp

                @if ($selectedManualItem)
                    <div class="rounded-xl border border-warning/20 bg-warning/10 p-4 text-sm">
                        <div class="font-semibold text-warning mb-2">{{ $selectedManualItem['drug_name'] ?: str_replace('_,', ' ', $selectedManualItem['drug_concat'] ?? 'Medication') }}</div>
                        <div>Schedule: {{ $selectedManualItem['schedule_text'] ?? 'N/A' }}</div>
                        <div>Days: {{ $selectedManualItem['days_to_cover'] ?? 'N/A' }}</div>
                        <div>Remaining: {{ number_format($selectedManualItem['computed_remaining_qty']) }}</div>
                        <div>Allowable Now: {{ number_format($selectedManualItem['allowable_request_qty']) }}</div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="label"><span class="label-text">Quantity to Record</span></label>
                        <x-mary-input type="number" min="1" step="1" wire:model="manualQtyRequested" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Remarks</span></label>
                        <x-mary-input wire:model="manualRemarks" placeholder="Optional notes for this manual refill" />
                    </div>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('manualRefillModal', false)" />
            <x-mary-button label="Save Manual Refill" wire:click="createManualRefill" class="btn-primary" spinner="createManualRefill" />
        </x-slot:actions>
    </x-mary-modal>
</div>
