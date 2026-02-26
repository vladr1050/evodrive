@extends('driverportal.layouts.portal')

@section('title', __('portal.profile'))

@section('content')
@php
    $initials = strtoupper(collect(explode(' ', $driverName))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('') ?: '?');
@endphp
<!-- Header Section -->
<div class="mb-10">
    <h1 class="text-3xl font-bold text-slate-900">{{ __('portal.profile') }}</h1>
    <p class="text-slate-500 mt-1">{{ __('portal.profile_subtitle') }}</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-12">
    <!-- Profile Sidebar -->
    <div class="space-y-8">
        <div class="rounded-3xl border border-slate-200 bg-white px-10 py-12 shadow-sm">
            <div class="flex flex-col items-center text-center">
                <div class="flex h-32 w-32 shrink-0 items-center justify-center rounded-3xl bg-blue-100/70">
                    <span class="text-5xl font-extrabold leading-none text-blue-600">
                        {{ $initials ?? 'RR' }}
                    </span>
                </div>

                <h2 class="mt-8 text-4xl font-extrabold tracking-tight text-slate-900">
                    {{ $driverName ?? 'Rossie Rolfson' }}
                </h2>

                <p class="mt-3 text-xl font-semibold text-slate-500">
                    Driver ID: <span class="text-slate-700">{{ $driverId ?? '#EVO-2' }}</span>
                </p>

                <div class="mt-10 h-px w-full bg-slate-200"></div>

                <div class="mt-10">
                    <span class="inline-flex items-center gap-3 rounded-full bg-emerald-50 px-10 py-4 text-sm font-extrabold tracking-widest text-emerald-700">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        ACTIVE STATUS
                    </span>
                </div>
            </div>
        </div>

        <!-- Security Status Card -->
        <div class="bg-white border border-slate-200 rounded-[40px] p-8 shadow-sm">
            <div class="flex items-center gap-3 mb-6">
                <svg class="text-brand-600" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>
                <h4 class="font-bold text-slate-900 uppercase tracking-widest text-xs">Security Status</h4>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-xs font-bold text-slate-600">2FA Auth</span>
                    <span class="text-[10px] font-bold text-green-600 uppercase tracking-wider">Enabled</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-xs font-bold text-slate-600">Identity</span>
                    <span class="text-[10px] font-bold text-green-600 uppercase tracking-wider">Verified</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Form -->
    <div class="lg:col-span-2">
        <div class="bg-white border border-slate-200 rounded-[40px] p-10 shadow-sm">
            <div class="flex items-center gap-4 mb-12">
                <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h3 class="text-2xl font-bold text-slate-900">Personal Details</h3>
            </div>

            <form method="POST" action="{{ route('driverportal.profile.update', ['locale' => app()->getLocale()]) }}" class="space-y-10">
                @csrf
                <input type="hidden" name="atd_number" value="{{ old('atd_number', $driverAtd) }}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-3 ml-1">Full Name</label>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('name', $driverName) }}"
                            class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-brand-600/10 focus:border-brand-600 outline-none transition-all font-bold text-slate-700"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-3 ml-1">Phone Number</label>
                        <input
                            type="text"
                            name="phone"
                            value="{{ old('phone', $driverPhone) }}"
                            class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-brand-600/10 focus:border-brand-600 outline-none transition-all font-bold text-slate-700"
                        >
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-3 ml-1">Email Address</label>
                    <input
                        type="email"
                        value="{{ $driverEmail }}"
                        disabled
                        class="w-full px-5 py-4 bg-slate-100 border border-slate-200 rounded-2xl text-slate-400 cursor-not-allowed font-bold"
                    >
                    <p class="text-[10px] text-slate-400 mt-3 italic font-medium">Email cannot be changed manually. Contact support for updates.</p>
                </div>

                <div class="pt-12 border-t border-slate-100">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/></svg>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-900">Documents</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Taxi License</p>
                                <p class="font-bold text-slate-900">{{ $driverAtd ?: 'ATD-12345678' }}</p>
                            </div>
                            <div class="w-8 h-8 bg-green-100 text-green-600 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </div>
                        <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Driving License</p>
                                <p class="font-bold text-slate-900">{{ $driverLicense ?: 'LV-99887766' }}</p>
                            </div>
                            <div class="w-8 h-8 bg-green-100 text-green-600 rounded-xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-12 flex justify-end">
                    <button
                        type="submit"
                        class="bg-slate-900 text-white font-bold px-10 py-5 rounded-2xl shadow-xl shadow-slate-900/20 hover:bg-slate-800 active:scale-[0.98] transition-all"
                    >
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
