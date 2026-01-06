<div class="min-h-screen bg-gradient-to-br from-primary/10 to-secondary/10" x-data="{ time: new Date() }"
    x-init="setInterval(() => { time = new Date() }, 500);
    setInterval(() => { $wire.call('loadQueues') }, {{ $displaySettings->auto_refresh_seconds * 500 }});">

    {{-- Header --}}
    <div class="shadow-lg bg-primary text-primary-content">
        <div class="container px-8 py-6 mx-auto">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold">{{ config('app.name') }}</h1>
                    <p class="text-lg opacity-90">Prescription Queue System</p>
                </div>
                <div class="text-right">
                    <div class="text-5xl font-bold"
                        x-text="time.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })"></div>
                    <div class="text-xl opacity-90"
                        x-text="time.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container px-8 py-8 mx-auto">
        {{-- Now Serving Section --}}
        @if ($currentlyServing->count() > 0)
            <div class="mb-8">
                <div class="p-6 mb-6 text-center rounded-lg shadow-xl bg-warning text-warning-content">
                    <h2 class="mb-2 text-3xl font-bold">NOW PREPARING</h2>
                    <p class="text-lg opacity-90">Please wait at the counter</p>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    @foreach ($currentlyServing as $queue)
                        <div
                            class="p-8 text-center transition-all transform shadow-2xl rounded-xl bg-base-100 hover:scale-105 animate-pulse">
                            <div class="mb-3 badge badge-info badge-lg">PREPARING</div>
                            <div class="mb-2 text-6xl font-bold text-info">
                                {{ $queue->queue_number }}
                            </div>
                            @if ($displaySettings->show_patient_name && $queue->patient)
                                <div class="text-2xl font-semibold">
                                    {{ mb_substr($queue->patient->patfirst, 0, 1) }}. {{ $queue->patient->patlast }}
                                </div>
                            @endif
                            <div class="mt-4 text-sm opacity-70">
                                Wait: {{ $queue->getWaitTimeMinutes() }} min
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Ready for Pickup Section --}}
        @if ($showingQueues['ready']->count() > 0)
            <div class="mb-8">
                <div class="p-6 mb-6 text-center rounded-lg shadow-xl bg-success text-success-content">
                    <h2 class="mb-2 text-3xl font-bold">READY FOR PICKUP</h2>
                    <p class="text-lg opacity-90">Please proceed to the counter</p>
                </div>

                <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-5">
                    @foreach ($showingQueues['ready'] as $queue)
                        <div
                            class="p-6 text-center transition-all transform shadow-xl rounded-xl bg-base-100 hover:scale-105
                            {{ $lastCalledQueue && $lastCalledQueue->id === $queue->id ? 'ring-4 ring-success animate-bounce' : '' }}">
                            <div class="mb-2 badge badge-success badge-sm">READY</div>
                            <div class="mb-2 text-4xl font-bold text-success">
                                {{ $queue->queue_number }}
                            </div>
                            @if ($displaySettings->show_patient_name && $queue->patient)
                                <div class="text-lg font-semibold truncate">
                                    {{ mb_substr($queue->patient->patfirst, 0, 1) }}. {{ $queue->patient->patlast }}
                                </div>
                            @endif
                            <div class="mt-2 text-xs opacity-70">
                                Ready: {{ $queue->ready_at->format('h:i A') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Waiting Section --}}
        @if ($showingQueues['waiting']->count() > 0)
            <div>
                <div class="p-4 mb-4 text-center rounded-lg shadow-lg bg-base-200">
                    <h2 class="text-2xl font-bold">WAITING QUEUE</h2>
                </div>

                <div class="grid grid-cols-2 gap-3 md:grid-cols-5 lg:grid-cols-6">
                    @foreach ($showingQueues['waiting'] as $queue)
                        <div class="p-4 text-center transition-all shadow-lg rounded-xl bg-base-100 hover:shadow-xl">
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
                                <div class="mt-2 text-xs opacity-70">
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
                $showingQueues['ready']->count() === 0 &&
                $showingQueues['waiting']->count() === 0)
            <div class="flex flex-col items-center justify-center py-24">
                <div class="mb-6 text-9xl">☕</div>
                <h2 class="mb-4 text-4xl font-bold text-gray-400">No Active Queues</h2>
                <p class="text-2xl text-gray-400">All prescriptions have been served!</p>
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="fixed bottom-0 left-0 right-0 py-4 text-center shadow-lg bg-base-200">
        <div class="flex items-center justify-center gap-8 text-sm">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded-full bg-success"></div>
                <span>Ready for Pickup</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded-full bg-info"></div>
                <span>Now Preparing</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded-full bg-warning"></div>
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
            // Auto-refresh and notifications
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

            // Keep screen awake
            if ('wakeLock' in navigator) {
                navigator.wakeLock.request('screen').catch(err => {
                    console.log('Wake lock error:', err);
                });
            }
        </script>
    @endscript
</div>
