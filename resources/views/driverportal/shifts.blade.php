@extends('driverportal.layouts.portal')

@section('title', __('portal.shifts'))

@section('content')
<div x-data="{ filterStation: 'All', isStationDropdownOpen: false }" class="animate-fade-in">
    <!-- Header Section (UI 1:1 reference) -->
    <div class="mb-8 flex flex-col xl:flex-row xl:items-center justify-between gap-6">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">{{ __('portal.shifts') }}</h1>
            <p class="text-slate-500 mt-1 font-medium">{{ __('portal.shifts_subtitle') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2 bg-white p-1.5 rounded-2xl border border-slate-200 shadow-sm">
                <a href="{{ route('driverportal.shifts', ['locale' => app()->getLocale(), 'view' => 'current']) }}" class="px-4 py-2 rounded-xl text-sm font-bold transition-all {{ $view === 'current' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-900' }}">{{ __('portal.current_week') }}</a>
                <a href="{{ route('driverportal.shifts', ['locale' => app()->getLocale(), 'view' => 'next']) }}" class="px-4 py-2 rounded-xl text-sm font-bold transition-all {{ $view === 'next' ? 'bg-slate-900 text-white shadow-md' : 'text-slate-500 hover:text-slate-900' }}">{{ __('portal.next_week') }}</a>
            </div>

            <!-- Custom Station Dropdown (reference 1:1) -->
            <div class="relative">
                <button
                    type="button"
                    @click="isStationDropdownOpen = !isStationDropdownOpen"
                    class="flex items-center gap-3 bg-white px-5 py-3 rounded-2xl border border-slate-200 shadow-sm hover:border-brand-300 transition-all min-w-[180px] justify-between group"
                >
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" :class="filterStation !== 'All' ? 'text-brand-600' : 'text-slate-400'"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span class="text-sm font-bold text-slate-700" x-text="filterStation === 'All' ? '{{ __("portal.all_stations") }}' : filterStation"></span>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400 transition-transform duration-300" :class="isStationDropdownOpen ? 'rotate-45' : 'rotate-0'"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                </button>

                <div
                    x-show="isStationDropdownOpen"
                    x-cloak
                    @click.away="isStationDropdownOpen = false"
                    class="absolute top-full left-0 mt-2 w-full bg-white border border-slate-100 rounded-2xl shadow-xl z-20 overflow-hidden"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform -translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0"
                >
                    <button type="button" @click="filterStation = 'All'; isStationDropdownOpen = false" class="w-full px-5 py-3 text-left text-sm font-bold transition-colors flex items-center justify-between" :class="filterStation === 'All' ? 'bg-brand-50 text-brand-600' : 'text-slate-600 hover:bg-slate-50'">
                        <span>{{ __('portal.all_stations') }}</span>
                        <svg x-show="filterStation === 'All'" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                    @foreach($stations as $s)
                    <button type="button" @click="filterStation = '{{ addslashes($s->name) }}'; isStationDropdownOpen = false" class="w-full px-5 py-3 text-left text-sm font-bold transition-colors flex items-center justify-between" :class="filterStation === '{{ addslashes($s->name) }}' ? 'bg-brand-50 text-brand-600' : 'text-slate-600 hover:bg-slate-50'">
                        <span>{{ $s->name }}</span>
                        <svg x-show="filterStation === '{{ addslashes($s->name) }}'" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                    @endforeach
                </div>
            </div>
            <select id="filter-station" class="sr-only" aria-hidden="true" x-effect="$nextTick(()=>{const s=$el;if(s){s.value=filterStation==='All'?'':filterStation;s.dispatchEvent(new Event('change'))}})"><option value="">{{ __('portal.all_stations') }}</option>@foreach($stations as $s)<option value="{{ $s->name }}">{{ $s->name }}</option>@endforeach</select>

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

    <!-- Shifts Grid (reference UI 1:1) -->
    <div class="overflow-x-auto pb-4 -mx-4 px-4 md:mx-0 md:px-0">
        <div class="grid grid-cols-1 md:grid-cols-7 gap-4 min-w-[1000px] md:min-w-0">
            @foreach($weekDates as $dayInfo)
                <div class="space-y-4">
                    <div class="flex flex-col items-center py-3 bg-slate-100 rounded-2xl border border-slate-200 relative group">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 leading-none mb-1">{{ $dayInfo['name'] }}</span>
                        <span class="text-sm font-bold text-slate-700">{{ $dayInfo['date'] }} {{ $dayInfo['month'] }}</span>
                        <button type="button" onclick="openCreateModalForDate('{{ $dayInfo['iso'] }}')" class="absolute -top-2 -right-2 w-6 h-6 bg-white border border-slate-200 rounded-full flex items-center justify-center text-slate-400 hover:text-brand-600 hover:border-brand-600 shadow-sm transition-all opacity-0 group-hover:opacity-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="5" y2="19"/><line x1="5" x2="19" y1="12" y2="12"/></svg>
                        </button>
                    </div>
                    <div class="space-y-3 min-h-[200px]">
                        @php $dayShifts = collect($shifts)->where('day', $dayInfo['name'])->sortBy(fn($s) => (int)str_replace(':', '', $s['start']))->all(); @endphp
                        @foreach($dayShifts as $shift)
                            @include('driverportal.components.shift-block', ['shift' => $shift])
                        @endforeach
                        @if(empty($dayShifts))
                            <div class="h-full flex flex-col items-center justify-center py-12 text-center">
                                <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-300 mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <span class="text-[10px] font-medium text-slate-300 italic">{{ __('portal.no_shifts_planned') }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
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
        </div>
        <div class="flex items-center gap-2 text-slate-400 bg-slate-50 px-4 py-2 rounded-xl">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/></svg>
            <span class="text-xs font-medium">{{ __('portal.shifts_legend_info') }}</span>
        </div>
    </div>

    <!-- Create Shift Modal (reference UI 1:1) -->
    <div id="create-modal" data-testid="shift-create-modal" data-min-date="{{ $minDate }}" data-min-time-today="{{ $minTimeToday ?? '' }}" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" role="dialog" aria-modal="true" onclick="if(event.target===this)this.classList.add('hidden')">
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
                            @foreach($stations as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
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

    <script>
    (function() {
        const locale = '{{ app()->getLocale() }}';
        const checkUrl = '{{ route("driverportal.shifts.check-availability", ["locale" => app()->getLocale()]) }}';
        const confirmUrl = '{{ route("driverportal.shifts.confirm", ["locale" => app()->getLocale()]) }}';
        const previewCopyUrl = '{{ route("driverportal.shifts.copy-previous-week-preview", ["locale" => app()->getLocale()]) }}';
        const confirmCopyUrl = '{{ route("driverportal.shifts.copy-previous-week-confirm", ["locale" => app()->getLocale()]) }}';
        const csrf = '{{ csrf_token() }}';
        const stationNames = @json($stations->pluck('name', 'id'));
        const reasonLabels = @json($copyReasonLabels);

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
        window.openCreateModalForDate = openCreateModalForDate;
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

        document.getElementById('check-availability-btn')?.addEventListener('click', function() {
            var msg = document.getElementById('availability-message');
            msg.classList.remove('hidden', 'bg-green-50', 'text-green-700', 'bg-red-50', 'text-red-600');
            msg.textContent = '';
            var btn = document.getElementById('confirm-shift-btn');
            btn.disabled = true;
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

        document.getElementById('filter-station')?.addEventListener('change', function() {
            var val = this.value;
            document.querySelectorAll('.shift-card').forEach(function(el) {
                var name = el.getAttribute('data-station-name') || '';
                el.style.display = (!val || name === val) ? '' : 'none';
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
                        li.className = 'flex items-center gap-3 p-3 rounded-2xl border border-slate-100 bg-slate-50/50';
                        li.innerHTML = '<label class="flex items-center gap-3 flex-1 cursor-pointer">' +
                            '<input type="checkbox" class="copy-proposed-cb rounded border-slate-300 text-brand-600 focus:ring-brand-500" data-index="' + index + '" checked>' +
                            '<span class="font-medium text-slate-800">' + item.date + ' ' + item.start_time + '</span>' +
                            '<span class="text-slate-500 text-sm">' + item.duration_hours + 'h</span>' +
                            '<span class="text-slate-500 text-sm">' + stationName + '</span>' +
                            '<span class="text-slate-400 text-xs">(' + (item.available_vehicle_count || 0) + ' {{ __("portal.copy_vehicle_count") }})</span>' +
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
