<div class="min-h-screen bg-white flex flex-col" x-data="{ time: new Date() }" x-init="// Update clock
setInterval(() => { time = new Date() }, 500);

// Subscribe to Reverb channel for this location
const locationCode = '{{ $locationCode }}';
const channel = window.Echo.channel('pharmacy.location.' + locationCode);

// Listen for queue status changes
channel.listen('.queue.status.changed', (event) => {
    console.log('Queue status changed:', event);
    $wire.call('loadQueues');
});

// Listen for queue calls (plays sound)
channel.listen('.queue.called', (event) => {
    console.log('Queue called:', event);
    $wire.call('loadQueues');

    // Play notification sound
    const audio = document.getElementById('notification-sound');
    if (audio) {
        audio.play().catch(e => console.log('Audio play failed:', e));
    }
});

// Fallback refresh every 30 seconds
setInterval(() => {
    $wire.call('loadQueues');
}, 30000);">

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
    @php
        $hasCashier = $displaySettings->require_cashier;
        $hasReadyQueues = $showingQueues['ready']->isNotEmpty();
        // Layout: always show pharmacy windows + claiming column; optionally show cashier
        $pharmacyWidth = $hasCashier ? 'w-1/3' : 'w-1/2';
        $claimingWidth = $hasCashier ? 'w-1/3' : 'w-1/2';
        $cashierWidth = 'w-1/3';
    @endphp
    <div class="flex-1 flex">
        {{-- Left Side: PHARMACY WINDOWS --}}
        <div class="{{ $pharmacyWidth }} bg-gray-50 p-6 border-r-4 border-green-600">
            <div class="bg-green-700 text-white text-center py-4 rounded-t-xl shadow-lg mb-6">
                <h2 class="text-4xl font-bold">PHARMACY WINDOWS</h2>
                <p class="text-sm opacity-90 mt-1">Preparation</p>
            </div>

            <div class="space-y-4">
                @php
                    $windowsData = [];
                    // Group preparing queues by window
                    foreach ($currentlyServing as $queue) {
                        $windowsData[$queue->assigned_window ?? 0][] = [
                            'queue' => $queue,
                            'status' => 'preparing',
                        ];
                    }
                @endphp

                @for ($windowNum = 1; $windowNum <= $displaySettings->pharmacy_windows; $windowNum++)
                    @php
                        $windowQueues = collect($windowsData[$windowNum] ?? []);
                        $activeQueue = $windowQueues->first();
                        $waitingCount = $windowQueues->count() - 1;
                    @endphp

                    @if ($activeQueue)
                        @php
                            $queue = $activeQueue['queue'];
                        @endphp

                        <div
                            class="bg-white border-4 {{ $queue->priority === 'stat' ? 'border-red-600' : 'border-green-600' }} rounded-xl shadow-xl overflow-hidden">
                            <div class="flex items-center">
                                {{-- Window Badge --}}
                                <div
                                    class="bg-green-700 text-white px-8 py-6 flex flex-col items-center justify-center min-w-[140px]">
                                    <div class="text-sm font-semibold mb-1">WINDOW</div>
                                    <div class="text-6xl font-bold">{{ $windowNum }}</div>
                                    @if ($waitingCount > 0)
                                        <div class="mt-2 text-xs bg-white/20 rounded-full px-3 py-1">
                                            +{{ $waitingCount }} waiting
                                        </div>
                                    @endif
                                </div>

                                {{-- Queue Info --}}
                                <div class="flex-1 py-4">
                                    <div class="text-center">
                                        <div class="text-7xl font-bold text-green-700">
                                            {{ $queue->queue_number }}
                                        </div>
                                        <div class="mt-2">
                                            <span class="badge badge-lg badge-info text-lg px-4 py-3">
                                                PREPARING
                                            </span>
                                            @if ($queue->priority === 'stat')
                                                <span
                                                    class="badge badge-lg badge-error text-lg px-4 py-3 ml-2">STAT</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- Empty Window --}}
                        <div
                            class="bg-gray-100 border-4 border-gray-300 rounded-xl shadow-xl overflow-hidden opacity-40">
                            <div class="flex items-center">
                                <div
                                    class="bg-gray-300 text-gray-600 px-8 py-6 flex flex-col items-center justify-center min-w-[140px]">
                                    <div class="text-sm font-semibold mb-1">WINDOW</div>
                                    <div class="text-6xl font-bold">{{ $windowNum }}</div>
                                </div>
                                <div class="flex-1 text-center py-4">
                                    <div class="text-7xl font-bold text-gray-400">- - -</div>
                                    <div class="mt-2 text-sm text-gray-500">Available</div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endfor
            </div>
        </div>

        {{-- Middle: CASHIER QUEUE (Only if cashier is required) --}}
        @if ($hasCashier)
            <div class="{{ $cashierWidth }} bg-gray-50 p-6 border-r-4 border-yellow-600">
                <div class="bg-yellow-600 text-white text-center py-4 rounded-t-xl shadow-lg mb-6">
                    <h2 class="text-4xl font-bold">CASHIER QUEUE</h2>
                    <p class="text-sm opacity-90 mt-1">
                        @if ($displaySettings->cashier_location)
                            {{ $displaySettings->cashier_location }}
                        @else
                            Please proceed to payment
                        @endif
                    </p>
                </div>

                <div class="space-y-4">
                    {{-- Currently Being Served at Cashier --}}
                    @php
                        $cashierCurrent = $showingQueues['charging']
                            ->where('cashier_called_at', '!=', null)
                            ->sortByDesc('cashier_called_at')
                            ->first();
                        $cashierWaiting = $showingQueues['charging']
                            ->where('cashier_called_at', null)
                            ->sortBy('charging_at');
                    @endphp

                    @if ($cashierCurrent)
                        <div
                            class="bg-white border-4 border-red-600 ring-4 ring-red-300 rounded-xl shadow-xl overflow-hidden animate-pulse">
                            <div class="flex items-center">
                                <div
                                    class="bg-red-600 text-white px-8 py-6 flex flex-col items-center justify-center min-w-[120px]">
                                    <div class="text-sm font-semibold mb-1">NOW SERVING</div>
                                </div>
                                <div class="flex-1 py-4">
                                    <div class="text-center">
                                        <div class="text-7xl font-bold text-red-600">
                                            {{ $cashierCurrent->queue_number }}
                                        </div>
                                        <div class="mt-2 flex items-center justify-center gap-2">
                                            <span class="text-sm text-gray-600">From Window
                                                {{ $cashierCurrent->assigned_window }}</span>
                                            @if ($cashierCurrent->priority === 'stat')
                                                <span class="badge badge-error badge-sm">STAT</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @foreach ($cashierWaiting as $index => $queue)
                        <div class="bg-white border-4 border-yellow-400 rounded-xl shadow-xl overflow-hidden">
                            <div class="flex items-center">
                                <div
                                    class="bg-yellow-600 text-white px-8 py-6 flex flex-col items-center justify-center min-w-[120px]">
                                    <div class="text-sm font-semibold mb-1">POSITION</div>
                                    <div class="text-6xl font-bold">{{ $index + 1 }}</div>
                                </div>
                                <div class="flex-1 py-4">
                                    <div class="text-center">
                                        <div class="text-7xl font-bold text-yellow-600">
                                            {{ $queue->queue_number }}
                                        </div>
                                        <div class="mt-2 flex items-center justify-center gap-2">
                                            <span class="text-sm text-gray-600">From Window
                                                {{ $queue->assigned_window }}</span>
                                            @if ($queue->priority === 'stat')
                                                <span class="badge badge-error badge-sm">STAT</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if (!$cashierCurrent && $cashierWaiting->isEmpty())
                        <div
                            class="bg-gray-100 border-4 border-gray-300 rounded-xl shadow-xl overflow-hidden opacity-40">
                            <div class="flex items-center justify-center py-16">
                                <div class="text-center">
                                    <div class="text-4xl mb-4 text-gray-400">No Pending Payments</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Right Side: READY FOR CLAIMING --}}
        <div class="{{ $claimingWidth }} bg-gray-50 p-6">
            <div class="bg-blue-600 text-white text-center py-4 rounded-t-xl shadow-lg mb-6">
                <h2 class="text-4xl font-bold">READY FOR CLAIMING</h2>
                <p class="text-sm opacity-90 mt-1">Please proceed to the pharmacy window</p>
            </div>

            <div class="space-y-4">
                @php
                    $readyQueues = $showingQueues['ready']->sortByDesc('ready_at');
                    $latestReady = $readyQueues->first();
                @endphp

                @forelse ($readyQueues as $index => $queue)
                    @php
                        $isLatest = $latestReady && $latestReady->id === $queue->id;
                        $wasCalled = $queue->called_at && $queue->called_at >= ($queue->ready_at ?? now()->subDay());
                    @endphp
                    <div
                        class="bg-white border-4 {{ $wasCalled ? 'border-red-600 ring-4 ring-red-300' : 'border-blue-600' }} rounded-xl shadow-xl overflow-hidden {{ $wasCalled ? 'animate-pulse' : '' }}">
                        <div class="flex items-center">
                            {{-- Claiming Badge --}}
                            <div
                                class="{{ $wasCalled ? 'bg-red-600' : 'bg-blue-600' }} text-white px-8 py-6 flex flex-col items-center justify-center min-w-[140px]">
                                @if ($wasCalled)
                                    <div class="text-sm font-semibold mb-1">NOW CALLING</div>
                                @else
                                    <div class="text-sm font-semibold mb-1">WINDOW</div>
                                    <div class="text-6xl font-bold">{{ $queue->assigned_window ?? '-' }}</div>
                                @endif
                            </div>

                            {{-- Queue Info --}}
                            <div class="flex-1 py-4">
                                <div class="text-center">
                                    <div class="text-7xl font-bold {{ $wasCalled ? 'text-red-600' : 'text-blue-600' }}">
                                        {{ $queue->queue_number }}
                                    </div>
                                    <div class="mt-2">
                                        <span class="badge badge-lg badge-success text-lg px-4 py-3">
                                            READY TO CLAIM
                                        </span>
                                        @if ($queue->priority === 'stat')
                                            <span
                                                class="badge badge-lg badge-error text-lg px-4 py-3 ml-2">STAT</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div
                        class="bg-gray-100 border-4 border-gray-300 rounded-xl shadow-xl overflow-hidden opacity-40">
                        <div class="flex items-center justify-center py-16">
                            <div class="text-center">
                                <div class="text-4xl mb-4 text-gray-400">No Queues Ready</div>
                            </div>
                        </div>
                    </div>
                @endforelse
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
                    @if ($displaySettings->require_cashier)
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full bg-yellow-400 shadow-md ring-2 ring-white"></div>
                            <span>At Cashier</span>
                        </div>
                    @endif
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
