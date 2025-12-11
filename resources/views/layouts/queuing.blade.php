<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Queue Display' }} - {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        /* Prevent screen dimming */
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        /* Fullscreen mode */
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

        /* Hide cursor after inactivity */
        body.hide-cursor {
            cursor: none;
        }
    </style>
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen">
        {{ $slot }}
    </div>

    <x-mary-toast />
    @livewireScripts

    <script>
        // Auto enter fullscreen
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log('Fullscreen error:', err);
                });
            }, 1000);
        });

        // Hide cursor after 5 seconds of inactivity
        let cursorTimeout;
        document.addEventListener('mousemove', function() {
            document.body.classList.remove('hide-cursor');
            clearTimeout(cursorTimeout);
            cursorTimeout = setTimeout(() => {
                document.body.classList.add('hide-cursor');
            }, 5000);
        });

        // Prevent context menu
        document.addEventListener('contextmenu', e => e.preventDefault());

        // Prevent F keys except F11
        document.addEventListener('keydown', function(e) {
            if (e.key.startsWith('F') && e.key !== 'F11') {
                e.preventDefault();
            }
        });
    </script>
</body>

</html>
