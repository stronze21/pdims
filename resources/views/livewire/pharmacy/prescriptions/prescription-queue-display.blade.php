<div class="min-h-screen bg-gradient-to-br from-primary/10 to-secondary/10" x-data="{ time: new Date() }"
    x-init="setInterval(() => { time = new Date() }, 500);
    setInterval(() => { $wire.call('loadQueues') }, {{ $displaySettings->auto_refresh_seconds * 1000 }});">

    {{-- Header --}}
    <div class="shadow-lg bg-gradient-to-r from-primary via-primary to-secondary text-primary-content">
        <div class="container px-8 py-6 mx-auto">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-5xl font-bold tracking-tight">{{ config('app.name') }}</h1>
                    <p class="text-xl opacity-90 mt-1">Prescription Queue Management System</p>
                </div>
                <div class="text-right">
                    <div class="text-6xl font-bold tabular-nums"
                        x-text="time.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })"></div>
                    <div class="text-xl opacity-90"
                        x-text="time.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' })">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container px-8 py-8 mx-auto">
        {{-- Now Preparing Section --}}
        @if ($currentlyServing->count() > 0)
            <div class="mb-8">
                <div
                    class="p-6 mb-6 text-center rounded-lg shadow-xl bg-gradient-to-r from-info to-info-content text-white">
                    <h2 class="mb-2 text-4xl font-bold">NOW PREPARING</h2>
                    <p class="text-xl opacity-90">Please wait at the designated window</p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    @foreach ($currentlyServing as $queue)
                        <div
                            class="relative p-8 text-center transition-all transform shadow-2xl rounded-2xl bg-gradient-to-br from-base-100 to-base-200 hover:scale-105 ring-4 ring-info ring-offset-4 animate-pulse">
                            {{-- Window Badge - Top Right --}}
                            @if ($queue->assigned_window)
                                <div class="absolute top-4 right-4">
                                    <div class="badge badge-lg badge-info gap-2 px-4 py-3 text-lg font-bold shadow-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                        </svg>
                                        WINDOW {{ $queue->assigned_window }}
                                    </div>
                                </div>
                            @endif

                            <div class="mb-3 badge badge-info badge-lg px-4 py-3 text-sm font-bold">PREPARING</div>
                            <div class="mb-3 text-7xl font-bold text-info drop-shadow-lg">
                                {{ $queue->queue_number }}
                            </div>
                            @if ($displaySettings->show_patient_name && $queue->patient)
                                <div class="text-3xl font-semibold mb-2">
                                    {{ mb_substr($queue->patient->patfirst, 0, 1) }}. {{ $queue->patient->patlast }}
                                </div>
                            @endif
                            <div class="mt-4 text-sm opacity-70 bg-base-300 rounded-full px-4 py-2 inline-block">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4 inline mr-1">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Wait: {{ $queue->getWaitTimeMinutes() }} min
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Charging Section --}}
        @if ($showingQueues['charging']->count() > 0)
            <div class="mb-8">
                <div
                    class="p-6 mb-6 text-center rounded-lg shadow-xl bg-gradient-to-r from-secondary to-accent text-white">
                    <h2 class="mb-2 text-4xl font-bold">AT CASHIER</h2>
                    <p class="text-xl opacity-90">Please proceed to payment counter</p>
                </div>

                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5">
                    @foreach ($showingQueues['charging'] as $queue)
                        <div
                            class="relative p-6 text-center transition-all transform shadow-xl rounded-xl bg-base-100 hover:scale-105 border-2 border-secondary">
                            {{-- Window Badge - Top --}}
                            @if ($queue->assigned_window)
                                <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                                    <div class="badge badge-secondary badge-sm gap-1 px-3 py-2 font-bold shadow-md">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-3 h-3">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                        </svg>
                                        W{{ $queue->assigned_window }}
                                    </div>
                                </div>
                            @endif

                            <div class="mb-2 badge badge-secondary badge-sm mt-2">CHARGING</div>
                            <div class="mb-2 text-4xl font-bold text-secondary">
                                {{ $queue->queue_number }}
                            </div>
                            @if ($displaySettings->show_patient_name && $queue->patient)
                                <div class="text-lg font-semibold truncate">
                                    {{ mb_substr($queue->patient->patfirst, 0, 1) }}. {{ $queue->patient->patlast }}
                                </div>
                            @endif
                            <div class="mt-2 flex items-center justify-center gap-1 text-xs opacity-70">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                </svg>
                                At Cashier
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Ready for Pickup Section --}}
        @if ($showingQueues['ready']->count() > 0)
            <div class="mb-8">
                <div
                    class="p-6 mb-6 text-center rounded-lg shadow-xl bg-gradient-to-r from-success to-green-600 text-white">
                    <h2 class="mb-2 text-4xl font-bold">READY FOR DISPENSING</h2>
                    <p class="text-xl opacity-90">Please proceed to the dispensing window</p>
                </div>

                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5">
                    @foreach ($showingQueues['ready'] as $queue)
                        <div
                            class="relative p-6 text-center transition-all transform shadow-xl rounded-xl bg-base-100 hover:scale-105
                            {{ $lastCalledQueue && $lastCalledQueue->id === $queue->id ? 'ring-4 ring-success ring-offset-4 animate-bounce' : 'border-2 border-success' }}">
                            {{-- Window Badge - Top --}}
                            @if ($queue->assigned_window)
                                <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                                    <div class="badge badge-success badge-sm gap-1 px-3 py-2 font-bold shadow-md">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-3 h-3">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                        </svg>
                                        W{{ $queue->assigned_window }}
                                    </div>
                                </div>
                            @endif

                            <div class="mb-2 badge badge-success badge-sm mt-2">READY</div>
                            <div class="mb-2 text-5xl font-bold text-success">
                                {{ $queue->queue_number }}
                            </div>
                            @if ($displaySettings->show_patient_name && $queue->patient)
                                <div class="text-lg font-semibold truncate">
                                    {{ mb_substr($queue->patient->patfirst, 0, 1) }}. {{ $queue->patient->patlast }}
                                </div>
                            @endif
                            <div class="mt-2 flex items-center justify-center gap-1 text-xs opacity-70">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ $queue->ready_at->format('h:i A') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Waiting Section --}}
        @if ($showingQueues['waiting']->count() > 0)
            <div>
                <div
                    class="p-4 mb-4 text-center rounded-lg shadow-lg bg-gradient-to-r from-warning to-yellow-600 text-white">
                    <h2 class="text-3xl font-bold">WAITING QUEUE</h2>
                </div>

                <div class="grid grid-cols-2 gap-3 md:grid-cols-5 lg:grid-cols-6">
                    @foreach ($showingQueues['waiting'] as $queue)
                        <div
                            class="p-4 text-center transition-all shadow-lg rounded-xl bg-base-100 hover:shadow-xl border border-warning">
                            <div class="mb-1 badge badge-warning badge-xs">WAITING</div>
                            <div class="mb-1 text-2xl font-bold">
                                {{ $queue->queue_number }}
                            </div>
                            @if ($queue->priority !== 'normal')
                                <div class="badge badge-error badge-xs">
                                    {{ strtoupper($queue->priority) }}
                                </div>
                            @endif
                            @if ($displaySettings->show_estimated_wait)
                                <div class="mt-2 text-xs opacity-70 flex items-center justify-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    ~{{ $queue->estimated_wait_minutes ?? 0 }}m
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- No Queues Message --}}
        @if (
            $currentlyServing->count() === 0 &&
                $showingQueues['charging']->count() === 0 &&
                $showingQueues['ready']->count() === 0 &&
                $showingQueues['waiting']->count() === 0)
            <div class="flex flex-col items-center justify-center py-24">
                <div class="mb-6 text-9xl">☕</div>
                <h2 class="mb-4 text-4xl font-bold text-gray-400">No Active Queues</h2>
                <p class="text-2xl text-gray-400">All prescriptions have been served!</p>
            </div>
        @endif
    </div>

    {{-- Footer Legend --}}
    <div class="fixed bottom-0 left-0 right-0 py-4 text-center shadow-2xl bg-base-100 border-t-4 border-primary">
        <div class="flex items-center justify-center gap-8 text-base font-semibold">
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-full bg-success shadow-md"></div>
                <span>Ready to Dispense</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-full bg-secondary shadow-md"></div>
                <span>At Cashier</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-full bg-info shadow-md"></div>
                <span>Now Preparing</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-5 h-5 rounded-full bg-warning shadow-md"></div>
                <span>Waiting</span>
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
