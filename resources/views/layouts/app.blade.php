<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Pharmacy System') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles

</head>

<body class="min-h-screen font-sans antialiased bg-base-200">

    {{-- 🌤️ NAVBAR (Top Header) --}}
    <x-mary-nav sticky full-width>
        <x-slot:brand>
            {{-- Drawer toggle for sidebar --}}
            <label for="main-drawer" class="mr-3 lg:hidden">
                <x-mary-icon name="o-bars-3" class="cursor-pointer" />
            </label>

            {{-- Brand Name --}}
            <div class="text-lg font-semibold text-primary">{{ config('app.name') }}</div>
        </x-slot:brand>

        {{-- Right Side Actions --}}
        <x-slot:actions>
            <x-mary-theme-toggle class="btn btn-circle btn-ghost" />
            <x-mary-button icon="o-bell" tooltip-bottom="Notifications" class="tooltip-bottom btn-ghost btn-sm" />
            <x-mary-button icon="o-user-circle" tooltip-bottom="Profile" class="tooltip-bottom btn-ghost btn-sm" />
        </x-slot:actions>
    </x-mary-nav>

    {{-- 🌙 MAIN BODY --}}
    <x-mary-main with-nav full-width>
        {{-- 🧱 SIDEBAR (Collapsible + Drawer) --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="border-r bg-base-100">
            {{-- User Info --}}
            @if ($user = auth()->user())
                <x-mary-list-item :item="$user" value="name" sub-value="email" no-separator no-hover
                    class="pt-2">
                    <x-slot:actions>
                        <x-mary-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="Logout"
                            no-wire-navigate link="{{ route('logout') }}" />
                    </x-slot:actions>
                </x-mary-list-item>
                <x-mary-menu-separator />
            @endif

            {{-- MENU ITEMS --}}
            <x-mary-menu activate-by-route>
                <x-mary-menu-item title="Dashboard" icon="o-home" link="{{ route('dashboard') }}" />
                <x-mary-menu-item title="Inventory" icon="o-cube" link="/inventory" />
                <x-mary-menu-item title="Dispensing" icon="o-clipboard-document-list" link="/dispensing" />
                <x-mary-menu-item title="Patients" icon="o-users" link="{{ route('records.patients.index') }}" />
                <x-mary-menu-item title="Reports" icon="o-chart-bar" link="/reports" />
                <x-mary-menu-sub title="Settings" icon="o-cog-6-tooth">
                    <x-mary-menu-item title="Users" icon="o-user-group" link="{{ route('users.index') }}" />
                    <x-mary-menu-item title="Roles" icon="o-building-office" link="{{ route('roles.index') }}" />
                    <x-mary-menu-item title="Permissions" icon="o-shield-check"
                        link="{{ route('permissions.index') }}" />
                </x-mary-menu-sub>
            </x-mary-menu>
        </x-slot:sidebar>

        {{-- 📄 PAGE CONTENT --}}
        <x-slot:content>
            <div class="p-4 lg:p-6">
                {{ $slot }}
            </div>
        </x-slot:content>
    </x-mary-main>

    {{-- 📱 BOTTOM NAVIGATION (Visible only on mobile/tablets) --}}
    <div class="fixed bottom-0 left-0 right-0 flex justify-around py-2 border-t shadow-inner bg-base-100 lg:hidden">
        <x-mary-button flat icon="o-home" label="Home" link="/" />
        <x-mary-button flat icon="o-cube" label="Inventory" link="/inventory" />
        <x-mary-button flat icon="o-clipboard-document-list" label="Dispense" link="/dispensing" />
        <x-mary-button flat icon="o-users" label="Patients" link="/patients" />
        <x-mary-button flat icon="o-cog-6-tooth" label="Settings" link="/settings" />
    </div>

    {{-- 🔔 TOAST NOTIFICATIONS --}}
    <x-mary-toast />

    @livewireScripts
</body>

</html>
