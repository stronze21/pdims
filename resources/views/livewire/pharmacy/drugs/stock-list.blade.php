<div class="flex flex-col px-5 mx-auto max-w-screen">
    <x-mary-header title="Drugs and Medicine Stock Inventory" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <div class="flex items-center space-x-2 px-3 py-1 bg-white rounded-lg shadow-sm border border-gray-200">
                <x-mary-icon name="o-map-pin" class="w-4 h-4 text-blue-600" />
                <span class="text-sm font-semibold text-gray-700">{{ auth()->user()->location->description }}</span>
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <div class="flex space-x-2">
                @can('add-stock-item')
                    <x-mary-button label="Add Item" icon="o-plus"
                        class="btn-sm btn-primary shadow-md hover:shadow-lg transition-shadow" wire:click="openAddModal" />
                @endcan
                @can('filter-stocks-location')
                    <div class="flex space-x-2">
                        <select class="select select-bordered select-sm shadow-sm" wire:model.live="location_id">
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->description }}</option>
                            @endforeach
                        </select>
                    </div>
                @endcan
                <x-mary-button label="Sync" icon="o-arrow-path"
                    class="btn-sm btn-info shadow-md hover:shadow-lg transition-shadow" wire:click="sync_items" />
            </div>
        </x-slot:actions>
    </x-mary-header>

    <div x-data="{
        stocks: [],
        page: 1,
        loading: false,
        hasMore: true,
        locationId: {{ $location_id }},
        chargeCodes: @js($charge_codes->pluck('chrgdesc')->unique()->values()),
        filters: {
            chrgdesc: '',
            drug_concat: '',
            dmselprice: '',
            stock_bal: '',
            exp_date_from: '',
            exp_date_to: '',
            lot_no: ''
        },
        filterTimeout: null,
    
        async loadStocks(resetPage = false) {
            if (this.loading || (!this.hasMore && !resetPage)) return;
    
            if (resetPage) {
                this.page = 1;
                this.stocks = [];
                this.hasMore = true;
            }
    
            this.loading = true;
    
            try {
                const params = new URLSearchParams({
                    location_id: this.locationId,
                    page: this.page,
                    ...this.filters
                });
    
                const response = await fetch(`/api/stocks?${params}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
    
                const result = await response.json();
    
                if (resetPage) {
                    this.stocks = result.data;
                } else {
                    this.stocks = [...this.stocks, ...result.data];
                }
    
                this.hasMore = result.has_more;
                this.page++;
            } catch (error) {
                console.error('Error loading stocks:', error);
            } finally {
                this.loading = false;
            }
        },
    
        applyFilters() {
            clearTimeout(this.filterTimeout);
            this.filterTimeout = setTimeout(() => {
                this.loadStocks(true);
            }, 500);
        },
    
        shouldApplyTextFilter(value, minChars = 3) {
            return value.length === 0 || value.length >= minChars;
        },
    
        handleTextFilter(filterName, value, minChars = 3) {
            this.filters[filterName] = value;
            if (this.shouldApplyTextFilter(value, minChars)) {
                this.applyFilters();
            }
        },
    
        clearFilters() {
            this.filters = {
                chrgdesc: '',
                drug_concat: '',
                dmselprice: '',
                stock_bal: '',
                exp_date_from: '',
                exp_date_to: '',
                lot_no: ''
            };
            this.loadStocks(true);
        },
    
        setupScrollListener() {
            let scrollTimeout;
            const tableBody = document.getElementById('stocks-table-body');
            if (tableBody) {
                tableBody.addEventListener('scroll', () => {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(() => {
                        const scrollHeight = tableBody.scrollHeight;
                        const scrollTop = tableBody.scrollTop;
                        const clientHeight = tableBody.clientHeight;
    
                        if (scrollTop + clientHeight >= scrollHeight - 200) {
                            this.loadStocks();
                        }
                    }, 100);
                });
            }
        },
    
        resetAndLoad() {
            this.clearFilters();
        },
    
        formatNumber(value, decimals) {
            return parseFloat(value).toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });
        },
    
        formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
        },
    
        getExpiryBadge(expDateStr, stockBal) {
            const expDate = new Date(expDateStr);
            const now = new Date();
            const months = Math.floor((expDate - now) / (1000 * 60 * 60 * 24 * 30));
    
            if (stockBal == 0) return 'badge-ghost';
            if (expDate < now) return 'badge-error';
            if (months < 6) return 'badge-warning';
            return 'badge-success';
        }
    }" x-init="loadStocks();
    setupScrollListener();
    $wire.on('refresh-stocks', () => {
        resetAndLoad();
    });
    $wire.on('location-changed', (event) => {
        locationId = event.locationId;
        loadStocks(true);
    });">

        <div
            class="flex items-center justify-between px-6 py-3 mb-3 bg-white rounded-xl shadow-md border border-gray-100 sticky top-0 z-20">
            <div class="flex items-center space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-semibold text-gray-700">Expiry Status:</span>
            </div>
            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-1.5">
                    <span class="badge badge-xs badge-ghost shadow-sm"></span>
                    <span class="text-xs text-gray-600">Out of Stock</span>
                </div>
                <div class="w-px h-4 bg-gray-300"></div>
                <div class="flex items-center space-x-1.5">
                    <span class="badge badge-xs badge-success shadow-sm"></span>
                    <span class="text-xs text-gray-600">≥6 Months</span>
                </div>
                <div class="w-px h-4 bg-gray-300"></div>
                <div class="flex items-center space-x-1.5">
                    <span class="badge badge-xs badge-warning shadow-sm"></span>
                    <span class="text-xs text-gray-600">&lt;6 Months</span>
                </div>
                <div class="w-px h-4 bg-gray-300"></div>
                <div class="flex items-center space-x-1.5">
                    <span class="badge badge-xs badge-error shadow-sm"></span>
                    <span class="text-xs text-gray-600">Expired</span>
                </div>
            </div>
            <div class="flex items-center space-x-2 px-3 py-1 bg-blue-50 rounded-lg border border-blue-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span class="text-sm font-semibold text-blue-700" x-text="stocks.length"></span>
                <span class="text-xs text-blue-600">items</span>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto max-h-[calc(100vh-300px)]" id="stocks-table-body">
                <table class="table table-sm">
                    <thead class="bg-gradient-to-r from-slate-700 via-slate-600 to-slate-700 sticky top-0 z-10">
                        <tr>
                            <th class="py-6 px-4 border-r border-slate-500">
                                <div class="flex flex-col space-y-2">
                                    <div
                                        class="flex items-center space-x-2 text-white text-xs font-bold uppercase tracking-wide">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Source of Fund</span>
                                    </div>
                                    <select x-model="filters.chrgdesc" @change="applyFilters()"
                                        class="select select-xs bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 w-full rounded-lg shadow-sm [&>option]:bg-white [&>option]:text-gray-800">
                                        <option value="" class="bg-white text-gray-800">All Sources</option>
                                        <template x-for="code in chargeCodes" :key="code">
                                            <option :value="code" x-text="code"
                                                class="bg-white text-gray-800"></option>
                                        </template>
                                    </select>
                                </div>
                            </th>
                            <th class="py-6 px-4 border-r border-slate-500">
                                <div
                                    class="flex items-center space-x-2 text-white text-xs font-bold uppercase tracking-wide">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>Balance as of</span>
                                </div>
                            </th>
                            <th class="py-6 px-4 border-r border-slate-500">
                                <div class="flex flex-col space-y-2">
                                    <div
                                        class="flex items-center space-x-2 text-white text-xs font-bold uppercase tracking-wide">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                        </svg>
                                        <span>Generic Drug Name</span>
                                    </div>
                                    <input type="text" x-model="filters.drug_concat"
                                        @input="handleTextFilter('drug_concat', $event.target.value, 3)"
                                        placeholder="Min 3 chars..."
                                        class="input input-xs bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400 w-full rounded-lg shadow-sm">
                                    <span x-show="filters.drug_concat.length > 0 && filters.drug_concat.length < 3"
                                        class="text-xs text-yellow-300 italic">
                                        Type <span x-text="3 - filters.drug_concat.length"></span> more char(s)
                                    </span>
                                </div>
                            </th>
                            <th class="py-6 px-4 text-end border-r border-slate-500">
                                <div class="flex flex-col space-y-2">
                                    <div
                                        class="flex items-center justify-end space-x-2 text-white text-xs font-bold uppercase tracking-wide">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Price</span>
                                    </div>
                                    <input type="text" x-model="filters.dmselprice" @input="applyFilters()"
                                        placeholder="Filter price..."
                                        class="input input-xs bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400 w-full rounded-lg shadow-sm">
                                </div>
                            </th>
                            <th class="py-6 px-4 text-end border-r border-slate-500">
                                <div class="flex flex-col space-y-2">
                                    <div
                                        class="flex items-center justify-end space-x-2 text-white text-xs font-bold uppercase tracking-wide">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        <span>Stock Balance</span>
                                    </div>
                                    <input type="text" x-model="filters.stock_bal"
                                        @input="handleTextFilter('stock_bal', $event.target.value, 3)"
                                        placeholder="Min 3 chars..."
                                        class="input input-xs bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400 w-full rounded-lg shadow-sm">
                                    <span x-show="filters.stock_bal.length > 0 && filters.stock_bal.length < 3"
                                        class="text-xs text-yellow-300 italic">
                                        Type <span x-text="3 - filters.stock_bal.length"></span> more char(s)
                                    </span>
                                </div>
                            </th>
                            <th class="py-6 px-4 text-center border-r border-slate-500">
                                <div class="flex flex-col space-y-2">
                                    <div
                                        class="flex items-center justify-center space-x-2 text-white text-xs font-bold uppercase tracking-wide">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>Expiry Date</span>
                                    </div>
                                    <div class="flex flex-col space-y-1">
                                        <input type="date" x-model="filters.exp_date_from"
                                            @change="applyFilters()" placeholder="From date"
                                            class="input input-xs bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400 w-full rounded-lg shadow-sm">
                                        <input type="date" x-model="filters.exp_date_to" @change="applyFilters()"
                                            placeholder="To date"
                                            class="input input-xs bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400 w-full rounded-lg shadow-sm">
                                    </div>
                                </div>
                            </th>
                            <th class="py-6 px-4 text-center border-r border-slate-500">
                                <div class="flex flex-col space-y-2">
                                    <div
                                        class="flex items-center justify-center space-x-2 text-white text-xs font-bold uppercase tracking-wide">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                                        </svg>
                                        <span>Lot Number</span>
                                    </div>
                                    <input type="text" x-model="filters.lot_no"
                                        @input="handleTextFilter('lot_no', $event.target.value, 3)"
                                        placeholder="Min 3 chars..."
                                        class="input input-xs bg-white text-gray-800 border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 placeholder-gray-400 w-full rounded-lg shadow-sm">
                                    <span x-show="filters.lot_no.length > 0 && filters.lot_no.length < 3"
                                        class="text-xs text-yellow-300 italic">
                                        Type <span x-text="3 - filters.lot_no.length"></span> more char(s)
                                    </span>
                                </div>
                            </th>
                            <th class="py-6 px-4">
                                <div class="flex flex-col space-y-2">
                                    <div
                                        class="flex items-center justify-center space-x-2 text-white text-xs font-bold uppercase tracking-wide">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                        </svg>
                                        <span>Actions</span>
                                    </div>
                                    <button @click="clearFilters()"
                                        class="btn btn-xs bg-gradient-to-r from-red-500 to-red-600 border-none text-white hover:from-red-600 hover:to-red-700 shadow-md hover:shadow-lg transition-all w-full rounded-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Clear
                                    </button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(stock, index) in stocks" :key="stock.id">
                            <tr class="hover:bg-gradient-to-r hover:from-blue-50 hover:to-transparent transition-all duration-200 border-b border-gray-100"
                                :class="{ 'bg-gray-50/50': index % 2 === 0 }">
                                <td class="py-4 px-4">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                        <span class="font-semibold text-gray-800 text-xs"
                                            x-text="stock.chrgdesc"></span>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="text-gray-600 text-xs font-mono" x-text="stock.updated_at"></span>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="font-bold text-gray-900 text-xs" x-text="stock.drug_concat"></span>
                                </td>
                                <td class="py-4 px-4 text-end">
                                    <div
                                        class="inline-flex items-center space-x-1 px-3 py-1 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border border-green-200">
                                        <span class="text-xs font-bold text-green-700">₱</span>
                                        <span class="font-bold text-green-800 text-xs"
                                            x-text="formatNumber(stock.dmselprice, 2)"></span>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-end">
                                    <div
                                        class="inline-flex items-center px-3 py-1.5 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg shadow-sm">
                                        <span class="font-bold text-white text-xs"
                                            x-text="formatNumber(stock.stock_bal, 0)"></span>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <span class="badge badge-sm shadow-md font-semibold"
                                        :class="getExpiryBadge(stock.exp_date, stock.stock_bal)"
                                        x-text="formatDate(stock.exp_date)"></span>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <span
                                        class="text-gray-700 font-mono text-xs bg-gray-100 px-2 py-1 rounded uppercase"
                                        x-text="stock.lot_no || 'N/A'"></span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex justify-center space-x-1">
                                        @can('update-stock-item')
                                            <button
                                                class="btn btn-xs btn-warning hover:scale-105 shadow-md hover:shadow-lg transition-all"
                                                @click="$wire.openUpdateModal(stock.id, stock.drug_concat, stock.chrgcode, stock.exp_date, stock.stock_bal, stock.dmduprice, stock.has_compounding, stock.compounding_fee || 0, stock.lot_no)">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>
                                        @endcan
                                        @can('adjust-stock-qty')
                                            <button
                                                class="btn btn-xs btn-info hover:scale-105 shadow-md hover:shadow-lg transition-all"
                                                @click="$wire.openAdjustModal(stock.id, stock.drug_concat, stock.chrgdesc, stock.exp_date, stock.stock_bal)">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                </svg>
                                            </button>
                                        @endcan
                                        @can('pull-out-items')
                                            <button
                                                class="btn btn-xs btn-error hover:scale-105 shadow-md hover:shadow-lg transition-all"
                                                @click="$wire.openPulloutModal(stock.id, stock.drug_concat, stock.chrgdesc, stock.exp_date, stock.stock_bal)">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M20 12H4" />
                                                </svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="loading"
                class="flex justify-center items-center p-12 bg-gradient-to-b from-white to-blue-50">
                <div class="flex flex-col items-center space-y-4">
                    <div class="relative">
                        <span class="loading loading-spinner loading-lg text-blue-600"></span>
                        <div class="absolute inset-0 animate-ping opacity-20">
                            <span class="loading loading-spinner loading-lg text-blue-600"></span>
                        </div>
                    </div>
                    <span class="text-sm text-gray-600 font-semibold animate-pulse">Loading more items...</span>
                </div>
            </div>

            <div x-show="!hasMore && stocks.length > 0"
                class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 border-t border-green-200">
                <div class="flex items-center justify-center space-x-3 text-green-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-bold">All items successfully loaded</span>
                </div>
            </div>

            <div x-show="stocks.length === 0 && !loading"
                class="flex flex-col justify-center items-center p-16 bg-gradient-to-b from-white to-gray-50">
                <div class="relative mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-gray-300" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <div class="absolute -top-2 -right-2 bg-red-500 rounded-full p-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <span class="text-2xl font-bold text-gray-400 mb-2">No stocks found</span>
                <span class="text-sm text-gray-400 mb-6">Try adjusting your filter criteria</span>
                <button @click="clearFilters()"
                    class="btn btn-sm btn-primary shadow-lg hover:shadow-xl transition-shadow">
                    Reset All Filters
                </button>
            </div>
        </div>
    </div>

    {{-- Add Item Modal --}}
    <x-mary-modal wire:model="addModal" title="Add Item" class="backdrop-blur">
        <x-mary-form wire:submit="add_item">
            <x-mary-select label="Fund Source" wire:model="chrgcode" :options="$charge_codes" option-value="chrgcode"
                option-label="chrgdesc" icon="o-banknotes" required />

            <x-mary-input label="Lot No" wire:model="lot_no" icon="o-hashtag" />

            <x-mary-choices-offline label="Drug Name" wire:model="dmdcomb" placeholder="Select drugs/medicine"
                placeholder-value="0" :options="$drugs->map(fn($d) => ['id' => $d->dmdcomb . ',' . $d->dmdctr, 'name' => $d->drug_name])" option-value="id" option-label="name" icon="o-beaker" single
                clearable searchable required />

            <x-mary-input label="Expiry Date" wire:model="expiry_date" type="date" icon="o-calendar" required />

            <x-mary-input label="QTY" wire:model="qty" type="number" step="1" icon="o-calculator"
                required />

            <x-mary-input label="Unit Cost" wire:model="unit_cost" type="number" step="0.01"
                icon="o-currency-dollar" required />

            <x-mary-checkbox label="Highly Specialised Drugs" wire:model.live="has_compounding" />

            @if ($has_compounding)
                <x-mary-input label="Compounding Fee" wire:model="compounding_fee" type="number" step="0.01"
                    icon="o-currency-dollar" required />
            @endif

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('addModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="add_item" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Update Item Modal --}}
    <x-mary-modal wire:model="updateModal" title="Update Item: {{ $selectedStockName }}" class="backdrop-blur">
        <x-mary-form wire:submit="update_item">
            <x-mary-select label="Fund Source" wire:model="chrgcode" :options="$charge_codes" option-value="chrgcode"
                option-label="chrgdesc" icon="o-banknotes" required />

            <x-mary-input label="Lot No" wire:model="lot_no" icon="o-hashtag" />

            <x-mary-input label="Expiry Date" wire:model="expiry_date" type="date" icon="o-calendar" required />

            <x-mary-input label="QTY" wire:model="qty" type="number" step="1" icon="o-calculator"
                required />

            <x-mary-input label="Unit Cost" wire:model="unit_cost" type="number" step="0.01"
                icon="o-currency-dollar" required />

            <x-mary-checkbox label="Highly Specialised Drugs" wire:model.live="has_compounding" />

            @if ($has_compounding)
                <x-mary-input label="Compounding Fee" wire:model="compounding_fee" type="number" step="0.01"
                    icon="o-currency-dollar" required />
            @endif

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('updateModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="update_item" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Adjust QTY Modal --}}
    <x-mary-modal wire:model="adjustModal" title="Adjust QTY: {{ $selectedStockName }}" class="backdrop-blur">
        <p class="text-sm text-gray-600 mb-4">{{ $selectedStockExpiry }} - {{ $selectedStockChrgcode }}</p>
        <x-mary-form wire:submit="adjust_qty">
            <x-mary-input label="QTY" wire:model="qty" type="number" step="1" icon="o-calculator"
                required />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('adjustModal', false)" />
                <x-mary-button label="Save" type="submit" class="btn-primary" spinner="adjust_qty" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>

    {{-- Pull-out Modal --}}
    <x-mary-modal wire:model="pulloutModal" title="Pull-out: {{ $selectedStockName }}" class="backdrop-blur">
        <p class="text-sm text-gray-600 mb-4">{{ $selectedStockExpiry }} - {{ $selectedStockChrgcode }}</p>
        <x-mary-form wire:submit="pull_out">
            <x-mary-input label="QTY" wire:model="qty" type="number" step="1" icon="o-calculator"
                required />

            <x-slot:actions>
                <x-mary-button label="Cancel" wire:click="$set('pulloutModal', false)" />
                <x-mary-button label="Pull-out" type="submit" class="btn-error" spinner="pull_out" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-modal>
</div>
