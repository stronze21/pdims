<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title . ' - ' : '' }}{{ config('app.name') }}</title>

    @include('layouts.partials.theme-init')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="pdims-theme font-sans antialiased">
    <div class="min-h-screen bg-base-200">
        {{ $slot }}
    </div>

    {{--  TOAST area --}}
    <x-mary-toast />
    @livewireScripts
</body>

</html>
