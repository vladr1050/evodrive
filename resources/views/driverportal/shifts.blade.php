@extends('driverportal.layouts.portal')

@section('title', __('portal.shifts'))

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<style>
    .shifts-map { width: 100%; height: 200px; border-radius: 1rem; z-index: 0; background: #e2e8f0; }
    @media (min-width: 1280px) {
        .shifts-map { height: min(420px, calc(100vh - 14rem)); min-height: 280px; }
    }
    .shifts-map-pin-wrap { background: transparent; border: 0; }
    .shifts-map-pin {
        width: 18px; height: 18px; border-radius: 9999px;
        background: #2563eb;
        border: 3px solid #fff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.45), 0 2px 8px rgba(37, 99, 235, 0.55);
    }
    .shifts-map-pin.is-selected {
        width: 24px; height: 24px;
        background: #1d4ed8;
        border-width: 3px;
        box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.4), 0 0 16px rgba(37, 99, 235, 0.75);
    }
    .marker-cluster-small,
    .marker-cluster-medium,
    .marker-cluster-large {
        background-color: rgba(37, 99, 235, 0.35) !important;
    }
    .marker-cluster-small div,
    .marker-cluster-medium div,
    .marker-cluster-large div {
        background-color: #2563eb !important;
        color: #fff !important;
        font-weight: 700 !important;
    }
    .leaflet-container { font: inherit; }
</style>
<script>
    window.__SHIFTS_AVAILABLE_SLOTS__ = @json($availableSlots ?? []);
    window.__SHIFTS_PAGE_INIT__ = @json($shiftsPageInit ?? []);
    document.addEventListener('alpine:init', function() {
        Alpine.data('shiftsPage', function() {
            const init = window.__SHIFTS_PAGE_INIT__ || {};
            const LAST_KEY = init.lastStationStorageKey || 'evodrive.driver.lastStationId';
            return {
                filterStationId: init.initialFilterStationId || null,
                filterStation: init.initialFilterStation || 'All',
                isStationDropdownOpen: false,
                stationSearch: '',
                shiftsMode: (init.initialFilterStationId || (init.favoriteStationIds || []).length) ? 'free' : 'mine',
                availableSlots: window.__SHIFTS_AVAILABLE_SLOTS__ || [],
                stations: init.stations || [],
                favoriteStationIds: init.favoriteStationIds || [],
                recentStationIds: init.recentStationIds || [],
                shiftsBaseUrl: init.shiftsBaseUrl || '',
                currentView: init.currentView || '0',
                weekOptions: init.weekOptions || [],
                weekDates: init.weekDates || [],
                selectedDayIso: null,
                toggleFavoriteUrl: init.toggleFavoriteUrl || '',
                requireStationForFree: init.requireStationForFree !== false,
                hasFavoriteStations: !!(init.hasFavoriteStations || (init.favoriteStationIds || []).length),
                lastStationStorageKey: LAST_KEY,
                map: null,
                markerLayer: null,
                markersById: {},
                mapReady: false,
                labels: {
                    allStations: @json(__('portal.all_stations')),
                    favorites: @json(__('portal.favorite_stations')),
                    recent: @json(__('portal.recent_stations')),
                    other: @json(__('portal.other_stations')),
                    carOne: @json(__('portal.car_available')),
                    carsMany: @json(__('portal.cars_available')),
                    carsShort: @json(__('portal.cars_short')),
                    selectPrompt: @json(__('portal.select_station_prompt')),
                    selectHint: @json(__('portal.select_station_hint')),
                    mapTitle: @json(__('portal.map_stations')),
                    mapNoCoords: @json(__('portal.map_no_coordinates')),
                },
                boot() {
                    const today = init.todayIso;
                    const days = this.weekDates;
                    this.selectedDayIso = (days.find(d => d.iso === today) || days[0] || {}).iso || null;

                    if (this.filterStationId) {
                        try { localStorage.setItem(this.lastStationStorageKey, String(this.filterStationId)); } catch (e) {}
                    } else if (!this.hasFavoriteStations) {
                        // Only restore last single-station filter when there are no favorites yet.
                        try {
                            const last = localStorage.getItem(this.lastStationStorageKey);
                            if (last && this.stations.some(s => String(s.id) === String(last))) {
                                window.location.replace(this.weekUrl(this.currentView, parseInt(last, 10)));
                                return;
                            }
                        } catch (e) {}
                    }

                    this.$nextTick(() => this.initMap());
                },
                weekUrl(view, stationId) {
                    const params = new URLSearchParams();
                    params.set('view', view);
                    const sid = stationId === undefined ? this.filterStationId : stationId;
                    if (sid) params.set('station_id', sid);
                    return this.shiftsBaseUrl + '?' + params.toString();
                },
                selectedStationLabel() {
                    if (!this.filterStationId) return this.labels.favorites;
                    const st = this.stations.find(s => Number(s.id) === Number(this.filterStationId));
                    return st ? (st.short || st.name) : this.filterStation;
                },
                stationsWithCoords() {
                    return this.stations.filter(s =>
                        s.is_active !== false
                        && s.latitude != null
                        && s.longitude != null
                    );
                },
                filteredStations() {
                    const q = (this.stationSearch || '').trim().toLowerCase();
                    const active = this.stations.filter(s => s.is_active !== false);
                    if (!q) return active;
                    return active.filter(s => {
                        const hay = [s.name, s.short, s.address, s.provider].filter(Boolean).join(' ').toLowerCase();
                        return hay.includes(q);
                    });
                },
                favoriteStations() {
                    const ids = this.favoriteStationIds.map(Number);
                    return this.filteredStations().filter(s => ids.includes(Number(s.id)));
                },
                recentStations() {
                    const fav = new Set(this.favoriteStationIds.map(Number));
                    return this.recentStationIds
                        .map(id => this.filteredStations().find(s => Number(s.id) === Number(id)))
                        .filter(s => s && !fav.has(Number(s.id)));
                },
                groupedStations() {
                    const skip = new Set([
                        ...this.favoriteStationIds.map(Number),
                        ...this.recentStationIds.map(Number),
                    ]);
                    const groups = {};
                    this.filteredStations().forEach(s => {
                        if (!this.stationSearch && skip.has(Number(s.id))) return;
                        const key = s.provider || this.labels.other;
                        if (!groups[key]) groups[key] = [];
                        groups[key].push(s);
                    });
                    return Object.keys(groups).sort().map(name => ({ name, stations: groups[name] }));
                },
                applyStationFilter(stationId) {
                    this.isStationDropdownOpen = false;
                    this.stationSearch = '';
                    if (stationId) {
                        try { localStorage.setItem(this.lastStationStorageKey, String(stationId)); } catch (e) {}
                    } else {
                        try { localStorage.removeItem(this.lastStationStorageKey); } catch (e) {}
                    }
                    window.location = this.weekUrl(this.currentView, stationId || null);
                },
                clearStationFilter() {
                    this.applyStationFilter(null);
                },
                async toggleFavorite(stationId, event) {
                    if (event) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    if (!this.toggleFavoriteUrl) return;
                    try {
                        const res = await fetch(this.toggleFavoriteUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({ station_id: stationId }),
                        });
                        const data = await res.json();
                        if (data.ok) {
                            this.favoriteStationIds = data.favorite_station_ids || [];
                            this.hasFavoriteStations = this.favoriteStationIds.length > 0;
                            this.stations = this.stations.map(s => ({
                                ...s,
                                is_favorite: this.favoriteStationIds.map(Number).includes(Number(s.id)),
                            }));
                            // Favorites scope is server-rendered; reload when not pinned to one station.
                            if (!this.filterStationId) {
                                window.location = this.weekUrl(this.currentView, null);
                            }
                        }
                    } catch (e) {}
                },
                carsLabel(count) {
                    const n = count || 0;
                    if (n === 1) return this.labels.carOne;
                    return this.labels.carsMany.replace(':count', String(n));
                },
                carsShort(count) {
                    const n = count || 0;
                    return this.labels.carsShort.replace(':count', String(n));
                },
                slotsForDay(dayName) {
                    return this.availableSlots.filter(s => s.day === dayName);
                },
                needsStationForFree() {
                    return this.shiftsMode === 'free' && this.requireStationForFree && !this.filterStationId;
                },
                pinIcon(selected) {
                    return L.divIcon({
                        className: 'shifts-map-pin-wrap',
                        html: '<div class="shifts-map-pin' + (selected ? ' is-selected' : '') + '"></div>',
                        iconSize: selected ? [24, 24] : [18, 18],
                        iconAnchor: selected ? [12, 12] : [9, 9],
                    });
                },
                initMap() {
                    const el = document.getElementById('shifts-stations-map');
                    if (!el || typeof L === 'undefined') return;

                    const withCoords = this.stationsWithCoords();
                    if (!withCoords.length) {
                        el.innerHTML = '<div class="h-full flex items-center justify-center p-4 text-center text-xs font-medium text-slate-500">' + this.labels.mapNoCoords + '</div>';
                        return;
                    }

                    if (this.map) {
                        this.map.remove();
                        this.map = null;
                        this.markersById = {};
                    }

                    this.map = L.map(el, {
                        scrollWheelZoom: false,
                        zoomControl: true,
                    });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                        maxZoom: 19,
                    }).addTo(this.map);

                    const useCluster = typeof L.markerClusterGroup === 'function';
                    this.markerLayer = useCluster
                        ? L.markerClusterGroup({ maxClusterRadius: 42, showCoverageOnHover: false })
                        : L.layerGroup();

                    withCoords.forEach(s => {
                        const selected = Number(this.filterStationId) === Number(s.id);
                        const marker = L.marker([s.latitude, s.longitude], {
                            icon: this.pinIcon(selected),
                            title: s.short || s.name,
                            keyboard: true,
                        });
                        marker.bindTooltip(s.short || s.name, { direction: 'top', offset: [0, -12] });
                        marker.on('click', () => {
                            if (Number(this.filterStationId) === Number(s.id)) return;
                            this.applyStationFilter(s.id);
                        });
                        this.markersById[s.id] = marker;
                        this.markerLayer.addLayer(marker);
                    });

                    this.map.addLayer(this.markerLayer);

                    const selected = withCoords.find(s => Number(s.id) === Number(this.filterStationId));
                    if (selected) {
                        this.map.setView([selected.latitude, selected.longitude], 14);
                    } else if (this.markerLayer.getBounds && this.markerLayer.getBounds().isValid()) {
                        this.map.fitBounds(this.markerLayer.getBounds().pad(0.18));
                    } else {
                        this.map.setView([56.9496, 24.1052], 11);
                    }

                    this.mapReady = true;
                    setTimeout(() => this.map && this.map.invalidateSize(), 150);
                    setTimeout(() => this.map && this.map.invalidateSize(), 400);
                },
            };
        });
    });
</script>
<div x-data="shiftsPage()" x-init="boot()" class="animate-fade-in">
    <!-- Header Section (UI 1:1 reference) -->
    <div class="mb-8 flex flex-col xl:flex-row xl:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">{{ __('portal.shifts') }}</h1>
            <p class="text-slate-500 mt-1 font-medium">{{ __('portal.shifts_subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            @php
                $shiftsBaseUrl = route('driverportal.shifts', ['locale' => request()->route('locale', app()->getLocale())]);
            @endphp
            <div class="flex flex-wrap items-center gap-2 bg-white p-1.5 rounded-2xl border border-slate-200 shadow-sm">
                @foreach($weekOptions ?? [] as $opt)
                <a href="{{ $shiftsBaseUrl }}?view={{ $opt['index'] }}{{ request('station_id') ? '&station_id=' . request('station_id') : '' }}" class="px-4 py-2 rounded-xl text-sm font-bold transition-all {{ (string)$view === (string)$opt['index'] ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-900' }}">{{ $opt['label'] }}</a>
                @endforeach
            </div>

            <!-- Station picker: search + favorites + provider groups (desktop panel / mobile sheet) -->
            <div class="relative">
                <button
                    type="button"
                    @click="isStationDropdownOpen = !isStationDropdownOpen"
                    class="flex items-center gap-3 bg-white px-5 py-3 rounded-2xl border border-slate-200 shadow-sm hover:border-brand-300 transition-all min-w-[200px] max-w-[280px] justify-between group"
                >
                    <div class="flex items-center gap-2 min-w-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0" :class="filterStationId ? 'text-brand-600' : 'text-slate-400'"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span class="text-sm font-bold text-slate-700 truncate" x-text="selectedStationLabel()"></span>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400 shrink-0 transition-transform duration-300" :class="isStationDropdownOpen ? 'rotate-45' : 'rotate-0'"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                </button>

                {{-- Desktop dropdown --}}
                <div
                    x-show="isStationDropdownOpen"
                    x-cloak
                    @click.away="isStationDropdownOpen = false"
                    class="hidden md:block absolute top-full left-0 mt-2 w-[340px] max-h-[420px] bg-white border border-slate-100 rounded-2xl shadow-xl z-30 overflow-hidden"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform -translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0"
                >
                    <div class="p-3 border-b border-slate-100 sticky top-0 bg-white z-10">
                        <input type="search" x-model="stationSearch" placeholder="{{ __('portal.search_stations') }}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400" @click.stop>
                    </div>
                    <div class="overflow-y-auto max-h-[360px]">
                        <button type="button" @click="clearStationFilter()" class="w-full px-4 py-2.5 text-left text-sm font-bold transition-colors flex items-center justify-between" :class="!filterStationId ? 'bg-brand-50 text-brand-600' : 'text-slate-600 hover:bg-slate-50'">
                            <span>{{ __('portal.favorite_stations') }}</span>
                        </button>
                        <template x-if="favoriteStations().length">
                            <div>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="labels.favorites"></div>
                                <template x-for="s in favoriteStations()" :key="'fav-'+s.id">
                                    <div class="flex items-stretch">
                                        <button type="button" @click="applyStationFilter(s.id)" class="flex-1 px-4 py-2.5 text-left text-sm font-bold transition-colors min-w-0" :class="filterStationId === s.id ? 'bg-brand-50 text-brand-600' : 'text-slate-600 hover:bg-slate-50'">
                                            <span class="block truncate" x-text="s.short || s.name"></span>
                                            <span class="block text-xs font-normal text-slate-400 truncate" x-text="s.address || s.name"></span>
                                        </button>
                                        <button type="button" class="px-3 text-amber-500 hover:bg-amber-50" @click="toggleFavorite(s.id, $event)" title="{{ __('portal.favorite_stations') }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-if="recentStations().length && !stationSearch">
                            <div>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="labels.recent"></div>
                                <template x-for="s in recentStations()" :key="'rec-'+s.id">
                                    <div class="flex items-stretch">
                                        <button type="button" @click="applyStationFilter(s.id)" class="flex-1 px-4 py-2.5 text-left text-sm font-bold transition-colors min-w-0" :class="filterStationId === s.id ? 'bg-brand-50 text-brand-600' : 'text-slate-600 hover:bg-slate-50'">
                                            <span class="block truncate" x-text="s.short || s.name"></span>
                                            <span class="block text-xs font-normal text-slate-400 truncate" x-text="s.address || s.name"></span>
                                        </button>
                                        <button type="button" class="px-3 text-slate-300 hover:text-amber-500 hover:bg-amber-50" @click="toggleFavorite(s.id, $event)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <template x-for="group in groupedStations()" :key="group.name">
                            <div>
                                <div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="group.name"></div>
                                <template x-for="s in group.stations" :key="'g-'+s.id">
                                    <div class="flex items-stretch">
                                        <button type="button" @click="applyStationFilter(s.id)" class="flex-1 px-4 py-2.5 text-left text-sm font-bold transition-colors min-w-0" :class="filterStationId === s.id ? 'bg-brand-50 text-brand-600' : 'text-slate-600 hover:bg-slate-50'">
                                            <span class="block truncate" x-text="s.short || s.name"></span>
                                            <span class="block text-xs font-normal text-slate-400 truncate" x-text="s.address || s.name"></span>
                                        </button>
                                        <button type="button" class="px-3 hover:bg-amber-50" :class="favoriteStationIds.includes(s.id) ? 'text-amber-500' : 'text-slate-300 hover:text-amber-500'" @click="toggleFavorite(s.id, $event)">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" :fill="favoriteStationIds.includes(s.id) ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Mobile bottom sheet --}}
                <div
                    x-show="isStationDropdownOpen"
                    x-cloak
                    class="md:hidden fixed inset-0 z-40"
                    x-transition.opacity
                >
                    <div class="absolute inset-0 bg-slate-900/40" @click="isStationDropdownOpen = false"></div>
                    <div class="absolute bottom-0 left-0 right-0 max-h-[85vh] bg-white rounded-t-3xl shadow-2xl flex flex-col"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="translate-y-full"
                         x-transition:enter-end="translate-y-0">
                        <div class="flex items-center justify-between px-5 pt-4 pb-2">
                            <span class="text-base font-bold text-slate-900">{{ __('portal.favorite_stations') }}</span>
                            <button type="button" @click="isStationDropdownOpen = false" class="p-2 text-slate-400 hover:text-slate-700">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
                            </button>
                        </div>
                        <div class="px-5 pb-3">
                            <input type="search" x-model="stationSearch" placeholder="{{ __('portal.search_stations') }}" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400">
                        </div>
                        <div class="overflow-y-auto px-2 pb-8">
                            <button type="button" @click="clearStationFilter()" class="w-full px-4 py-3 text-left text-sm font-bold rounded-xl" :class="!filterStationId ? 'bg-brand-50 text-brand-600' : 'text-slate-600'">{{ __('portal.favorite_stations') }}</button>
                            <template x-if="favoriteStations().length">
                                <div>
                                    <div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="labels.favorites"></div>
                                    <template x-for="s in favoriteStations()" :key="'mfav-'+s.id">
                                        <div class="flex items-stretch">
                                            <button type="button" @click="applyStationFilter(s.id)" class="flex-1 px-4 py-3 text-left text-sm font-bold min-w-0" :class="filterStationId === s.id ? 'text-brand-600' : 'text-slate-700'">
                                                <span class="block truncate" x-text="s.short || s.name"></span>
                                                <span class="block text-xs font-normal text-slate-400 truncate" x-text="s.address || s.name"></span>
                                            </button>
                                            <button type="button" class="px-3 text-amber-500" @click="toggleFavorite(s.id, $event)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="recentStations().length && !stationSearch">
                                <div>
                                    <div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="labels.recent"></div>
                                    <template x-for="s in recentStations()" :key="'mrec-'+s.id">
                                        <div class="flex items-stretch">
                                            <button type="button" @click="applyStationFilter(s.id)" class="flex-1 px-4 py-3 text-left text-sm font-bold min-w-0">
                                                <span class="block truncate" x-text="s.short || s.name"></span>
                                                <span class="block text-xs font-normal text-slate-400 truncate" x-text="s.address || s.name"></span>
                                            </button>
                                            <button type="button" class="px-3 text-slate-300" @click="toggleFavorite(s.id, $event)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-for="group in groupedStations()" :key="'mg-'+group.name">
                                <div>
                                    <div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400" x-text="group.name"></div>
                                    <template x-for="s in group.stations" :key="'mg-'+s.id">
                                        <div class="flex items-stretch">
                                            <button type="button" @click="applyStationFilter(s.id)" class="flex-1 px-4 py-3 text-left text-sm font-bold min-w-0" :class="filterStationId === s.id ? 'text-brand-600' : 'text-slate-700'">
                                                <span class="block truncate" x-text="s.short || s.name"></span>
                                                <span class="block text-xs font-normal text-slate-400 truncate" x-text="s.address || s.name"></span>
                                            </button>
                                            <button type="button" class="px-3" :class="favoriteStationIds.includes(s.id) ? 'text-amber-500' : 'text-slate-300'" @click="toggleFavorite(s.id, $event)">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" :fill="favoriteStationIds.includes(s.id) ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Copy Previous Week (reference: plus icon rotate-45) -->
            <button type="button" id="copy-prev-week" class="flex items-center gap-2 px-5 py-3 bg-slate-100 text-slate-700 rounded-2xl font-bold hover:bg-slate-200 transition-all text-sm border border-slate-200">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="rotate-45"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                {{ __('portal.copy_prev_week') }}
            </button>

            <button type="button" data-testid="shift-create-btn" onclick="document.getElementById('create-modal').classList.remove('hidden'); window.updateStartTimeOptions&&window.updateStartTimeOptions();" class="bg-brand-600 text-white px-6 py-3 rounded-2xl font-bold shadow-lg shadow-brand-600/20 hover:bg-brand-700 active:scale-95 transition-all flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                {{ __('portal.create_shift') }}
            </button>
        </div>
    </div>

    @if(session('driverportal.success'))
        <div class="mb-4 p-4 bg-green-50 text-green-700 rounded-2xl text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('driverportal.success') }}
        </div>
    @endif

    <!-- Mode toggle: All shifts / My shifts / Free slots -->
    <div class="mb-6 flex flex-wrap items-center gap-2 sm:gap-4 bg-white p-2 rounded-3xl border border-slate-100 shadow-sm w-fit max-w-full">
        <button type="button" @click="shiftsMode = 'all'" :class="shiftsMode === 'all' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-900'" class="px-4 sm:px-6 py-2.5 rounded-2xl text-sm font-bold transition-all flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            {{ __('portal.all_shifts') }}
        </button>
        <button type="button" @click="shiftsMode = 'mine'" :class="shiftsMode === 'mine' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-900'" class="px-4 sm:px-6 py-2.5 rounded-2xl text-sm font-bold transition-all flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            {{ __('portal.my_shifts_tab') }}
        </button>
        <button type="button" @click="shiftsMode = 'free'" :class="shiftsMode === 'free' ? 'bg-brand-600 text-white shadow-md' : 'text-slate-500 hover:text-slate-900'" class="px-4 sm:px-6 py-2.5 rounded-2xl text-sm font-bold transition-all flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            {{ __('portal.show_free_slots') }}
        </button>
    </div>

    <div x-show="needsStationForFree()" x-cloak class="mb-6 p-4 rounded-2xl border border-dashed border-brand-300 bg-brand-50/50 text-sm text-brand-800 font-medium">
        <p x-text="labels.selectPrompt"></p>
        <p class="mt-1 text-xs font-normal text-brand-600/80" x-text="labels.selectHint"></p>
    </div>

    <!-- Split: map filter + week schedule -->
    <div class="flex flex-col xl:grid xl:grid-cols-12 gap-4 xl:gap-5 items-start">
        <aside class="w-full xl:col-span-4 xl:sticky xl:top-4 space-y-3">
            <div class="bg-white border border-slate-100 rounded-3xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
                    <span class="text-sm font-bold text-slate-800" x-text="labels.mapTitle"></span>
                    <button
                        type="button"
                        x-show="filterStationId"
                        x-cloak
                        @click="clearStationFilter()"
                        class="text-xs font-bold text-slate-500 hover:text-brand-600"
                    >{{ __('portal.clear_station_filter') }}</button>
                </div>
                <div id="shifts-stations-map" class="shifts-map" role="application" aria-label="{{ __('portal.map_stations') }}"></div>
            </div>
            <p class="text-[11px] text-slate-400 font-medium px-1" x-show="stationsWithCoords().length" x-cloak>
                <span x-text="stationsWithCoords().length"></span> / <span x-text="stations.length"></span>
            </p>
        </aside>

        <div class="w-full xl:col-span-8 min-w-0">
            {{-- Mobile / tablet: single-day chips --}}
            <div class="flex xl:hidden gap-2 overflow-x-auto pb-3 -mx-1 px-1">
                <template x-for="d in weekDates" :key="d.iso">
                    <button
                        type="button"
                        @click="selectedDayIso = d.iso"
                        class="shrink-0 px-3 py-2 rounded-xl text-xs font-bold border transition-all"
                        :class="selectedDayIso === d.iso ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200'"
                    >
                        <span class="block uppercase tracking-wide opacity-80" x-text="d.name"></span>
                        <span x-text="d.date + ' ' + d.month"></span>
                    </button>
                </template>
            </div>

            <!-- Shifts Grid -->
            <div class="overflow-x-auto pb-4 xl:overflow-visible">
                <div class="grid grid-cols-1 xl:grid-cols-7 gap-2 min-w-0">
                    @foreach($weekDates as $dayInfo)
                        @php
                            $sortShifts = fn ($c) => $c->sortBy(fn ($s) => (int) str_replace(':', '', $s['start']))->all();
                            $dayShiftsAll = $sortShifts(collect($shiftsAll)->where('date_iso', $dayInfo['iso']));
                            $dayShiftsMine = $sortShifts(collect($shiftsMine)->where('date_iso', $dayInfo['iso']));
                        @endphp
                        <div
                            class="space-y-2 min-w-0"
                            :class="{ 'hidden xl:block': selectedDayIso !== '{{ $dayInfo['iso'] }}' }"
                        >
                            <div class="flex flex-col items-center py-2 bg-slate-100 rounded-xl border border-slate-200 relative group">
                                <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-0.5">{{ $dayInfo['name'] }}</span>
                                <span class="text-xs font-bold text-slate-700">{{ $dayInfo['date'] }} {{ $dayInfo['month'] }}</span>
                                <button type="button" onclick="openCreateModalForDate('{{ $dayInfo['iso'] }}')" class="absolute -top-2 -right-2 w-6 h-6 bg-white border border-slate-200 rounded-full flex items-center justify-center text-slate-400 hover:text-brand-600 hover:border-brand-600 shadow-sm transition-all opacity-0 group-hover:opacity-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                                </button>
                            </div>
                            <div class="space-y-1.5 min-h-[120px]">
                                <div x-show="shiftsMode === 'all'" class="space-y-1.5" x-cloak>
                                    @foreach($dayShiftsAll as $shift)
                                        @include('driverportal.components.shift-block', ['shift' => $shift, 'hideStation' => (bool) $selectedStationId])
                                    @endforeach
                                </div>
                                <div x-show="shiftsMode === 'mine'" class="space-y-1.5" x-cloak>
                                    @foreach($dayShiftsMine as $shift)
                                        @include('driverportal.components.shift-block', ['shift' => $shift, 'hideStation' => false])
                                    @endforeach
                                </div>
                                <div x-show="shiftsMode === 'free' && !needsStationForFree()" class="space-y-1.5" x-cloak>
                                    <template x-for="slot in slotsForDay('{{ $dayInfo['name'] }}')" :key="slot.id">
                                        <button
                                            type="button"
                                            class="w-full text-left px-2 py-1.5 rounded-lg border border-dashed border-brand-300 bg-brand-50/40 hover:bg-brand-50 hover:border-brand-500 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                                            @click="window.openCreateModalFromSlot && window.openCreateModalFromSlot(slot)"
                                            :title="(slot.station_short || slot.station || '') + ' · ' + carsLabel(slot.cars_count || (slot.vehicles ? slot.vehicles.length : 0))"
                                            :aria-label="slot.start + '–' + slot.end + ', ' + (slot.duration || 0) + 'h, ' + carsLabel(slot.cars_count || (slot.vehicles ? slot.vehicles.length : 0))"
                                        >
                                            {{-- From – To on one line; no truncate so both times stay visible --}}
                                            <div class="text-[11px] font-bold text-brand-700 tabular-nums leading-tight whitespace-nowrap">
                                                <span x-text="slot.start"></span><span class="text-brand-400 font-semibold">–</span><span x-text="slot.end"></span><span class="text-brand-400" x-show="slot.end_date_iso">+1</span>
                                            </div>
                                            <div class="mt-1 text-[10px] font-bold text-brand-500 tabular-nums" x-text="(slot.duration || 0) + 'h'"></div>
                                            <div class="mt-1 space-y-0.5">
                                                <template x-for="(v, vi) in (slot.vehicles || [])" :key="slot.id + '-v-' + vi">
                                                    <div class="text-[10px] font-semibold text-slate-700 tabular-nums leading-tight whitespace-nowrap" x-text="v.number || v.model"></div>
                                                </template>
                                            </div>
                                            <div
                                                x-show="!filterStationId"
                                                x-cloak
                                                class="mt-1 text-[9px] font-medium text-brand-600/80 whitespace-nowrap truncate"
                                                x-text="slot.station_short || slot.station"
                                            ></div>
                                        </button>
                                    </template>
                                </div>
                                <div x-show="!needsStationForFree() && ((shiftsMode === 'all' && {{ empty($dayShiftsAll) ? 'true' : 'false' }}) || (shiftsMode === 'mine' && {{ empty($dayShiftsMine) ? 'true' : 'false' }}) || (shiftsMode === 'free' && slotsForDay('{{ $dayInfo['name'] }}').length === 0))"
                                     x-transition
                                     class="h-full flex flex-col items-center justify-center py-8 text-center">
                                    <div class="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center text-slate-300 mb-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </div>
                                    <span class="text-[10px] font-medium text-slate-300 italic" x-text="shiftsMode === 'free' ? '{{ __("portal.no_slots_found") }}' : '{{ __("portal.no_shifts_planned") }}'"></span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Legend & Info (reference UI 1:1) -->
    <div class="mt-12 p-6 bg-white border border-slate-100 rounded-3xl flex flex-wrap items-center justify-between gap-8 shadow-sm">
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full bg-slate-300"></div>
                <span class="text-sm font-medium text-slate-600">{{ __('portal.reserved') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                <span class="text-sm font-medium text-slate-600">{{ __('portal.my_shift') }}</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded border-2 border-dashed border-brand-400 bg-brand-50"></div>
                <span class="text-sm font-medium text-slate-600">{{ __('portal.available') }}</span>
            </div>
        </div>
        <div class="flex items-center gap-2 text-slate-400 bg-slate-50 px-4 py-2 rounded-xl">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/></svg>
            <span class="text-xs font-medium">{{ __('portal.shifts_legend_info') }}</span>
        </div>
    </div>

    <!-- Create Shift Modal (reference UI 1:1) -->
    <div id="create-modal" data-testid="shift-create-modal" data-min-date="{{ $minDate }}" data-max-date="{{ $maxDate }}" data-min-time-today="{{ $minTimeToday ?? '' }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" role="dialog" aria-modal="true" onclick="if(event.target===this)this.classList.add('hidden')">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl relative">
            <button type="button" data-testid="shift-create-modal-close" onclick="document.getElementById('create-modal').classList.add('hidden')" class="absolute top-6 right-6 p-2 text-slate-400 hover:text-slate-900 transition-colors" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
            </button>
            <h3 class="text-2xl font-bold text-slate-900 mb-6">{{ __('portal.create_shift') }}</h3>
            <div class="space-y-6">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="create-date" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">{{ __('portal.date') }}</label>
                        <input id="create-date" data-testid="shift-create-date" type="date" min="{{ $minDate }}" max="{{ $maxDate }}" value="{{ $minDate }}" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-brand-600/20 focus:border-brand-600 transition-all font-bold text-slate-700">
                    </div>
                    <div>
                        <label for="create-station" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">{{ __('portal.station') }}</label>
                        <select id="create-station" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-brand-600/20 focus:border-brand-600 transition-all font-bold text-slate-700">
                            @foreach($stations as $s)
                                <option value="{{ $s->id }}">{{ $s->name }}{{ !empty($s->address) ? ' — ' . $s->address : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="create-start" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">{{ __('portal.start_time') }}</label>
                        <select id="create-start" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-brand-600/20 focus:border-brand-600 transition-all font-bold text-slate-700">
                            @for($h = 0; $h < 24; $h++) @for($m = 0; $m < 60; $m += $timeSlotMinutes)
                                <option value="{{ sprintf('%02d:%02d', $h, $m) }}" {{ sprintf('%02d:%02d', $h, $m) === '08:00' ? 'selected' : '' }}>{{ sprintf('%02d:%02d', $h, $m) }}</option>
                            @endfor @endfor
                        </select>
                    </div>
                    <div>
                        <label for="create-duration" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">{{ __('portal.duration') }}</label>
                        <select id="create-duration" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-brand-600/20 focus:border-brand-600 transition-all font-bold text-slate-700">
                            @foreach($allowedDurations as $d)<option value="{{ $d }}">{{ $d }}h</option>@endforeach
                        </select>
                    </div>
                </div>
                <div id="availability-message" class="hidden p-4 rounded-xl text-sm font-medium"></div>
                <div class="flex gap-3">
                    <button type="button" id="check-availability-btn" data-testid="shift-check-availability" class="flex-1 bg-slate-100 text-slate-700 font-bold py-4 rounded-xl hover:bg-slate-200 transition-all">
                        {{ __('portal.check_availability') }}
                    </button>
                    <button type="button" id="confirm-shift-btn" data-testid="shift-confirm" class="flex-1 bg-brand-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-brand-600/20 hover:bg-brand-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        {{ __('portal.confirm_shift') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Copy previous week modal --}}
    @php
        $copyReasonCodes = ['NO_VEHICLES', 'DOWNTIME', 'DAILY_LIMIT', 'VEHICLE_24H', 'INVALID_DURATION', 'INVALID_START', 'STATION_MISMATCH', 'OUTSIDE_PLANNING_WINDOW'];
        $copyReasonLabels = [];
        foreach ($copyReasonCodes as $code) {
            $copyReasonLabels[$code] = __('portal.copy_reason_' . $code);
        }
    @endphp
    <div id="copy-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="copy-modal-title">
        <div class="bg-white rounded-3xl p-8 max-w-lg w-full max-h-[90vh] overflow-hidden flex flex-col shadow-2xl relative">
            <button type="button" id="copy-modal-close" class="absolute top-6 right-6 p-2 text-slate-400 hover:text-slate-900 transition-colors" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <h2 id="copy-modal-title" class="text-2xl font-bold text-slate-900 mb-4 pr-10">{{ __('portal.copy_modal_title') }}</h2>
            <div id="copy-modal-loading" class="py-12 text-center text-slate-500 font-medium">{{ __('portal.copy_loading') }}</div>
            <div id="copy-modal-empty" class="hidden py-8 text-center text-slate-500">{{ __('portal.copy_empty_previous_week') }}</div>
            <div id="copy-modal-content" class="hidden flex-1 overflow-y-auto space-y-6">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-2">{{ __('portal.copy_modal_will_be_copied') }}</h3>
                    <ul id="copy-proposed-list" class="space-y-2"></ul>
                </div>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-2">{{ __('portal.copy_modal_conflicts') }}</h3>
                    <ul id="copy-conflicts-list" class="space-y-2"></ul>
                </div>
                <div id="copy-confirm-error" class="hidden p-4 rounded-2xl bg-red-50 text-red-600 text-sm font-medium"></div>
            </div>
            <div id="copy-modal-footer" class="hidden mt-6 flex gap-3 justify-end pt-4 border-t border-slate-100">
                <button type="button" id="copy-modal-cancel" class="px-5 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-2xl hover:bg-slate-200 transition-all">{{ __('portal.copy_cancel_btn') }}</button>
                <button type="button" id="copy-modal-confirm" class="px-5 py-2.5 bg-brand-600 text-white font-bold rounded-2xl shadow-lg shadow-brand-600/20 hover:bg-brand-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed">{{ __('portal.copy_confirm_btn') }}</button>
            </div>
        </div>
    </div>

    {{-- Cancel shift modal --}}
    <div id="cancel-shift-modal" data-testid="shift-cancel-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="cancel-shift-modal-title">
        <div class="bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl relative">
            <h2 id="cancel-shift-modal-title" class="text-xl font-bold text-slate-900 mb-6">{{ __('portal.cancel_shift_confirm_title') }}</h2>
            <div id="cancel-shift-error" class="hidden mb-4 p-3 rounded-2xl bg-red-50 text-red-600 text-sm"></div>
            <div class="flex gap-3 justify-end">
                <button type="button" id="cancel-shift-modal-keep" class="px-5 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-2xl hover:bg-slate-200 transition-all">{{ __('portal.cancel_shift_cancel_btn') }}</button>
                <button type="button" id="cancel-shift-modal-confirm" data-testid="shift-cancel-confirm" class="px-5 py-2.5 bg-red-600 text-white font-bold rounded-2xl hover:bg-red-700 transition-all disabled:opacity-50">{{ __('portal.cancel_shift_confirm_btn') }}</button>
            </div>
        </div>
    </div>

    {{-- Edit shift modal --}}
    <div id="edit-shift-modal" data-testid="shift-edit-modal" data-min-date="{{ $minDate }}" data-max-date="{{ $maxDate }}" data-min-time-today="{{ $minTimeToday ?? '' }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" role="dialog" aria-modal="true" onclick="if(event.target===this)document.getElementById('edit-shift-modal').classList.add('hidden')">
        <div class="bg-white rounded-3xl p-8 max-w-md w-full shadow-2xl relative" onclick="event.stopPropagation()">
            <button type="button" data-testid="shift-edit-modal-close" onclick="document.getElementById('edit-shift-modal').classList.add('hidden')" class="absolute top-6 right-6 p-2 text-slate-400 hover:text-slate-900 transition-colors" aria-label="Close">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/></svg>
            </button>
            <h3 id="edit-shift-modal-title" class="text-2xl font-bold text-slate-900 mb-6">{{ __('portal.edit_shift') }}</h3>
            <div class="space-y-6">
                <div>
                    <label for="edit-shift-date" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">{{ __('portal.date') }}</label>
                    <input id="edit-shift-date" data-testid="shift-edit-date" type="date" min="{{ $minDate }}" max="{{ $maxDate }}" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-brand-600/20 focus:border-brand-600 transition-all font-bold text-slate-700">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit-shift-start" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">{{ __('portal.start_time') }}</label>
                        <select id="edit-shift-start" data-testid="shift-edit-start" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-brand-600/20 focus:border-brand-600 transition-all font-bold text-slate-700">
                            @for($h = 0; $h < 24; $h++) @for($m = 0; $m < 60; $m += $timeSlotMinutes)
                                <option value="{{ sprintf('%02d:%02d', $h, $m) }}">{{ sprintf('%02d:%02d', $h, $m) }}</option>
                            @endfor @endfor
                        </select>
                    </div>
                    <div>
                        <label for="edit-shift-duration" class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">{{ __('portal.duration') }}</label>
                        <select id="edit-shift-duration" data-testid="shift-edit-duration" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-brand-600/20 focus:border-brand-600 transition-all font-bold text-slate-700">
                            @foreach($allowedDurations as $d)<option value="{{ $d }}">{{ $d }}h</option>@endforeach
                        </select>
                    </div>
                </div>
                <div id="edit-shift-extend-banner" class="hidden rounded-2xl bg-slate-50 border border-slate-100 p-4 space-y-2">
                    <p id="edit-shift-extend-hint-primary" class="text-sm font-medium text-slate-700"></p>
                    <p id="edit-shift-extend-hint-secondary" class="text-xs text-slate-500"></p>
                </div>
                <div id="edit-shift-error" class="hidden p-4 rounded-xl text-sm font-medium bg-red-50 text-red-600"></div>
                <div class="flex gap-3">
                    <button type="button" id="edit-shift-modal-cancel" class="flex-1 bg-slate-100 text-slate-700 font-bold py-4 rounded-xl hover:bg-slate-200 transition-all">{{ __('portal.cancel_shift_cancel_btn') }}</button>
                    <button type="button" id="edit-shift-save-btn" data-testid="shift-edit-save" class="flex-1 bg-brand-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-brand-600/20 hover:bg-brand-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed">{{ __('portal.edit_shift_save') }}</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const locale = '{{ app()->getLocale() }}';
        const checkUrl = '{{ route("driverportal.shifts.check-availability", ["locale" => app()->getLocale()]) }}';
        const confirmUrl = '{{ route("driverportal.shifts.confirm", ["locale" => app()->getLocale()]) }}';
        const previewCopyUrl = '{{ route("driverportal.shifts.copy-previous-week-preview", ["locale" => app()->getLocale()]) }}';
        const confirmCopyUrl = '{{ route("driverportal.shifts.copy-previous-week-confirm", ["locale" => app()->getLocale()]) }}';
        const csrf = '{{ csrf_token() }}';
        const minDate = '{{ $minDate }}';
        const maxDate = '{{ $maxDate }}';
        const minTimeToday = '{{ $minTimeToday ?? '' }}';
        const stationNames = @json($stations->pluck('name', 'id'));
        const stationAddresses = @json($stations->pluck('address', 'id'));
        const reasonLabels = @json($copyReasonLabels);
        const portalAllowedDurations = @json($allowedDurations);
        const editShiftModalTitleEdit = @json(__('portal.edit_shift'));
        const editShiftModalTitleExtend = @json(__('portal.extend_shift_title'));
        const extendHintNextTpl = @json(__('portal.extend_shift_hint_next'));
        const extendHintNoNext = @json(__('portal.extend_shift_hint_no_next'));
        const extendDurationHelp = @json(__('portal.extend_shift_duration_help'));

        function updateStartTimeOptions() {
            var modal = document.getElementById('create-modal');
            var dateEl = document.getElementById('create-date');
            var startEl = document.getElementById('create-start');
            var minDate = modal?.dataset.minDate || '';
            var minTimeToday = modal?.dataset.minTimeToday || '';
            if (!dateEl || !startEl || !minDate || !minTimeToday) return;
            var isToday = dateEl.value === minDate;
            var firstValid = null;
            for (var i = 0; i < startEl.options.length; i++) {
                var opt = startEl.options[i];
                var disabled = isToday && opt.value < minTimeToday;
                opt.disabled = disabled;
                if (!disabled && firstValid === null) firstValid = opt.value;
            }
            if (isToday && firstValid && startEl.value < minTimeToday) startEl.value = firstValid;
        }
        function openCreateModalForDate(isoDate) {
            document.getElementById('create-modal').classList.remove('hidden');
            var dateEl = document.getElementById('create-date');
            if (dateEl && isoDate) dateEl.value = isoDate;
            updateStartTimeOptions();
            document.getElementById('availability-message').classList.add('hidden');
            document.getElementById('confirm-shift-btn').disabled = true;
        }
        function openCreateModalFromSlot(slot) {
            document.getElementById('create-modal').classList.remove('hidden');
            var dateEl = document.getElementById('create-date');
            var stationEl = document.getElementById('create-station');
            var startEl = document.getElementById('create-start');
            var durationEl = document.getElementById('create-duration');
            if (dateEl && slot.date_iso) dateEl.value = slot.date_iso;
            if (stationEl && slot.station_id) stationEl.value = String(slot.station_id);
            if (startEl && slot.start) startEl.value = slot.start;
            if (durationEl && slot.duration) durationEl.value = String(slot.duration);
            updateStartTimeOptions();
            document.getElementById('availability-message').classList.add('hidden');
            document.getElementById('confirm-shift-btn').disabled = true;
        }
        window.openCreateModalForDate = openCreateModalForDate;
        window.openCreateModalFromSlot = openCreateModalFromSlot;
        window.updateStartTimeOptions = updateStartTimeOptions;
        document.getElementById('create-date')?.addEventListener('change', updateStartTimeOptions);

        function getPayload() {
            return {
                station_id: parseInt(document.getElementById('create-station').value, 10),
                date: document.getElementById('create-date').value,
                start_time: document.getElementById('create-start').value,
                duration_hours: parseInt(document.getElementById('create-duration').value, 10),
                _token: csrf
            };
        }

        function isDateInPlanningWindow() {
            var modal = document.getElementById('create-modal');
            var dateEl = document.getElementById('create-date');
            var minDate = modal?.dataset.minDate || '';
            var maxDate = modal?.dataset.maxDate || '';
            var value = dateEl?.value || '';
            return value >= minDate && value <= maxDate;
        }

        function showDateRangeError() {
            var msg = document.getElementById('availability-message');
            msg.classList.remove('hidden', 'bg-green-50', 'text-green-700');
            msg.classList.add('bg-red-50', 'text-red-600');
            msg.textContent = '{{ __("portal.shift_date_outside_planning_window") }}';
        }

        document.getElementById('check-availability-btn')?.addEventListener('click', function() {
            var msg = document.getElementById('availability-message');
            msg.classList.remove('hidden', 'bg-green-50', 'text-green-700', 'bg-red-50', 'text-red-600');
            msg.textContent = '';
            var btn = document.getElementById('confirm-shift-btn');
            btn.disabled = true;
            if (!isDateInPlanningWindow()) {
                showDateRangeError();
                return;
            }
            axios.post(checkUrl, getPayload())
                .then(function(res) {
                    if (res.data.available && res.data.count > 0) {
                        msg.classList.add('bg-green-50', 'text-green-700');
                        msg.textContent = '{{ __("portal.available_vehicles") }}'.replace(':count', res.data.count);
                        btn.disabled = false;
                    } else {
                        msg.classList.add('bg-red-50', 'text-red-600');
                        msg.textContent = res.data.error || '{{ __("portal.not_available") }}';
                    }
                })
                .catch(function(err) {
                    msg.classList.add('bg-red-50', 'text-red-600');
                    msg.textContent = (err.response?.data?.error) || '{{ __("portal.check_failed") }}';
                });
        });

        document.getElementById('confirm-shift-btn')?.addEventListener('click', function() {
            var btn = this;
            var msg = document.getElementById('availability-message');
            if (!isDateInPlanningWindow()) {
                msg.classList.remove('hidden', 'bg-green-50', 'text-green-700');
                msg.classList.add('bg-red-50', 'text-red-600');
                msg.textContent = '{{ __("portal.shift_date_outside_planning_window") }}';
                return;
            }
            btn.disabled = true;
            axios.post(confirmUrl, getPayload())
                .then(function(res) {
                    if (res.data.success) {
                        document.getElementById('create-modal').classList.add('hidden');
                        window.location.reload();
                    }
                })
                .catch(function(err) {
                    msg.classList.remove('hidden', 'bg-green-50', 'text-green-700');
                    msg.classList.add('bg-red-50', 'text-red-600');
                    msg.textContent = err.response?.data?.error || '{{ __("portal.confirm_failed") }}';
                    btn.disabled = false;
                });
        });

        function getNextMondayYmd() {
            var d = new Date();
            var day = d.getDay();
            var diff = day === 0 ? 1 : (8 - day) % 7;
            if (diff === 0) diff = 7;
            d.setDate(d.getDate() + diff);
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }

        function openCopyModal() {
            var modal = document.getElementById('copy-modal');
            modal.classList.remove('hidden');
            document.getElementById('copy-modal-loading').classList.remove('hidden');
            document.getElementById('copy-modal-empty').classList.add('hidden');
            document.getElementById('copy-modal-content').classList.add('hidden');
            document.getElementById('copy-modal-footer').classList.add('hidden');
            document.getElementById('copy-confirm-error').classList.add('hidden');
            document.getElementById('copy-confirm-error').textContent = '';
            axios.post(previewCopyUrl, { target_week_start: getNextMondayYmd(), _token: csrf })
                .then(function(res) {
                    document.getElementById('copy-modal-loading').classList.add('hidden');
                    var proposed = res.data.proposed || [];
                    var conflicts = res.data.conflicts || [];
                    if (proposed.length === 0 && conflicts.length === 0) {
                        document.getElementById('copy-modal-empty').classList.remove('hidden');
                        return;
                    }
                    document.getElementById('copy-modal-content').classList.remove('hidden');
                    document.getElementById('copy-modal-footer').classList.remove('hidden');
                    var proposedList = document.getElementById('copy-proposed-list');
                    var conflictsList = document.getElementById('copy-conflicts-list');
                    proposedList.innerHTML = '';
                    conflictsList.innerHTML = '';
                    proposed.forEach(function(item, index) {
                        var li = document.createElement('li');
                        var stationName = stationNames[item.station_id] || ('#' + item.station_id);
                        var stationAddr = (stationAddresses && stationAddresses[item.station_id]) ? stationAddresses[item.station_id] : '';
                        var addrHtml = stationAddr ? '<span class="block text-slate-400 text-xs mt-0.5 whitespace-nowrap truncate" title="' + stationAddr.replace(/"/g, '&quot;') + '">' + stationAddr + '</span>' : '';
                        li.className = 'flex items-center gap-3 p-3 rounded-2xl border border-slate-100 bg-slate-50/50';
                        li.innerHTML = '<label class="flex items-center gap-3 flex-1 cursor-pointer min-w-0">' +
                            '<input type="checkbox" class="copy-proposed-cb rounded border-slate-300 text-brand-600 focus:ring-brand-500 shrink-0" data-index="' + index + '" checked>' +
                            '<span class="font-medium text-slate-800">' + item.date + ' ' + item.start_time + '</span>' +
                            '<span class="text-slate-500 text-sm shrink-0">' + item.duration_hours + 'h</span>' +
                            '<span class="text-slate-500 text-sm min-w-0"><span class="block">' + stationName + '</span>' + addrHtml + '</span>' +
                            '<span class="text-slate-400 text-xs shrink-0">(' + (item.available_vehicle_count || 0) + ' {{ __("portal.copy_vehicle_count") }})</span>' +
                            '</label>';
                        proposedList.appendChild(li);
                        li.dataset.date = item.date;
                        li.dataset.startTime = item.start_time;
                        li.dataset.stationId = item.station_id;
                        li.dataset.durationHours = item.duration_hours;
                    });
                    conflicts.forEach(function(item) {
                        var li = document.createElement('li');
                        var reason = reasonLabels[item.reason_code] || item.reason_code;
                        li.className = 'flex items-center justify-between p-3 rounded-2xl border border-amber-100 bg-amber-50/50 text-slate-700';
                        li.innerHTML = '<span class="font-medium">' + item.date + ' ' + item.start_time + ' · ' + item.duration_hours + 'h</span><span class="text-amber-700 text-sm">' + reason + '</span>';
                        conflictsList.appendChild(li);
                    });
                })
                .catch(function(err) {
                    document.getElementById('copy-modal-loading').classList.add('hidden');
                    document.getElementById('copy-modal-empty').classList.remove('hidden');
                    document.getElementById('copy-modal-empty').textContent = err.response?.data?.message || '{{ __("portal.check_failed") }}';
                });
        }

        function closeCopyModal() {
            document.getElementById('copy-modal').classList.add('hidden');
        }

        function updateEditStartTimeOptions() {
            var modal = document.getElementById('edit-shift-modal');
            var dateEl = document.getElementById('edit-shift-date');
            var startEl = document.getElementById('edit-shift-start');
            if (!dateEl || !startEl || !minDate || !minTimeToday) return;
            var isToday = dateEl.value === minDate;
            var firstValid = null;
            for (var i = 0; i < startEl.options.length; i++) {
                var opt = startEl.options[i];
                var disabled = isToday && opt.value < minTimeToday;
                opt.disabled = disabled;
                if (!disabled && firstValid === null) firstValid = opt.value;
            }
            if (isToday && firstValid && startEl.value < minTimeToday) startEl.value = firstValid;
        }
        document.getElementById('edit-shift-date')?.addEventListener('change', updateEditStartTimeOptions);

        function setEditDurationOptions(hoursList) {
            var sel = document.getElementById('edit-shift-duration');
            if (!sel) return;
            sel.innerHTML = '';
            (hoursList || []).forEach(function(h) {
                var o = document.createElement('option');
                o.value = String(h);
                o.textContent = h + 'h';
                sel.appendChild(o);
            });
        }

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.shifts-grid-edit-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            var url = btn.getAttribute('data-edit-url');
            var date = btn.getAttribute('data-edit-date') || minDate;
            var start = btn.getAttribute('data-edit-start') || '08:00';
            var duration = btn.getAttribute('data-edit-duration') || '8';
            var extendOngoing = btn.getAttribute('data-extend-ongoing') === '1';
            var extensionDurations = [];
            try {
                extensionDurations = JSON.parse(btn.getAttribute('data-extension-durations') || '[]') || [];
            } catch (err) {
                extensionDurations = [];
            }
            var nextBooked = null;
            try {
                nextBooked = JSON.parse(btn.getAttribute('data-next-booked') || 'null');
            } catch (err2) {
                nextBooked = null;
            }
            if (!url) return;
            var modalTitle = document.getElementById('edit-shift-modal-title');
            var dateEl = document.getElementById('edit-shift-date');
            var startEl = document.getElementById('edit-shift-start');
            var durationEl = document.getElementById('edit-shift-duration');
            var extendBanner = document.getElementById('edit-shift-extend-banner');
            var hintPrimary = document.getElementById('edit-shift-extend-hint-primary');
            var hintSecondary = document.getElementById('edit-shift-extend-hint-secondary');
            document.getElementById('edit-shift-modal').classList.remove('hidden');
            dateEl.value = date;
            dateEl.min = minDate;
            dateEl.max = maxDate;
            startEl.value = start;
            document.getElementById('edit-shift-error').classList.add('hidden');
            document.getElementById('edit-shift-save-btn').dataset.editUrl = url;
            document.getElementById('edit-shift-save-btn').dataset.extendOngoing = extendOngoing ? '1' : '0';
            if (extendOngoing) {
                if (modalTitle) modalTitle.textContent = editShiftModalTitleExtend;
                dateEl.disabled = true;
                startEl.disabled = true;
                setEditDurationOptions(extensionDurations);
                if (durationEl.options.length) {
                    durationEl.value = durationEl.options[0].value;
                }
                extendBanner.classList.remove('hidden');
                if (nextBooked) {
                    hintPrimary.textContent = extendHintNextTpl.replace(':time', String(nextBooked));
                } else {
                    hintPrimary.textContent = extendHintNoNext;
                }
                hintSecondary.textContent = extendDurationHelp;
            } else {
                if (modalTitle) modalTitle.textContent = editShiftModalTitleEdit;
                dateEl.disabled = false;
                startEl.disabled = false;
                setEditDurationOptions(portalAllowedDurations);
                durationEl.value = String(duration);
                updateEditStartTimeOptions();
                extendBanner.classList.add('hidden');
                hintPrimary.textContent = '';
                hintSecondary.textContent = '';
            }
        });
        document.getElementById('edit-shift-modal-cancel')?.addEventListener('click', function() {
            document.getElementById('edit-shift-modal').classList.add('hidden');
        });
        document.getElementById('edit-shift-save-btn')?.addEventListener('click', function() {
            var url = this.dataset.editUrl;
            if (!url) return;
            var dateEl = document.getElementById('edit-shift-date');
            var startEl = document.getElementById('edit-shift-start');
            var durationEl = document.getElementById('edit-shift-duration');
            var errEl = document.getElementById('edit-shift-error');
            var extendOngoing = this.dataset.extendOngoing === '1';
            var payload = {
                date: dateEl.value,
                start_time: startEl.value,
                duration_hours: parseInt(durationEl.value, 10),
                _token: csrf
            };
            if (extendOngoing) {
                payload.extend_ongoing = true;
            }
            if (dateEl.value < minDate || dateEl.value > maxDate) {
                errEl.textContent = '{{ __("portal.shift_date_outside_planning_window") }}';
                errEl.classList.remove('hidden');
                return;
            }
            this.disabled = true;
            errEl.classList.add('hidden');
            axios.post(url, payload)
                .then(function(res) {
                    if (res.data.success) {
                        document.getElementById('edit-shift-modal').classList.add('hidden');
                        window.location.reload();
                    }
                })
                .catch(function(err) {
                    errEl.textContent = (err.response?.data?.error) || '{{ __("portal.confirm_failed") }}';
                    errEl.classList.remove('hidden');
                    document.getElementById('edit-shift-save-btn').disabled = false;
                });
        });

        document.getElementById('copy-prev-week')?.addEventListener('click', openCopyModal);
        document.getElementById('copy-modal-close')?.addEventListener('click', closeCopyModal);
        document.getElementById('copy-modal-cancel')?.addEventListener('click', closeCopyModal);

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.shifts-grid-cancel-btn');
            if (!btn) return;
            e.preventDefault();
            e.stopPropagation();
            var url = btn.getAttribute('data-cancel-url');
            var shiftId = btn.getAttribute('data-shift-id');
            if (!url) return;
            document.getElementById('cancel-shift-modal').classList.remove('hidden');
            document.getElementById('cancel-shift-error').classList.add('hidden');
            document.getElementById('cancel-shift-modal-confirm').disabled = false;
            document.getElementById('cancel-shift-modal-confirm').dataset.cancelUrl = url;
            document.getElementById('cancel-shift-modal-confirm').dataset.shiftId = shiftId;
        });
        document.getElementById('cancel-shift-modal-keep')?.addEventListener('click', function() {
            document.getElementById('cancel-shift-modal').classList.add('hidden');
        });
        document.getElementById('cancel-shift-modal')?.addEventListener('click', function(e) {
            if (e.target === document.getElementById('cancel-shift-modal')) document.getElementById('cancel-shift-modal').classList.add('hidden');
        });
        document.getElementById('cancel-shift-modal-confirm')?.addEventListener('click', function() {
            var url = this.dataset.cancelUrl;
            var shiftId = this.dataset.shiftId;
            if (!url) return;
            this.disabled = true;
            document.getElementById('cancel-shift-error').classList.add('hidden');
            axios.post(url, { _token: csrf })
                .then(function(res) {
                    if (res.data.success) {
                        document.getElementById('cancel-shift-modal').classList.add('hidden');
                        window.location.reload();
                    }
                })
                .catch(function(err) {
                    this.disabled = false;
                    var errEl = document.getElementById('cancel-shift-error');
                    errEl.textContent = err.response?.status === 403 ? '{{ __("portal.cancel_forbidden") }}' : '{{ __("portal.cancel_invalid") }}';
                    errEl.classList.remove('hidden');
                });
        });

        document.getElementById('copy-modal-confirm')?.addEventListener('click', function() {
            var checkboxes = document.querySelectorAll('#copy-proposed-list .copy-proposed-cb:checked');
            var selections = [];
            checkboxes.forEach(function(cb) {
                var li = cb.closest('li');
                if (!li) return;
                selections.push({
                    station_id: parseInt(li.dataset.stationId, 10),
                    starts_at: li.dataset.date + ' ' + li.dataset.startTime + ':00',
                    duration_hours: parseFloat(li.dataset.durationHours)
                });
            });
            if (selections.length === 0) {
                closeCopyModal();
                return;
            }
            var btn = document.getElementById('copy-modal-confirm');
            var errEl = document.getElementById('copy-confirm-error');
            btn.disabled = true;
            errEl.classList.add('hidden');
            errEl.textContent = '';
            axios.post(confirmCopyUrl, { selections: selections, _token: csrf })
                .then(function(res) {
                    if (res.data.success) {
                        closeCopyModal();
                        window.location.reload();
                    } else {
                        btn.disabled = false;
                        var conflicts = res.data.conflicts || [];
                        var conflictsList = document.getElementById('copy-conflicts-list');
                        conflictsList.innerHTML = '';
                        conflicts.forEach(function(item) {
                            var li = document.createElement('li');
                            var reason = reasonLabels[item.reason_code] || item.reason_code;
                            li.className = 'flex items-center justify-between p-3 rounded-2xl border border-amber-100 bg-amber-50/50 text-slate-700';
                            li.innerHTML = '<span class="font-medium">' + (item.date || '') + ' ' + (item.start_time || '') + ' · ' + (item.duration_hours || 0) + 'h</span><span class="text-amber-700 text-sm">' + reason + '</span>';
                            conflictsList.appendChild(li);
                        });
                        errEl.textContent = '{{ __("portal.confirm_failed") }}';
                        errEl.classList.remove('hidden');
                    }
                })
                .catch(function(err) {
                    btn.disabled = false;
                    var errEl = document.getElementById('copy-confirm-error');
                    errEl.textContent = (err.response?.data?.message || err.response?.data?.error) || '{{ __("portal.confirm_failed") }}';
                    errEl.classList.remove('hidden');
                    var conflicts = err.response?.data?.conflicts || [];
                    var conflictsList = document.getElementById('copy-conflicts-list');
                    if (conflictsList) {
                        conflictsList.innerHTML = '';
                        conflicts.forEach(function(item) {
                            var li = document.createElement('li');
                            var reason = reasonLabels[item.reason_code] || item.reason_code;
                            li.className = 'flex items-center justify-between p-3 rounded-2xl border border-amber-100 bg-amber-50/50 text-slate-700';
                            li.innerHTML = '<span class="font-medium">' + (item.date || '') + ' ' + (item.start_time || '') + ' · ' + (item.duration_hours || 0) + 'h</span><span class="text-amber-700 text-sm">' + reason + '</span>';
                            conflictsList.appendChild(li);
                        });
                    }
                });
        });
    })();
    </script>
</div>
@endsection
