@extends('layouts.app')

@section('title', $page?->getTranslated('meta_title') ?? __('ui.rent_hero_h1_1') . ' ' . __('ui.rent_hero_h1_2') . ' — EvoDrive.lv')

@section('content')
<div class="bg-white min-h-screen pb-20">
    @php $locale = app()->getLocale(); @endphp

    {{-- Hero Section --}}
    <section class="pt-16 pb-20 px-4 border-b border-slate-50 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-12">
                <div class="max-w-2xl">
                    <div class="flex items-center gap-2 mb-6">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-slate-900 text-white">
                            {{ __('ui.rent_badge_1') }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-brand-50 text-brand-600">
                            {{ __('ui.rent_badge_2') }}
                        </span>
                    </div>
                    <h1 class="text-5xl md:text-7xl font-black text-slate-900 tracking-tighter mb-8 leading-[0.9]">
                        {{ __('ui.rent_hero_h1_1') }}<br/>
                        <span class="text-brand-600">{{ __('ui.rent_hero_h1_2') }}</span>
                    </h1>
                    <p class="text-xl text-slate-500 font-medium leading-relaxed mb-10">
                        {{ __('ui.rent_hero_sub') }}
                    </p>
                    <a href="{{ $applyUrl }}"
                       class="inline-flex items-center justify-center bg-brand-600 text-white font-bold h-14 px-10 text-lg rounded-2xl shadow-xl shadow-brand-600/20 hover:bg-brand-700 transition-all active:scale-95">
                        {{ __('ui.start_application') }}
                        <x-icon-arrow-up-right class="w-5 h-5 ml-2 shrink-0"/>
                    </a>
                </div>

                @php
                    $pricingExample = collect($rentalCars ?? [])->sortBy('price')->first();
                    $examplePrice = $pricingExample ? $pricingExample['price'] : 350;
                    $exampleCar = $pricingExample ? $pricingExample['make'] . ' ' . $pricingExample['model'] : 'Tesla Model 3';
                    $examplePriceFormatted = number_format($examplePrice, $examplePrice == floor($examplePrice) ? 0 : 1, ',', ' ');
                @endphp
                <div class="bg-slate-900 p-10 rounded-[40px] text-white shadow-2xl shadow-slate-900/20 max-w-sm w-full">
                    <h3 class="text-xl font-black mb-8 flex items-center gap-2">
                        <x-logo class="w-6 h-6 object-contain flex-shrink-0"/>
                        {{ __('ui.rent_pricing_title') }}
                    </h3>
                    <div class="space-y-6">
                        <div class="flex justify-between items-center py-3 border-b border-white/10">
                            <span class="text-slate-400 font-bold">{{ $exampleCar }}</span>
                            <span class="font-black">€{{ $examplePriceFormatted }} <span class="text-[10px] uppercase text-slate-500">/wk</span></span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-white/10">
                            <span class="text-slate-400 font-bold">{{ __('ui.rent_insurance') }}</span>
                            <span class="text-emerald-400 font-black">{{ __('ui.rent_included') }}</span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-white/10">
                            <span class="text-slate-400 font-bold">{{ __('ui.rent_maintenance') }}</span>
                            <span class="text-emerald-400 font-black">{{ __('ui.rent_included') }}</span>
                        </div>
                        <div class="pt-4 flex justify-between items-center">
                            <span class="text-lg font-black uppercase tracking-widest text-slate-500">{{ __('ui.rent_fixed_cost') }}</span>
                            <span class="text-2xl font-black text-brand-600">€{{ $examplePriceFormatted }} <span class="text-[10px] uppercase text-slate-500">/wk</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- What's Included --}}
    <section class="py-24 bg-slate-50 border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                @foreach([['t' => 'rent_included_1_t', 'd' => 'rent_included_1_d'], ['t' => 'rent_included_2_t', 'd' => 'rent_included_2_d'], ['t' => 'rent_included_3_t', 'd' => 'rent_included_3_d'], ['t' => 'rent_included_4_t', 'd' => 'rent_included_4_d']] as $item)
                    <div class="flex items-center gap-4">
                        <div class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center text-white flex-shrink-0">
                            <x-icon-check-simple class="w-[18px] h-[18px] text-white" />
                        </div>
                        <div>
                            <div class="font-black text-slate-900 text-sm">{{ __('ui.' . $item['t']) }}</div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('ui.' . $item['d']) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Car Grid --}}
    <div id="pricing" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-24">
        <div class="flex justify-between items-center mb-16">
            <h2 class="text-3xl font-black text-slate-900 tracking-tight">{{ __('ui.rent_available_vehicles') }}</h2>
            <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                <x-icon-alert-triangle class="w-[14px] h-[14px] text-brand-600"/>
                {{ __('ui.rent_atd_required') }}
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
            @foreach($rentalCars ?? [] as $car)
                <div class="bg-white rounded-[32px] border border-slate-100 overflow-hidden hover:border-brand-600 transition-all group flex flex-col shadow-sm hover:shadow-2xl hover:shadow-slate-200">
                    <div class="relative h-72 bg-slate-50 overflow-hidden">
                        @if(!empty($car['image']))
                            <img src="{{ $car['image'] }}" alt="{{ $car['make'] }} {{ $car['model'] }}" loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-1000">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-slate-300">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                        <div class="absolute top-6 left-6 flex gap-2">
                            @foreach($car['categories'] ?? [] as $cat)
                                <span class="bg-slate-900 text-white px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-[0.2em] shadow-xl">{{ $cat }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="p-10 flex flex-col flex-grow">
                        <div class="flex justify-between items-start mb-10">
                            <div>
                                <h3 class="text-3xl font-black text-slate-900 tracking-tighter">{{ $car['make'] }} {{ $car['model'] }}</h3>
                                <div class="text-sm font-bold text-slate-400 mt-2 uppercase tracking-widest">{{ $car['year'] }} • {{ $car['type'] }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-4xl font-black text-brand-600">€{{ $car['price'] }}</div>
                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ __('ui.rent_weekly') }}</div>
                            </div>
                        </div>

                        <div class="bg-slate-50 rounded-2xl p-6 grid grid-cols-3 gap-6 border border-slate-100 mb-10">
                            <div class="text-center border-r border-slate-200">
                                <div class="text-[9px] text-slate-400 font-black uppercase mb-2 tracking-widest">{{ __('ui.rent_trans') }}</div>
                                <div class="text-xs font-black text-slate-700 flex items-center justify-center gap-2">
                                    <x-icon-settings class="w-[14px] h-[14px] text-brand-600"/> {{ $car['transmission'] }}
                                </div>
                            </div>
                            <div class="text-center border-r border-slate-200">
                                <div class="text-[9px] text-slate-400 font-black uppercase mb-2 tracking-widest">{{ __('ui.rent_energy') }}</div>
                                <div class="text-xs font-black text-slate-700 flex items-center justify-center gap-2">
                                    <x-icon-fuel class="w-[14px] h-[14px] text-brand-600"/> {{ $car['consumption'] }}
                                </div>
                            </div>
                            <div class="text-center">
                                <div class="text-[9px] text-slate-400 font-black uppercase mb-2 tracking-widest">{{ __('ui.rent_seats') }}</div>
                                <div class="text-xs font-black text-slate-700 flex items-center justify-center gap-2">
                                    <x-icon-users class="w-[14px] h-[14px] text-brand-600"/> {{ $car['seats'] }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto flex items-center justify-between pt-8 border-t border-slate-100">
                            <div class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em]">
                                {{ __('ui.rent_deposit') }}: <span class="text-slate-900">€{{ $car['deposit'] }}</span>
                            </div>
                            <a href="{{ route('landing.rent.detail', ['locale' => $locale, 'id' => $car['id']]) }}"
                               class="inline-flex items-center justify-center gap-2 bg-brand-600 text-white font-bold px-10 h-14 rounded-xl hover:bg-brand-700 transition-all active:scale-95">
                                {{ __('ui.rent_now') }}
                                <x-icon-arrow-up-right class="w-[18px] h-[18px]"/>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
