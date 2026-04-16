<div>
    <x-mary-header title="Prescription Reorder Settings" subtitle="Schedule, run history, and manual reorder controls." separator />

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <x-mary-card class="xl:col-span-1">
            <div class="space-y-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-base-content/60">Scheduled Run</p>
                    <p class="text-lg font-semibold">Daily at 7:00 AM</p>
                    <p class="text-sm text-base-content/70">Timezone: Asia/Manila</p>
                </div>

                <div class="divider my-1"></div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-base-content/60">Next Scheduled Run</p>
                    <p class="font-medium">{{ $nextScheduledRun->format('M d, Y h:i A') }}</p>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-base-content/60">Today's Run</p>
                    @if ($todayRun)
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="badge badge-success">{{ ucfirst($todayRun->status) }}</span>
                            <span class="badge badge-outline">{{ $todayRun->source === 'auto' ? 'Automatic' : 'Manual' }}</span>
                        </div>
                        <p class="text-sm mt-1">{{ $todayRun->run_at?->setTimezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
                        <p class="text-sm text-base-content/70">{{ number_format($todayRun->reordered_count) }} item(s) reordered</p>
                    @else
                        <span class="badge badge-warning">Not yet run today</span>
                    @endif
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-base-content/60">Latest Run</p>
                    @if ($latestRun)
                        <p class="font-medium">{{ $latestRun->run_at?->setTimezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
                        <p class="text-sm text-base-content/70">
                            {{ $latestRun->source === 'auto' ? 'Automatic' : 'Manual' }}
                            @if ($latestRun->performed_by)
                                by {{ $latestRun->performed_by }}
                            @endif
                        </p>
                    @else
                        <p class="text-sm text-base-content/70">No run logged yet.</p>
                    @endif
                </div>
            </div>
        </x-mary-card>

        <x-mary-card class="xl:col-span-2">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-base-content/60">Manual Trigger</p>
                    <h3 class="text-lg font-semibold">Run Reorder Now</h3>
                    <p class="text-sm text-base-content/70">
                        This manually runs the same reorder logic used by the daily 7:00 AM schedule.
                    </p>
                    @if ($todayRun)
                        <p class="text-sm text-warning mt-2">
                            Warning: a reorder run has already been logged for today at
                            {{ $todayRun->run_at?->setTimezone('Asia/Manila')->format('h:i A') }}.
                        </p>
                    @endif
                </div>
                <div class="shrink-0">
                    <button
                        wire:click="triggerManualReorder"
                        wire:mary-confirm="Warning: manually run the reorder process now?"
                        class="btn btn-primary">
                        <x-mary-icon name="o-bolt" class="w-5 h-5" />
                        Run Reorder Now
                    </button>
                </div>
            </div>

            @if ($lastManualRun)
                <div class="mt-4 rounded-xl border border-success/20 bg-success/10 p-4 text-sm">
                    <p class="font-semibold text-success">Latest manual run finished.</p>
                    <p>Run at: {{ \Carbon\Carbon::parse($lastManualRun['run_at'])->setTimezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
                    <p>Items reordered: {{ number_format($lastManualRun['count']) }}</p>
                </div>
            @endif
        </x-mary-card>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-4">
        <x-mary-card>
            <div class="mb-3">
                <h3 class="text-lg font-semibold">Run Log</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-sm table-zebra">
                    <thead>
                        <tr>
                            <th>Run At</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Count</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($runLogs as $log)
                            <tr>
                                <td class="text-xs">{{ $log->run_at?->setTimezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                                <td>
                                    <span class="badge badge-outline">{{ $log->source === 'auto' ? 'Automatic' : 'Manual' }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $log->status === 'completed' ? 'badge-success' : 'badge-ghost' }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td>{{ number_format($log->reordered_count) }}</td>
                                <td class="text-xs">{{ $log->performed_by ?: 'System' }}</td>
                            </tr>
                            @if ($log->notes)
                                <tr>
                                    <td colspan="5" class="text-xs text-base-content/60 pt-0">{{ $log->notes }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-8 text-base-content/50">No run logs yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-mary-card>

        <x-mary-card>
            <div class="mb-3">
                <h3 class="text-lg font-semibold">Recent Reordered Items</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="table table-sm table-zebra">
                    <thead>
                        <tr>
                            <th>Reordered At</th>
                            <th>Source</th>
                            <th>Patient</th>
                            <th>Drug</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentItems as $item)
                            <tr>
                                <td class="text-xs">{{ \Carbon\Carbon::parse($item->reordered_at)->setTimezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                                <td>
                                    <span class="badge badge-outline">{{ $item->source_label }}</span>
                                </td>
                                <td>
                                    <div class="font-medium">{{ $item->patient_name ?? 'N/A' }}</div>
                                    <div class="text-xs opacity-60">{{ $item->hpercode ?? 'N/A' }} | {{ $item->encounter_type_label ?? 'N/A' }}</div>
                                </td>
                                <td>
                                    <div>{{ str_replace('_,', ' ', $item->drug_concat ?? 'N/A') }}</div>
                                    <div class="text-xs opacity-60">PD #{{ $item->prescription_data_id }} | Rx #{{ $item->prescription_id }}</div>
                                </td>
                            </tr>
                            @if ($item->notes)
                                <tr>
                                    <td colspan="4" class="text-xs text-base-content/60 pt-0">{{ $item->notes }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-8 text-base-content/50">No reordered items logged yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-mary-card>
    </div>

    <x-mary-modal wire:model="showRerunWarningModal" title="Run Already Logged Today" class="backdrop-blur">
        <div class="space-y-3">
            <p class="text-sm text-base-content/80">
                A reorder run has already been logged for today. Running it again is usually unnecessary and may confuse staff reviewing the logs.
            </p>
            <p class="text-sm text-warning">
                Only continue if you intentionally need a second manual run for {{ now('Asia/Manila')->format('M d, Y') }}.
            </p>
        </div>

        <x-slot:actions>
            <x-mary-button label="Cancel" wire:click="$set('showRerunWarningModal', false)" />
            <x-mary-button
                label="Run Again Anyway"
                wire:click="confirmManualRerun"
                class="btn-warning" />
        </x-slot:actions>
    </x-mary-modal>
</div>
