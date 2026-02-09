<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Print' }} - {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; }
            @page { margin: 5mm; size: 80mm auto; }
        }
        .text-3xs { font-size: 0.6rem; line-height: 0.85rem; }
        .text-2xs { font-size: 0.65rem; line-height: 0.9rem; }
    </style>
</head>

<body class="font-sans antialiased bg-base-100">
    {{ $slot }}

    @livewireScripts
</body>

</html>
