<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="theme-color" content="#000000">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Point of Sale' }} - {{ config('app.name', 'Motorcycle Inventory') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    {{-- <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" /> --}}

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Styles -->
    @livewireStyles
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
</head>

<body class="font-sans antialiased bg-base-50">
    <!-- Minimal Top Bar -->
    <div class="border-b shadow-sm bg-base border-base-200">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Left side - Logo/Title -->
                <div class="flex items-center">
                    <div class="flex items-center flex-shrink-0">
                        <x-heroicon-o-computer-desktop class="w-8 h-8 text-primary" />
                        <span class="ml-2 text-xl font-semibold text-base-900">MMMH & MC - Pharmacy Dispensing</span>
                    </div>
                </div>

                <!-- Right side - User info and actions -->
                <div class="flex items-center space-x-4">
                    <!-- Current Date/Time -->
                    <div class="text-sm text-base-500">
                        <div>{{ now()->format('M d, Y') }}</div>
                        <div class="text-xs">{{ now()->format('h:i A') }}</div>
                    </div>

                    <x-mary-theme-toggle class="btn btn-circle btn-ghost" />

                    <!-- User Menu -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open"
                            class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            <div class="flex items-center space-x-2">
                                <div class="flex items-center justify-center w-8 h-8 text-base rounded-full bg-primary">
                                    {{ substr(auth()->user()->name, 0, 1) }}
                                </div>
                                <div class="text-left">
                                    <div class="text-sm font-medium text-base-700">{{ auth()->user()->name }}</div>
                                    <div class="text-xs text-base-500">{{ ucfirst(auth()->user()->role) }}</div>
                                </div>
                                <x-heroicon-o-chevron-down class="w-4 h-4 text-base-400" />
                            </div>
                        </button>
                        <div x-show="open" @click.away="open = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 z-50 w-48 mt-2 origin-top-right rounded-md shadow-lg bg-base ring-1 ring-black ring-opacity-5 bg-base">
                            <div class="py-1 bg-base-100 rounded-md">
                                <a href="{{ route('dashboard') }}"
                                    class="flex items-center px-4 py-2 text-sm text-base-700 hover:bg-base-100">
                                    <x-heroicon-o-home class="w-4 h-4 mr-2" />
                                    Back to Dashboard
                                </a>
                                <div class="border-t border-base-100"></div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit"
                                        class="flex items-center w-full px-4 py-2 text-sm text-base-700 hover:bg-base-100">
                                        <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4 mr-2" />
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Fullscreen Toggle -->
                    <button onclick="toggleFullscreen()"
                        class="p-2 rounded-lg text-base-400 hover:text-base-600 hover:bg-base-100"
                        title="Toggle Fullscreen">
                        <x-heroicon-o-arrows-pointing-out class="w-5 h-5" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-1">
        <div class="h-[calc(100vh-4rem)]">
            {{ $slot }}
        </div>
    </main>

    <!-- Toast Notifications -->
    <x-mary-toast />

    @livewireScripts

    <!-- Fullscreen Toggle Script -->
    <script>
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log(`Error attempting to enable fullscreen: ${err.message}`);
                });
            } else {
                document.exitFullscreen();
            }
        }

        // Auto-refresh time every minute
        setInterval(function() {
            const timeElements = document.querySelectorAll('.current-time');
            const now = new Date();
            timeElements.forEach(element => {
                element.textContent = now.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            });
        }, 60000);

        // Keyboard shortcuts for POS
        document.addEventListener('keydown', function(e) {
            // F11 for fullscreen
            if (e.key === 'F11' || (e.key === 'Enter' && e.altKey)) {
                e.preventDefault();
                toggleFullscreen();
            }

            // Escape to exit fullscreen
            if (e.key === 'Escape' && document.fullscreenElement) {
                document.exitFullscreen();
            }
        });

        // Prevent accidental page refresh
        window.addEventListener('beforeunload', function(e) {
            if (window.livewire && window.livewire.find('point-of-sale')) {
                const component = window.livewire.find('point-of-sale');
                if (component.cartItems && component.cartItems.length > 0) {
                    e.preventDefault();
                    e.returnValue = 'You have items in your cart. Are you sure you want to leave?';
                    return e.returnValue;
                }
            }
        });
    </script>

    <!-- Custom POS Styles -->
    <style>
        /* Fullscreen styles */
        body:-webkit-full-screen {
            width: 100%;
            height: 100%;
        }

        body:-moz-full-screen {
            width: 100%;
            height: 100%;
        }

        body:fullscreen {
            width: 100%;
            height: 100%;
        }

        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
        }

        /* Mobile responsiveness for POS */
        @media (max-width: 768px) {
            .pos-mobile-stack {
                flex-direction: column;
            }
        }
    </style>
</body>

</html>
