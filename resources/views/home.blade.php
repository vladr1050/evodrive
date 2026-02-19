@extends('layouts.app')

@section('title', __('ui.home_hero_title') . ' ' . __('ui.home_hero_repeat') . ' — EvoDrive.lv')

@section('content')
<div class="flex flex-col min-h-screen pt-20">
    {{-- SECTION 1: HERO - FOCUS ON SEGMENTATION --}}
    <section class="relative pt-20 pb-24 md:pt-32 md:pb-40 bg-white border-b border-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 max-w-3xl mx-auto">
                <h1 class="text-5xl md:text-7xl font-black text-slate-900 tracking-tighter mb-6 leading-tight">
                    {{ __('ui.home_hero_title') }}<br/>
                    <span class="text-brand-600">{{ __('ui.home_hero_repeat') }}</span>
                </h1>
                <p class="text-xl text-slate-500 font-medium">
                    {{ __('ui.home_hero_sub') }}
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
                {{-- PATH 1: WORK WITH FLEET --}}
                <div class="bg-white p-12 rounded-[32px] border border-slate-100 hover:border-brand-600 transition-all hover:shadow-2xl group flex flex-col">
                    <div class="w-16 h-16 bg-slate-900 text-white rounded-2xl flex items-center justify-center mb-8 group-hover:bg-brand-600 transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-black text-slate-900 mb-4">{{ __('ui.home_work_title') }}</h2>
                    <p class="text-slate-500 text-lg mb-8 leading-relaxed">{{ __('ui.home_work_desc') }}</p>
                    <ul class="space-y-4 mb-12">
                        @foreach(['home_work_1','home_work_2','home_work_3'] as $key)
                            <li class="flex items-center gap-3 text-base font-bold text-slate-700">
                                <x-icon-check class="w-5 h-5 text-brand-600 shrink-0"/> {{ __('ui.' . $key) }}
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-auto space-y-4">
                        <a href="{{ $applyUrl }}"
                           class="flex items-center justify-center w-full bg-brand-600 text-white font-bold py-5 rounded-lg h-16 text-lg hover:bg-brand-700 transition-all min-h-[56px]">
                            {{ __('ui.home_work_apply') }}
                        </a>
                        <a href="{{ route('landing.google', ['locale' => app()->getLocale()]) }}"
                           class="block text-center text-sm font-bold text-slate-400 hover:text-slate-900 transition-colors uppercase tracking-widest">
                            {{ __('ui.home_work_learn') }}
                        </a>
                    </div>
                </div>

                {{-- PATH 2: RENT A CAR --}}
                <div class="bg-white p-12 rounded-[32px] border border-slate-100 hover:border-brand-600 transition-all hover:shadow-2xl group flex flex-col">
                    <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-2xl flex items-center justify-center mb-8 group-hover:bg-brand-600 group-hover:text-white transition-colors">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                    </div>
                    <h2 class="text-3xl font-black text-slate-900 mb-4">{{ __('ui.home_rent_title') }}</h2>
                    <p class="text-slate-500 text-lg mb-8 leading-relaxed">{{ __('ui.home_rent_desc') }}</p>
                    <ul class="space-y-4 mb-12">
                        @foreach(['home_rent_1','home_rent_2','home_rent_3'] as $key)
                            <li class="flex items-center gap-3 text-base font-bold text-slate-700">
                                <x-icon-check class="w-5 h-5 text-brand-600 shrink-0"/> {{ __('ui.' . $key) }}
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-auto space-y-4">
                        <a href="{{ route('landing.meta', ['locale' => app()->getLocale()]) }}"
                           class="flex items-center justify-center w-full bg-transparent text-slate-900 font-bold py-5 rounded-lg border-2 border-slate-200 hover:border-slate-900 h-16 text-lg transition-all min-h-[56px]">
                            {{ __('ui.home_rent_view') }}
                        </a>
                        <a href="{{ route('landing.meta', ['locale' => app()->getLocale()]) }}#pricing"
                           class="block text-center text-sm font-bold text-slate-400 hover:text-slate-900 transition-colors uppercase tracking-widest">
                            {{ __('ui.home_rent_pricing') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SECTION 2: WHY EVODRIVE (MINIMAL TRUST) --}}
    <section class="py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-12">
                @foreach([
                    ['icon' => 'shield', 'title' => 'home_trust_1', 'desc' => 'home_trust_1_desc'],
                    ['icon' => 'clock', 'title' => 'home_trust_2', 'desc' => 'home_trust_2_desc'],
                    ['icon' => 'zap', 'title' => 'home_trust_3', 'desc' => 'home_trust_3_desc'],
                    ['icon' => 'headset', 'title' => 'home_trust_4', 'desc' => 'home_trust_4_desc'],
                ] as $item)
                    <div class="text-center group">
                        <div class="flex justify-center mb-6">
                            @if($item['icon'] === 'shield')
                                <svg class="w-8 h-8 text-brand-600 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            @elseif($item['icon'] === 'clock')
                                <svg class="w-8 h-8 text-brand-600 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            @elseif($item['icon'] === 'zap')
                                <svg class="w-8 h-8 text-brand-600 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            @else
                                <svg class="w-8 h-8 text-brand-600 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            @endif
                        </div>
                        <div class="text-xl font-black text-slate-900 mb-1">{{ __('ui.' . $item['title']) }}</div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ __('ui.' . $item['desc']) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>
@endsection
