<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">

    <title>Portal Administration - {{ config('app.name', 'PDIMS') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-base-200 text-base-content antialiased">
    <div class="relative min-h-screen overflow-hidden">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-24 top-20 h-72 w-72 rounded-full bg-primary/10 blur-3xl"></div>
            <div class="absolute right-0 top-0 h-96 w-96 rounded-full bg-secondary/10 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/3 h-80 w-80 rounded-full bg-info/10 blur-3xl"></div>
        </div>

        <div class="relative flex min-h-screen">
            <aside class="hidden w-80 shrink-0 border-r border-base-300 bg-base-100/90 backdrop-blur-xl lg:flex lg:flex-col">
                <div class="border-b border-base-300 px-6 py-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary text-primary-content shadow-lg shadow-primary/20">
                            <x-mary-icon name="o-globe-alt" class="h-6 w-6" />
                        </div>
                        <div>
                            <div class="text-lg font-black tracking-tight text-primary">Salun-at Portal</div>
                            <div class="text-xs uppercase tracking-[0.28em] text-base-content/50">Patient web app admin</div>
                        </div>
                    </div>
                </div>

                @if ($user = auth()->user())
                    <div class="px-6 py-5">
                        <div class="rounded-3xl border border-base-300 bg-base-200/60 p-4 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary text-secondary-content font-bold">
                                    {{ substr($user->name ?? 'U', 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate font-semibold text-base-content">{{ $user->name }}</div>
                                    <div class="truncate text-sm text-base-content/60">{{ $user->email }}</div>
                                    <div class="mt-1 text-[11px] uppercase tracking-[0.2em] text-base-content/40">Signed in</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <nav class="flex-1 overflow-y-auto px-4 pb-6">
                    <div class="mb-3 px-2 text-xs font-semibold uppercase tracking-[0.25em] text-base-content/40">
                        Portal Modules
                    </div>

                    <a href="{{ route('settings.portal.users') }}"
                        class="mb-2 flex items-center gap-3 rounded-2xl px-4 py-3 transition {{ request()->routeIs('settings.portal.users') ? 'bg-primary text-primary-content shadow-lg shadow-primary/20' : 'text-base-content/70 hover:bg-base-200' }}">
                        <x-mary-icon name="o-user-group" class="h-5 w-5" />
                        <div class="min-w-0">
                            <div class="font-semibold">Portal Users</div>
                            <div class="text-xs opacity-70">Accounts and linked patients</div>
                        </div>
                    </a>

                    <a href="{{ route('settings.portal.refills') }}"
                        class="mb-2 flex items-center gap-3 rounded-2xl px-4 py-3 transition {{ request()->routeIs('settings.portal.refills') ? 'bg-primary text-primary-content shadow-lg shadow-primary/20' : 'text-base-content/70 hover:bg-base-200' }}">
                        <x-mary-icon name="o-arrow-path" class="h-5 w-5" />
                        <div class="min-w-0">
                            <div class="font-semibold">Refill Requests</div>
                            <div class="text-xs opacity-70">Review and process refills</div>
                        </div>
                    </a>

                    <a href="{{ route('settings.portal.chat') }}"
                        class="mb-2 flex items-center gap-3 rounded-2xl px-4 py-3 transition {{ request()->routeIs('settings.portal.chat') ? 'bg-primary text-primary-content shadow-lg shadow-primary/20' : 'text-base-content/70 hover:bg-base-200' }}">
                        <x-mary-icon name="o-chat-bubble-left-right" class="h-5 w-5" />
                        <div class="min-w-0">
                            <div class="font-semibold">Chat Conversations</div>
                            <div class="text-xs opacity-70">Manage portal messages</div>
                        </div>
                    </a>

                    <a href="{{ route('portal.teleconsult.lobby') }}"
                        class="mb-2 flex items-center gap-3 rounded-2xl px-4 py-3 transition {{ request()->routeIs('portal.teleconsult.*') ? 'bg-primary text-primary-content shadow-lg shadow-primary/20' : 'text-base-content/70 hover:bg-base-200' }}">
                        <x-mary-icon name="o-video-camera" class="h-5 w-5" />
                        <div class="min-w-0">
                            <div class="font-semibold">Teleconsult</div>
                            <div class="text-xs opacity-70">Schedule and start sessions</div>
                        </div>
                    </a>

                    <div class="mt-6 rounded-3xl border border-base-300 bg-base-200/50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.25em] text-base-content/40">Workspace</div>
                        <p class="mt-2 text-sm text-base-content/70">
                            This section is intentionally styled like a separate administrative portal so staff can switch context faster.
                        </p>
                    </div>
                </nav>

                <div class="border-t border-base-300 p-4">
                    <a href="{{ route('dashboard') }}"
                        class="flex items-center justify-center gap-2 rounded-2xl border border-base-300 bg-base-100 px-4 py-3 text-sm font-semibold text-base-content transition hover:bg-base-200">
                        <x-mary-icon name="o-arrow-left" class="h-4 w-4" />
                        Back to Main App
                    </a>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-30 border-b border-base-300 bg-base-100/85 backdrop-blur-xl">
                    <div class="flex items-center gap-4 px-4 py-3 lg:px-6">
                        <label for="portal-drawer" class="btn btn-ghost btn-sm lg:hidden">
                            <x-mary-icon name="o-bars-3" class="h-5 w-5" />
                        </label>

                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold uppercase tracking-[0.25em] text-base-content/50">Portal Administration</div>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="dropdown dropdown-end lg:hidden">
                                <label tabindex="0" class="btn btn-ghost btn-sm">
                                    <x-mary-icon name="o-bars-3" class="h-5 w-5" />
                                </label>
                                <ul tabindex="0" class="menu dropdown-content z-50 mt-3 w-64 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-xl">
                                    <li><a href="{{ route('settings.portal.users') }}">Portal Users</a></li>
                                    <li><a href="{{ route('settings.portal.refills') }}">Refill Requests</a></li>
                                    <li><a href="{{ route('settings.portal.chat') }}">Chat Conversations</a></li>
                                    <li><a href="{{ route('portal.teleconsult.lobby') }}">Teleconsult</a></li>
                                    <li class="mt-1 border-t border-base-300 pt-1"><a href="{{ route('dashboard') }}">Back to Main App</a></li>
                                </ul>
                            </div>

                            <x-mary-theme-toggle class="btn btn-circle btn-ghost" />
                            <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm hidden sm:inline-flex">
                                <x-mary-icon name="o-arrow-left" class="h-4 w-4" />
                                Main App
                            </a>
                        </div>
                    </div>
                </header>

                <main class="flex-1 overflow-y-auto px-4 py-4 lg:px-6 lg:py-6">
                    <div class="mx-auto w-full max-w-[1600px]">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </div>

    @livewireScripts
    <x-mary-toast />
    @stack('scripts')
</body>

</html>
