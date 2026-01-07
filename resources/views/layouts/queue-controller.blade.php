<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Queue Controller' }} - {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        /* Tablet-optimized styles */
        body {
            -webkit-tap-highlight-color: transparent;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            user-select: none;
        }

        /* Prevent zoom on double tap */
        * {
            touch-action: manipulation;
        }

        /* Larger touch targets for tablets */
        .touch-target {
            min-height: 44px;
            min-width: 44px;
        }

        /* Hide scrollbars but keep functionality */
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Smooth transitions */
        .smooth-transition {
            transition: all 0.2s ease-in-out;
        }

        /* Fullscreen support */
        body:-webkit-full-screen {
            width: 100%;
            height: 100%;
        }
    </style>
</head>

<body class="min-h-screen antialiased bg-base-200">
    {{-- Top Navigation Bar - Fixed --}}
    <div class="sticky top-0 z-50 border-b shadow-md bg-base-100 border-base-300">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                {{-- Left: App Info --}}
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <x-mary-icon name="o-queue-list" class="w-8 h-8 text-primary" />
                        <div>
                            <h1 class="text-lg font-bold leading-tight">Queue Controller</h1>
                            <p class="text-xs opacity-70">{{ auth()->user()->location->description }}</p>
                        </div>
                    </div>
                </div>

                {{-- Center: Live Clock --}}
                <div class="text-center">
                    <div class="text-2xl font-bold tabular-nums" id="queue-clock">
                        {{ now()->format('h:i:s A') }}
                    </div>
                    <div class="text-xs opacity-70">{{ now()->format('D, M j, Y') }}</div>
                </div>

                {{-- Right: Actions --}}
                <div class="flex items-center gap-2">
                    <x-mary-theme-toggle class="btn btn-circle btn-ghost btn-sm" />

                    <div class="dropdown dropdown-end">
                        <label tabindex="0" class="btn btn-circle btn-ghost btn-sm">
                            <x-mary-icon name="o-user-circle" class="w-6 h-6" />
                        </label>
                        <ul tabindex="0"
                            class="dropdown-content z-[1] menu p-2 shadow-lg bg-base-100 rounded-box w-52 mt-3">
                            <li class="menu-title">
                                <span>{{ auth()->user()->name }}</span>
                            </li>
                            <li><a href="{{ route('dashboard') }}">
                                    <x-mary-icon name="o-home" class="w-4 h-4" />
                                    Dashboard
                                </a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left">
                                        <x-mary-icon name="o-arrow-right-on-rectangle" class="w-4 h-4" />
                                        Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>

                    <button onclick="toggleFullscreen()" class="btn btn-circle btn-ghost btn-sm touch-target"
                        title="Toggle Fullscreen">
                        <x-mary-icon name="o-arrows-pointing-out" class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Content Area --}}
    <main class="h-[calc(100vh-73px)] overflow-hidden">
        {{ $slot }}
    </main>

    {{-- Toast Notifications --}}
    <x-mary-toast />

    @livewireScripts

    {{-- Fullscreen & Clock Scripts --}}
    <script>
        // Toggle fullscreen
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log(`Error: ${err.message}`);
                });
            } else {
                document.exitFullscreen();
            }
        }

        // Update clock
        function updateQueueClock() {
            const now = new Date();
            const hours = now.getHours();
            const minutes = now.getMinutes();
            const seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;

            const timeString =
                `${displayHours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')} ${ampm}`;

            const clockElement = document.getElementById('queue-clock');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateQueueClock();
            setInterval(updateQueueClock, 1000);
        });

        // Prevent accidental navigation
        window.addEventListener('beforeunload', function(e) {
            if (window.livewire && confirm) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // F11 for fullscreen
            if (e.key === 'F11') {
                e.preventDefault();
                toggleFullscreen();
            }
            // Escape to exit fullscreen
            if (e.key === 'Escape' && document.fullscreenElement) {
                document.exitFullscreen();
            }
        });
    </script>
</body>

</html>
