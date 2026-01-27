<div class="p-6">
    <x-mary-header title="Non-PNF Drugs Management" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-mary-input wire:model.live.debounce.300ms="search" placeholder="Search drugs..." icon="o-magnifying-glass"
                clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-checkbox wire:model.live="showDeletedOnly" label="Show Deleted" class="mr-4" />
            <x-mary-button icon="o-arrow-down-tray" wire:click="openImportModal" class="btn-secondary mr-2">
                Import Excel
            </x-mary-button>
            <x-mary-button icon="o-plus" wire:click="create" class="btn-primary">
                Add Drug
            </x-mary-button>
        </x-slot:actions>
    </x-mary-header>

    @if (session('success'))
        <x-mary-alert icon="o-check-circle" class="alert-success mb-4">
            {{ session('success') }}
        </x-mary-alert>
    @endif

    @if (session('error'))
        <x-mary-alert icon="o-x-circle" class="alert-error mb-4">
            {{ session('error') }}
        </x-mary-alert>
    @endif

    <div class="overflow-x-auto bg-base-100 rounded-lg shadow">
        <table class="table table-zebra table-sm">
            <thead>
                <tr class="bg-base-200">
                    <th class="w-12">#</th>
                    <th>Medicine Name</th>
                    <th>Dose</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th class="w-32 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($drugs as $drug)
                    <tr wire:key="drug-{{ $drug->id }}">
                        <td>{{ $drugs->firstItem() + $loop->index }}</td>
                        <td class="font-medium">{{ $drug->medicine_name }}</td>
                        <td>{{ $drug->dose ?? '-' }}</td>
                        <td>{{ $drug->unit ?? '-' }}</td>
                        <td>
                            @if ($drug->deleted_at)
                                <span class="badge badge-error badge-sm">Deleted</span>
                            @elseif ($drug->is_active)
                                <button wire:click="toggleActive({{ $drug->id }})"
                                    class="badge badge-success badge-sm cursor-pointer hover:badge-success-content">
                                    Active
                                </button>
                            @else
                                <button wire:click="toggleActive({{ $drug->id }})"
                                    class="badge badge-warning badge-sm cursor-pointer hover:badge-warning-content">
                                    Inactive
                                </button>
                            @endif
                        </td>
                        <td class="max-w-xs truncate">{{ $drug->remarks ?? '-' }}</td>
                        <td>
                            <div class="flex gap-1 justify-center">
                                @if ($drug->deleted_at)
                                    <button wire:click="restore({{ $drug->id }})" wire:confirm="Restore this drug?"
                                        class="btn btn-xs btn-success" title="Restore">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <button wire:click="forceDelete({{ $drug->id }})"
                                        wire:confirm="Permanently delete this drug? This cannot be undone!"
                                        class="btn btn-xs btn-error" title="Permanently Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                @else
                                    <button wire:click="edit({{ $drug->id }})" class="btn btn-xs btn-ghost"
                                        title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path
                                                d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                        </svg>
                                    </button>
                                    <button wire:click="delete({{ $drug->id }})" wire:confirm="Delete this drug?"
                                        class="btn btn-xs btn-ghost" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-8 text-base-content/60">
                            No drugs found
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $drugs->links() }}
    </div>

    {{-- Add/Edit Modal --}}
    <x-mary-modal wire:model="showModal" title="{{ $drugId ? 'Edit' : 'Add' }} Non-PNF Drug" separator>
        <x-mary-form wire:submit="save">
            <x-mary-input wire:model="medicine_name" label="Medicine Name" placeholder="Enter medicine name" required />

            <x-mary-input wire:model="dose" label="Dose" placeholder="e.g., 500mg" />

            <x-mary-input wire:model="unit" label="Unit" placeholder="e.g., tablet, capsule" />

            <x-mary-checkbox wire:model="is_active" label="Active" />

            <x-mary-textarea wire:model="remarks" label="Remarks" placeholder="Additional notes" rows="3" />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="closeModal" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Import Modal --}}
    <x-mary-modal wire:model="showImportModal" title="Import Non-PNF Drugs from Excel" separator persistent
        box-class="w-11/12 max-w-7xl">
        @if (!$showPreview)
            <div class="space-y-4">
                <x-mary-alert icon="o-information-circle" class="alert-info">
                    Download the template file, fill in the drug details, and upload it back.
                </x-mary-alert>

                <x-mary-button icon="o-arrow-down-tray" wire:click="downloadTemplate" class="btn-outline w-full">
                    Download Excel Template
                </x-mary-button>

                <x-mary-file wire:model="importFile" label="Select Excel File" accept=".xlsx,.xls"
                    hint="Maximum file size: 10MB. Preview will load automatically after file selection." />

                <div wire:loading wire:target="importFile" class="flex items-center justify-center py-8">
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                    <span class="ml-3 text-base-content">Processing file...</span>
                </div>

                @if ($importResults)
                    <div class="mt-4 space-y-2">
                        <div class="stats shadow w-full">
                            <div class="stat">
                                <div class="stat-title">Imported</div>
                                <div class="stat-value text-success">{{ $importResults['imported'] }}</div>
                            </div>
                            <div class="stat">
                                <div class="stat-title">Skipped</div>
                                <div class="stat-value text-warning">{{ $importResults['skipped'] }}</div>
                            </div>
                        </div>

                        @if (!empty($importResults['errors']))
                            <div class="alert alert-warning">
                                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6"
                                    fill="none" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div>
                                    <h3 class="font-bold">Errors during import:</h3>
                                    <ul class="list-disc list-inside mt-2 max-h-40 overflow-y-auto text-sm">
                                        @foreach ($importResults['errors'] as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-mary-button label="Close" wire:click="closeImportModal" />
            </x-slot:actions>
        @else
            {{-- Preview Section --}}
            <div class="space-y-4">
                <div class="stats shadow w-full">
                    <div class="stat">
                        <div class="stat-title">Total Records</div>
                        <div class="stat-value text-primary">{{ $previewData['total_count'] }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-title">Valid</div>
                        <div class="stat-value text-success">{{ $previewData['valid_count'] }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-title">Invalid/Duplicates</div>
                        <div class="stat-value text-error">{{ $previewData['invalid_count'] }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto max-h-96 border rounded-lg">
                    <table class="table table-zebra table-pin-rows table-sm">
                        <thead>
                            <tr class="bg-base-200">
                                <th class="w-16">Row</th>
                                <th>Medicine Name</th>
                                <th>Dose</th>
                                <th>Unit</th>
                                <th class="w-24">Status</th>
                                <th>Errors</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($previewData['data'] as $item)
                                <tr wire:key="preview-{{ $loop->index }}">
                                    <td>{{ $item['row_number'] }}</td>
                                    <td class="font-medium">{{ $item['medicine_name'] ?: '-' }}</td>
                                    <td>{{ $item['dose'] ?: '-' }}</td>
                                    <td>{{ $item['unit'] ?: '-' }}</td>
                                    <td>
                                        @if ($item['status'] === 'valid')
                                            <span class="badge badge-success badge-sm">Valid</span>
                                        @elseif ($item['status'] === 'duplicate')
                                            <span class="badge badge-warning badge-sm">Duplicate</span>
                                        @else
                                            <span class="badge badge-error badge-sm">Invalid</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (!empty($item['errors']))
                                            <span
                                                class="text-error text-xs">{{ implode(', ', $item['errors']) }}</span>
                                        @else
                                            <span class="text-success text-xs">✓ Ready to import</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-mary-alert icon="o-information-circle" class="alert-warning">
                    Invalid and duplicate records will be skipped during import. Only valid records will be added.
                </x-mary-alert>
            </div>

            <x-slot:actions>
                <x-mary-button label="Close" wire:click="closeImportModal" />
                <x-mary-button label="Upload Different File" wire:click="cancelPreview" class="btn-secondary" />
                <x-mary-button
                    label="Confirm Import ({{ $previewData['valid_count'] - count(array_filter($previewData['data'], fn($item) => $item['status'] === 'duplicate')) }} records)"
                    wire:click="confirmImport" class="btn-primary" spinner="confirmImport" :disabled="$previewData['valid_count'] === 0" />
            </x-slot:actions>
        @endif
    </x-mary-modal>
</div>
