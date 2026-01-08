<div class="min-h-screen bg-white flex flex-col" x-data="{ time: new Date() }" x-init="console.log('Display Settings:', {{ json_encode($displaySettings) }});
console.log('Auto Refresh Seconds:', {{ $displaySettings->auto_refresh_seconds }});
console.log('Refresh Interval (ms):', {{ $displaySettings->auto_refresh_seconds * 1000 }});

setInterval(() => { time = new Date() }, 500);
setInterval(() => {
    console.log('Refreshing queues at:', new Date().toLocaleTimeString());
    $wire.call('loadQueues');
}, {{ $displaySettings->auto_refresh_seconds * 1000 }});">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-green-700 to-green-600 text-white shadow-2xl">
        <div class="px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    {{-- Hospital Logo --}}
                    <div class="bg-white rounded-lg p-2 shadow-lg">
                        <img src="/images/hospital-logo.png" alt="Hospital Logo" class="h-16 w-16 object-contain">
                    </div>
                    <div>
                        <h1 class="text-4xl font-bold tracking-tight">{{ config('app.name') }}</h1>
                        <p class="text-lg opacity-90">Prescription Queue Display</p>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-5xl font-bold tabular-nums"
                        x-text="time.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' })">
                    </div>
                    <div class="text-xl opacity-90"
                        x-text="time.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' | ' + time.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content - Split Screen --}}
    <div class="flex-1 flex">
        {{-- Left Side: PHARMACY WINDOWS --}}
        <div class="w-1/2 bg-gray-50 p-6 border-r-4 border-green-600">
            <div class="bg-green-700 text-white text-center py-4 rounded-t-xl shadow-lg mb-6">
                <h2 class="text-4xl font-bold">PHARMACY WINDOWS</h2>
            </div>

            <div class="space-y-4">
                {{-- Preparing Queues --}}
                @foreach ($currentlyServing as $queue)
                    <div
                        class="bg-white border-4 {{ $queue->priority === 'stat' ? 'border-red-600' : 'border-green-600' }} rounded-xl shadow-xl overflow-hidden">
                        <div class="flex items-center">
                            {{-- Window Badge --}}
                            <div
                                class="bg-green-700 text-white px-8 py-6 flex flex-col items-center justify-center min-w-[140px]">
                                <div class="text-sm font-semibold mb-1">WINDOW</div>
                                <div class="text-6xl font-bold">{{ $queue->assigned_window ?? 'X' }}</div>
                            </div>

                            {{-- Queue Number --}}
                            <div class="flex-1 text-center py-4">
                                <div class="text-7xl font-bold text-green-700">
                                    {{ $queue->queue_number }}
                                </div>
                                @if ($queue->priority === 'stat')
                                    <div class="mt-2">
                                        <span class="badge badge-error badge-lg text-lg px-4 py-3">STAT</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Charging Queues (only if cashier required) --}}
                @if ($displaySettings->require_cashier)
                    @foreach ($showingQueues['charging']->take($displaySettings->pharmacy_windows - $currentlyServing->count()) as $queue)
                        <div class="bg-white border-4 border-yellow-500 rounded-xl shadow-xl overflow-hidden">
                            <div class="flex items-center">
                                <div
                                    class="bg-yellow-500 text-white px-8 py-6 flex flex-col items-center justify-center min-w-[140px]">
                                    <div class="text-sm font-semibold mb-1">WINDOW</div>
                                    <div class="text-6xl font-bold">{{ $queue->assigned_window ?? 'X' }}</div>
                                </div>
                                <div class="flex-1 text-center py-4">
                                    <div class="text-7xl font-bold text-yellow-600">
                                        {{ $queue->queue_number }}
                                    </div>
                                    <div class="mt-2 text-sm text-gray-600">AT CASHIER</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- Fill empty slots based on pharmacy_windows setting --}}
                @php
                    $totalShown = $currentlyServing->count();
                    if ($displaySettings->require_cashier) {
                        $totalShown += $showingQueues['charging']
                            ->take($displaySettings->pharmacy_windows - $currentlyServing->count())
                            ->count();
                    }
                    $emptySlots = max(0, $displaySettings->pharmacy_windows - $totalShown);
                @endphp
                @for ($i = 0; $i < $emptySlots; $i++)
                    <div class="bg-gray-100 border-4 border-gray-300 rounded-xl shadow-xl overflow-hidden opacity-40">
                        <div class="flex items-center">
                            <div
                                class="bg-gray-300 text-gray-600 px-8 py-6 flex flex-col items-center justify-center min-w-[140px]">
                                <div class="text-sm font-semibold mb-1">WINDOW</div>
                                <div class="text-6xl font-bold">{{ $currentlyServing->count() + $i + 1 }}</div>
                            </div>
                            <div class="flex-1 text-center py-4">
                                <div class="text-7xl font-bold text-gray-400">- - -</div>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Right Side: DISPENSING COUNTERS --}}
        <div class="w-1/2 bg-gray-50 p-6">
            <div class="bg-green-700 text-white text-center py-4 rounded-t-xl shadow-lg mb-6">
                <h2 class="text-4xl font-bold">DISPENSING COUNTERS</h2>
            </div>

            <div class="space-y-4">
                {{-- Ready for Dispensing --}}
                @foreach ($showingQueues['ready'] as $queue)
                    <div
                        class="bg-white border-4 {{ $lastCalledQueue && $lastCalledQueue->id === $queue->id ? 'border-red-600 animate-pulse' : 'border-blue-600' }} rounded-xl shadow-xl overflow-hidden">
                        <div class="flex items-center">
                            {{-- Counter Badge --}}
                            <div
                                class="{{ $lastCalledQueue && $lastCalledQueue->id === $queue->id ? 'bg-red-600' : 'bg-blue-600' }} text-white px-8 py-6 flex flex-col items-center justify-center min-w-[140px]">
                                <div class="text-sm font-semibold mb-1">COUNTER</div>
                                <div class="text-6xl font-bold">{{ $queue->assigned_window ?? 'X' }}</div>
                            </div>

                            {{-- Queue Number --}}
                            <div class="flex-1 text-center py-4">
                                <div
                                    class="text-7xl font-bold {{ $lastCalledQueue && $lastCalledQueue->id === $queue->id ? 'text-red-600' : 'text-blue-600' }}">
                                    {{ $queue->queue_number }}
                                </div>
                                @if ($queue->priority === 'stat')
                                    <div class="mt-2">
                                        <span class="badge badge-error badge-lg text-lg px-4 py-3">STAT</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                {{-- Fill empty slots based on dispensing_counters setting --}}
                @php
                    $readyCount = $showingQueues['ready']->count();
                    $emptyCounterSlots = max(0, $displaySettings->dispensing_counters - $readyCount);
                @endphp
                @for ($i = 0; $i < $emptyCounterSlots; $i++)
                    <div class="bg-gray-100 border-4 border-gray-300 rounded-xl shadow-xl overflow-hidden opacity-40">
                        <div class="flex items-center">
                            <div
                                class="bg-gray-300 text-gray-600 px-8 py-6 flex flex-col items-center justify-center min-w-[140px]">
                                <div class="text-sm font-semibold mb-1">COUNTER</div>
                                <div class="text-6xl font-bold">{{ $readyCount + $i + 1 }}</div>
                            </div>
                            <div class="flex-1 text-center py-4">
                                <div class="text-7xl font-bold text-gray-400">- - -</div>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="bg-gradient-to-r from-green-700 to-green-600 text-white py-3 shadow-2xl">
        <div class="px-8">
            <div class="flex items-center justify-between">
                <div class="text-xl font-semibold">
                    Welcome to {{ config('app.name') }}
                </div>
                <div class="flex items-center gap-6 text-base font-semibold">
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-full bg-green-400 shadow-md ring-2 ring-white"></div>
                        <span>Preparing</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-full bg-yellow-400 shadow-md ring-2 ring-white"></div>
                        <span>At Cashier</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-full bg-blue-400 shadow-md ring-2 ring-white"></div>
                        <span>Ready to Claim</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-full bg-red-400 shadow-md ring-2 ring-white animate-pulse"></div>
                        <span>Now Calling</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sound notification --}}
    @if ($lastCalledQueue && $displaySettings->play_sound_alert)
        <audio id="notification-sound" autoplay>
            <source src="/sounds/notification.mp3" type="audio/mpeg">
        </audio>
    @endif

    @script
        <script>
            let lastCalledId = {{ $lastCalledQueue->id ?? 'null' }};

            $wire.on('queue-ready', (event) => {
                if (event.queueId !== lastCalledId) {
                    lastCalledId = event.queueId;
                    const audio = document.getElementById('notification-sound');
                    if (audio) {
                        audio.play().catch(e => console.log('Audio play failed:', e));
                    }
                }
            });

            if ('wakeLock' in navigator) {
                navigator.wakeLock.request('screen').catch(err => {
                    console.log('Wake lock error:', err);
                });
            }
        </script>
    @endscript
</div>
