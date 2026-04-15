<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Pharmacy System') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles

</head>

<body class="min-h-screen font-sans antialiased bg-base-200 pb-20 lg:pb-0">

    {{-- NAVBAR (Top Header) --}}
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

    {{-- MAIN BODY --}}
    <x-mary-main with-nav full-width>
        {{-- SIDEBAR (Collapsible + Drawer) --}}
        <x-slot:sidebar drawer="main-drawer" collapsible class="border-r bg-base-100 flex flex-col">
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

            <div class="flex min-h-0 flex-1 flex-col">
                {{-- MENU ITEMS --}}
                <div class="min-h-0 flex-1 overflow-y-auto">
                    <x-mary-menu activate-by-route>
                        <x-mary-menu-item title="Dashboard" icon="o-home" link="{{ route('dashboard') }}" />

                        {{-- Inventory --}}
                        <x-mary-menu-sub title="Inventory" icon="o-cube">
                            @can('inventory-viewer')
                                <x-mary-menu-item title="Stock List" icon="o-list-bullet" link="{{ route('inventory.stocks.list') }}" />
                                <x-mary-menu-item title="Stock Summary" icon="o-table-cells" link="{{ route('inventory.stocks.summary') }}" />
                                <x-mary-menu-item title="Stock Card" icon="o-document-chart-bar" link="{{ route('inventory.stocks.card') }}" />
                            @endcan

                            @can('manage-reorder-levels')
                                <x-mary-menu-item title="Reorder Levels" icon="o-arrow-path" link="{{ route('inventory.stocks.reorder') }}" />
                                <x-mary-menu-item title="Reorder Levels (Auto)" icon="o-calculator" link="{{ route('inventory.stocks.reorder-computed') }}" />
                            @endcan

                            @can('view-iotrans')
                                <x-mary-menu-item title="IO Transactions" icon="o-arrows-right-left" link="{{ route('inventory.io-trans') }}" />
                            @endcan

                            @can('view-ris')
                                <x-mary-menu-item title="Ward RIS" icon="o-building-office" link="{{ route('inventory.ward-ris') }}" />
                            @endcan
                        </x-mary-menu-sub>


                        {{-- Dispensing --}}
                        @can('view-prescriptions')
                            <x-mary-menu-item title="Dispensing" icon="o-clipboard-document-list"
                                onclick="const width = screen.availWidth;
                                    const height = screen.availHeight;
                                    window.open(
                                        '{{ route('dispensing.view.enctr') }}',
                                        'dispensingApp',
                                        `toolbar=no,menubar=no,location=no,status=no,width=${width},height=${height},left=0,top=0`
                                    );
                                    return false;" />
                        @endcan


                        {{-- Records --}}
                        @can('view-patients')
                            <x-mary-menu-sub title="Records" icon="o-document-text">
                                <x-mary-menu-item title="Patients" icon="o-users" link="{{ route('records.patients.index') }}" />
                                <x-mary-menu-item title="May Go Home" icon="o-user-group"
                                    link="{{ route('records.for-discharge-patients') }}" />
                                <x-mary-menu-item title="Discharged Patients" icon="o-user-group"
                                    link="{{ route('records.discharged-patients') }}" />
                            </x-mary-menu-sub>
                        @endcan


                        {{-- Rx / Orders --}}
                        @can('view-prescriptions')
                            <x-mary-menu-sub title="Rx/Orders" icon="o-clipboard-document-list">
                                <x-mary-menu-item title="Out Patient Department" icon="o-user-group"
                                    link="{{ route('rx.opd') }}" />
                                <x-mary-menu-item title="Wards" icon="o-building-office-2"
                                    link="{{ route('rx.ward') }}" />
                                <x-mary-menu-item title="Emergency Room" icon="o-heart"
                                    link="{{ route('rx.er') }}" />
                            </x-mary-menu-sub>
                        @endcan


                        {{-- Purchases --}}
                        @canany('view-deliveries', 'view-eps')
                            <x-mary-menu-sub title="Purchases" icon="o-shopping-cart">

                                @can('view-deliveries')
                                    <x-mary-menu-item title="PIMS RIS" icon="o-document-text" link="{{ route('purchases.ris') }}" />
                                    <x-mary-menu-item title="Deliveries" icon="o-truck" link="{{ route('purchases.deliveries') }}" />
                                    <x-mary-menu-item title="Donations" icon="o-gift" link="{{ route('purchases.donations') }}" />
                                @endcan

                                @can('view-eps')
                                    <x-mary-menu-item title="Emergency Purchase" icon="o-bolt"
                                        link="{{ route('purchases.emergency-purchase') }}" />
                                @endcan

                            </x-mary-menu-sub>
                        @endcanany

                        {{-- Reports --}}
                        @can('view-reports')
                            <x-mary-menu-item title="Reports" icon="o-chart-bar" link="/reports" />
                        @endcan

                        {{-- Queue --}}
                        <x-mary-menu-sub title="Queueing" icon="o-clock">
                            <x-mary-menu-item title="Queue Controller" icon="o-device-tablet"
                                link="{{ route('prescriptions.queue.controller2') }}" />

                            <x-mary-menu-item title="Cashier Queue Controller" icon="o-banknotes"
                                link="{{ route('prescriptions.cashier.queue') }}" />

                            <x-mary-menu-item title="Queue TV Display" icon="o-computer-desktop"
                                link="{{ route('queue.display', ['locationCode' => 2]) }}" />

                            <x-mary-menu-item title="Queue Display Settings" icon="o-cog-6-tooth"
                                link="{{ route('prescriptions.queue.display-setting') }}" />
                        </x-mary-menu-sub>

                        {{-- Settings --}}
                        @can('view-settings')
                            <x-mary-menu-sub title="Settings" icon="o-cog-6-tooth">

                                <x-mary-menu-item title="Users" icon="o-user-group" link="{{ route('users.index') }}" />
                                <x-mary-menu-item title="Roles" icon="o-building-office" link="{{ route('roles.index') }}" />
                                <x-mary-menu-item title="Permissions" icon="o-shield-check"
                                    link="{{ route('permissions.index') }}" />

                                <x-mary-menu-item title="Non-PNF Drugs" icon="o-beaker"
                                    link="{{ route('pharmacy.non-pnf-drugs') }}" />

                                <x-mary-menu-item title="Zero-Billing Fund Sources" icon="o-hashtag"
                                    link="{{ route('settings.zero-billing') }}" />
                            </x-mary-menu-sub>
                        @endcan

                    </x-mary-menu>
                </div>

                @can('view-settings')
                    <div class="mt-auto border-t border-base-300 p-3">
                        <a href="{{ route('portal.index') }}"
                            class="group flex items-center gap-3 rounded-2xl border border-primary/20 bg-gradient-to-r from-primary/10 to-secondary/10 px-3 py-2.5 shadow-sm transition hover:border-primary/40 hover:bg-primary/15">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-primary-content shadow-sm shadow-primary/20">
                                <x-mary-icon name="o-globe-alt" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-black uppercase tracking-[0.22em] text-primary">Portal</span>
                                    <span class="badge badge-primary badge-xs">External</span>
                                </div>
                                <div class="truncate text-xs font-semibold text-base-content">Open Patient Portal Admin</div>
                            </div>
                            <x-mary-icon name="o-arrow-top-right-on-square" class="h-4 w-4 text-primary opacity-80" />
                        </a>
                    </div>
                @endcan
            </div>
        </x-slot:sidebar>

        {{-- PAGE CONTENT --}}
        <x-slot:content>
            <div class="p-4 lg:p-6">
                {{ $slot }}
            </div>
        </x-slot:content>
    </x-mary-main>

    {{-- TABLET / MOBILE BOTTOM NAV WITH SLIDE-UP DRAWER --}}
    <div x-data="{ openMore: false }" class="lg:hidden">

        {{-- Backdrop --}}
        <div x-cloak
            x-show="openMore"
            x-transition.opacity
            x-on:click="openMore = false"
            class="fixed inset-0 z-40 bg-black/30">
        </div>

        {{-- Slide-up Drawer --}}
        <div x-cloak
            x-show="openMore"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="fixed bottom-0 left-0 right-0 z-50 rounded-t-2xl border-t bg-base-100 shadow-2xl">

            {{-- Drawer Handle --}}
            <div class="flex justify-center py-2">
                <div class="h-1.5 w-12 rounded-full bg-base-300"></div>
            </div>

            {{-- Drawer Header --}}
            <div class="flex items-center justify-between px-4 pb-2">
                <h3 class="text-sm font-semibold">More Actions</h3>
                <button class="btn btn-ghost btn-xs" x-on:click="openMore = false">
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                </button>
            </div>

            {{-- Drawer Content --}}
            <div class="max-h-[65vh] overflow-y-auto px-4 pb-6">
                <div class="grid grid-cols-3 gap-3 sm:grid-cols-4">

                    @can('view-prescriptions')
                        <a href="{{ route('rx.opd') }}" class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-user-group class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">OPD</span>
                        </a>

                        <a href="{{ route('rx.ward') }}" class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-building-office-2 class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">Wards</span>
                        </a>

                        <a href="{{ route('rx.er') }}" class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-heart class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">ER</span>
                        </a>
                    @endcan

                    @can('view-iotrans')
                        <a href="{{ route('inventory.io-trans') }}"
                        class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-arrows-right-left class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">IO Trans</span>
                        </a>
                    @endcan

                    @can('view-ris')
                        <a href="{{ route('inventory.ward-ris') }}"
                        class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-building-office class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">Ward RIS</span>
                        </a>
                    @endcan

                    @can('view-deliveries')
                        <a href="{{ route('purchases.ris') }}"
                        class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-document-text class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">PIMS RIS</span>
                        </a>

                        <a href="{{ route('purchases.deliveries') }}"
                        class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-truck class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">Deliveries</span>
                        </a>

                        <a href="{{ route('purchases.donations') }}"
                        class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-gift class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">Donations</span>
                        </a>
                    @endcan

                    @can('view-eps')
                        <a href="{{ route('purchases.emergency-purchase') }}"
                        class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-bolt class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">Emergency Purchase</span>
                        </a>
                    @endcan

                    @can('view-reports')
                        <a href="/reports" class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-chart-bar class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">Reports</span>
                        </a>
                    @endcan

                    <a href="{{ route('teleconsult.lobby') }}"
                    class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                        <x-heroicon-o-video-camera class="w-5 h-5" />
                        <span class="text-[11px] text-center leading-tight">Teleconsult</span>
                    </a>

                    @can('view-settings')
                        <a href="{{ route('users.index') }}"
                        class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                            <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                            <span class="text-[11px] text-center leading-tight">Settings</span>
                        </a>
                    @endcan

                    <a href="{{ route('prescriptions.queue.controller2') }}"
                    class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                        <x-heroicon-o-device-tablet class="w-5 h-5" />
                        <span class="text-[11px] text-center leading-tight">Queue Controller</span>
                    </a>

                    <a href="{{ route('prescriptions.cashier.queue') }}"
                    class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                        <x-heroicon-o-banknotes class="w-5 h-5" />
                        <span class="text-[11px] text-center leading-tight">Cashier Queue</span>
                    </a>

                    <a href="{{ route('queue.display', ['locationCode' => 2]) }}"
                    class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                        <x-heroicon-o-computer-desktop class="w-5 h-5" />
                        <span class="text-[11px] text-center leading-tight">Queue Display</span>
                    </a>

                    <a href="{{ route('prescriptions.queue.display-setting') }}"
                    class="btn btn-outline h-auto min-h-20 flex-col gap-1 py-3">
                        <x-heroicon-o-cog-6-tooth class="w-5 h-5" />
                        <span class="text-[11px] text-center leading-tight">Display Settings</span>
                    </a>

                </div>
            </div>
        </div>

        {{-- Bottom Nav --}}
        <div class="fixed bottom-0 left-0 right-0 z-30 border-t bg-base-100 shadow-inner">
            <div class="flex items-center justify-around px-2 py-2">

                <x-mary-button
                    flat
                    icon="o-home"
                    label="Home"
                    link="{{ route('dashboard') }}" />

                @can('inventory-viewer')
                    <x-mary-button
                        flat
                        icon="o-cube"
                        label="Inventory"
                        link="{{ route('inventory.stocks.list') }}" />
                @endcan

                @can('view-prescriptions')
                    <button
                        class="btn btn-primary btn-circle btn-lg -mt-8 shadow-xl"
                        onclick="const width = screen.availWidth;
                            const height = screen.availHeight;
                            window.open(
                                '{{ route('dispensing.view.enctr') }}',
                                'dispensingApp',
                                `toolbar=no,menubar=no,location=no,status=no,width=${width},height=${height},left=0,top=0`
                            );
                            return false;">
                        <x-heroicon-o-clipboard-document-list class="w-6 h-6" />
                    </button>
                @endcan

                @can('view-patients')
                    <x-mary-button
                        flat
                        icon="o-users"
                        label="Patients"
                        link="{{ route('records.patients.index') }}" />
                @endcan

                <button class="btn btn-ghost btn-sm flex flex-col gap-0"
                        x-on:click="openMore = true">
                    <x-heroicon-o-squares-2x2 class="w-5 h-5" />
                    <span class="text-[11px]">More</span>
                </button>

            </div>
        </div>
    </div>

    {{-- TOAST NOTIFICATIONS --}}
    <x-mary-toast />

    @stack('scripts')
    @livewireScripts

    <!-- Global Confirmation Modal -->
    <div
        x-data
        :class="{ 'modal-open': $store.maryConfirm.open }"
        class="modal backdrop-blur-sm"
        >
        <div class="modal-box bg-base-100">
            <h3 class="font-bold text-lg" x-text="$store.maryConfirm.title"></h3>

            <p class="py-4" x-text="$store.maryConfirm.description"></p>

            <div class="modal-action">
                <!-- Cancel -->
                <button
                    type="button"
                    class="btn btn-ghost"
                    @click="$store.maryConfirm.close()"
                >
                    Cancel
                </button>

                <!-- Confirm -->
                <button
                    type="button"
                    class="btn btn-error"
                    @click="$store.maryConfirm.confirm()"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>
</body>

</html>
