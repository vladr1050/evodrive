@extends('driverportal.layouts.portal')

@section('title', __('portal.dashboard'))

@section('content')
<div class="animate-fade-in">
    {{-- Header Section --}}
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-900">{{ __('portal.dashboard') }}</h1>
            <p class="text-slate-500 mt-1">{{ __('portal.welcome_back', ['name' => $driverName]) }}</p>
        </div>
        <button type="button" class="relative p-2 text-slate-400 hover:text-slate-600 transition-colors" aria-label="Notifications">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-slate-50"></span>
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Next Shift Countdown & Upcoming Shifts --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Next Shift Card --}}
            <div class="bg-brand-600 rounded-3xl p-8 text-white shadow-brand-xl relative overflow-hidden group">
                <div class="relative z-10">
                    <p class="text-brand-100 text-sm font-bold uppercase tracking-wider mb-2">{{ __('portal.next_shift') }}</p>
                    <div class="flex items-baseline gap-2 mb-6">
                        <span class="text-5xl font-bold">{{ $nextShiftCountdown }}</span>
                        <span class="text-brand-200 font-medium">{{ __('portal.until_start') }}</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10 transition-colors hover:bg-white/20">
                            <div class="flex items-center gap-2 text-brand-100 mb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-1.1 0-2 .9-2 2v7c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2v-1z"/><circle cx="7" cy="17" r="2"/><circle cx="15" cy="17" r="2"/></svg>
                                <span class="text-xs font-bold uppercase tracking-wide">{{ __('portal.vehicle') }}</span>
                            </div>
                            <p class="font-bold">{{ $nextShiftVehicle }}</p>
                        </div>
                        <div class="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/10 transition-colors hover:bg-white/20">
                            <div class="flex items-center gap-2 text-brand-100 mb-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                <span class="text-xs font-bold uppercase tracking-wide">{{ __('portal.station') }}</span>
                            </div>
                            <p class="font-bold">{{ $nextShiftStation }}</p>
                        </div>
                    </div>
                </div>
                <div class="absolute -right-16 -bottom-16 w-64 h-64 bg-white/5 rounded-full blur-3xl transition-transform group-hover:scale-110 duration-700"></div>
            </div>

            {{-- Upcoming Shifts Section --}}
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-slate-900">{{ __('portal.upcoming_shifts') }}</h2>
                    <a href="{{ route('driverportal.shifts', ['locale' => app()->getLocale()]) }}" class="text-brand-600 text-sm font-bold hover:underline">View All</a>
                </div>
                <div class="space-y-4">
                    @foreach($upcomingShifts as $shift)
                        @php
                            $isToday = ($shift['date'] ?? '') === date('Y-m-d');
                            $isTomorrow = ($shift['date'] ?? '') === date('Y-m-d', strtotime('+1 day'));
                            $timeLabel = $isToday ? __('portal.today') : ($isTomorrow ? __('portal.tomorrow') : (\Carbon\Carbon::parse($shift['date'])->translatedFormat('D, j M')));
                            $timeDisplay = $timeLabel . ', ' . $shift['time'];
                        @endphp
                        <div role="button" tabindex="0" onclick="window.location.href='{{ route('driverportal.shifts', ['locale' => app()->getLocale()]) }}'" class="bg-white border border-slate-100 rounded-2xl p-5 flex items-center justify-between hover:shadow-md transition-all cursor-pointer group">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400 group-hover:bg-brand-50 group-hover:text-brand-600 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-900">{{ $timeDisplay }}</p>
                                    <div class="flex items-center gap-3 text-sm text-slate-500 mt-1">
                                        <span class="flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-1.1 0-2 .9-2 2v7c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2v-1z"/><circle cx="7" cy="17" r="2"/><circle cx="15" cy="17" r="2"/></svg>
                                            {{ $shift['vehicle'] }}
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                            {{ $shift['station'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $shift['status'] === 'Confirmed' ? 'bg-green-50 text-green-600' : 'bg-amber-50 text-amber-600' }}">
                                    {{ $shift['status'] }}
                                </span>
                                <svg class="text-slate-300 group-hover:text-slate-900 transition-colors" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Sidebar Stats --}}
        <div class="space-y-8">
            {{-- Weekly Stats Card --}}
            <div class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm">
                <h3 class="font-bold text-slate-900 mb-4">{{ __('portal.weekly_stats') }}</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 text-sm">{{ __('portal.total_hours') }}</span>
                        <span class="font-bold">{{ $weeklyTotalHours }}h</span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                        <div class="bg-brand-600 h-full transition-all duration-1000" style="width: {{ $weeklyShiftsTotal ? round($weeklyShiftsDone / $weeklyShiftsTotal * 100) : 0 }}%;"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 text-sm">{{ __('portal.completed_shifts') }}</span>
                        <span class="font-bold">{{ $weeklyShiftsDone }} / {{ $weeklyShiftsTotal }}</span>
                    </div>
                </div>
            </div>

            {{-- Help Card --}}
            <div class="bg-slate-900 rounded-3xl p-6 text-white relative overflow-hidden group">
                <div class="relative z-10">
                    <h3 class="font-bold mb-2">{{ __('portal.need_help') }}</h3>
                    <p class="text-slate-400 text-sm mb-4">{{ __('portal.need_help_desc') }}</p>
                    <button type="button" onclick="window.location.href='mailto:support@evodrive.lv'" class="w-full bg-white/10 hover:bg-white/20 text-white font-bold py-3 rounded-xl transition-all active:scale-[0.98]">
                        Contact Support
                    </button>
                </div>
                <div class="absolute -top-10 -left-10 w-32 h-32 bg-brand-600/10 rounded-full blur-2xl group-hover:bg-brand-600/20 transition-colors"></div>
            </div>
        </div>
    </div>
</div>
@endsection
