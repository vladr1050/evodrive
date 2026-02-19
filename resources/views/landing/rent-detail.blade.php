@extends('layouts.app')

@push('scripts')
    @include('components.product-schema', ['car' => $car])
@endpush

@section('title', $car['make'] . ' ' . $car['model'] . ' — ' . __('ui.rent_now') . ' — EvoDrive.lv')

@section('content')
<div class="bg-white min-h-screen pb-20">
    @php $locale = app()->getLocale(); @endphp

    {{-- Sticky Header --}}
    <div class="border-b border-slate-100 sticky top-16 z-20 bg-white/95 backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('landing.meta', ['locale' => $locale]) }}" class="flex items-center gap-2 text-[11px] font-black text-slate-400 hover:text-slate-900 transition-colors uppercase tracking-widest">
                <x-icon-chevron-left class="w-[18px] h-[18px]"/>
                {{ __('ui.rent_back_to_fleet') }}
            </a>
            <div class="hidden sm:block text-center">
                <h1 class="text-sm font-black text-slate-900 uppercase tracking-tight">{{ $car['make'] }} {{ $car['model'] }}</h1>
            </div>
            <div class="text-xl font-black text-brand-600">€{{ $car['price'] }}<span class="text-[10px] font-bold text-slate-400 ml-1">/WK</span></div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-16">
            <div class="lg:col-span-8 space-y-16">
                <div class="rounded-[48px] overflow-hidden shadow-2xl shadow-slate-200/50 bg-slate-100 aspect-[16/10] w-full relative">
                    @if(!empty($car['image']))
                        <img src="{{ $car['image'] }}" alt="{{ $car['make'] }} {{ $car['model'] }}" loading="eager"
                             class="w-full h-full object-contain object-center vehicle-image"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="w-full h-full flex items-center justify-center text-slate-300 absolute inset-0 bg-slate-100" style="display: none;">
                            <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    @else
                        <div class="w-full h-full flex items-center justify-center text-slate-300">
                            <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    @endif
                </div>

                <div class="space-y-12">
                    <div>
                        <h2 class="text-4xl font-black text-slate-900 tracking-tighter mb-8">{{ __('ui.rent_vehicle_info') }}</h2>
                        <p class="text-xl text-slate-400 leading-relaxed font-medium">{{ $car['description'] ?? '' }}</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                        <div class="p-8 bg-slate-50 rounded-[32px] border border-slate-100">
                            <x-icon-settings class="text-brand-600 mb-4 w-6 h-6"/>
                            <div class="text-lg font-black text-slate-900">{{ $car['transmission'] }}</div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('ui.rent_gearbox') }}</div>
                        </div>
                        <div class="p-8 bg-slate-50 rounded-[32px] border border-slate-100">
                            <x-icon-fuel class="text-brand-600 mb-4 w-6 h-6"/>
                            <div class="text-lg font-black text-slate-900">{{ $car['consumption'] }}</div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('ui.rent_energy_label') }}</div>
                        </div>
                        <div class="p-8 bg-slate-50 rounded-[32px] border border-slate-100">
                            <x-icon-users class="text-brand-600 mb-4 w-6 h-6"/>
                            <div class="text-lg font-black text-slate-900">{{ $car['seats'] }}</div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('ui.rent_seats') }}</div>
                        </div>
                        <div class="p-8 bg-slate-50 rounded-[32px] border border-slate-100">
                            <x-icon-gauge class="text-brand-600 mb-4 w-6 h-6"/>
                            <div class="text-lg font-black text-slate-900">{{ $car['year'] }}</div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ __('ui.rent_year') }}</div>
                        </div>
                    </div>

                    <div class="p-10 bg-slate-900 rounded-[40px] text-white">
                        <h3 class="text-2xl font-black mb-10 flex items-center gap-3 tracking-tight">
                            <x-icon-shield class="text-brand-600 w-6 h-6"/>
                            {{ __('ui.rent_included_title') }}
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-6">
                            @foreach(['rent_detail_included_1', 'rent_detail_included_2', 'rent_detail_included_3', 'rent_detail_included_4'] as $key)
                                <div class="flex items-center gap-4">
                                    <x-icon-check-circle class="text-brand-600 w-5 h-5"/>
                                    <span class="text-base font-bold text-slate-300">{{ __('ui.' . $key) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4">
                <div class="sticky top-32 space-y-8">
                    <div class="bg-white rounded-[40px] p-10 border border-slate-100 shadow-2xl shadow-slate-100">
                        <h3 class="font-black text-2xl text-slate-900 mb-10 tracking-tight">{{ __('ui.rent_rent_vehicle') }}</h3>

                        <form action="{{ $applyUrl }}" method="GET" class="space-y-6">
                            <input type="hidden" name="rent_car_id" value="{{ $car['id'] }}" />
                            <div class="space-y-4">
                                <div>
                                    <label for="atd" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">{{ __('ui.rent_atd_label') }}</label>
                                    <input id="atd" type="text" name="atd" class="w-full px-5 py-4 bg-slate-50 border-2 border-transparent focus:border-brand-600 focus:ring-0 rounded-2xl outline-none font-black text-lg transition-colors placeholder:text-slate-400" placeholder="{{ __('ui.rent_atd_placeholder') }}" />
                                </div>
                                <div>
                                    <label for="phone" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">{{ __('ui.rent_phone_label') }}</label>
                                    <input id="phone" type="tel" name="phone" class="w-full px-5 py-4 bg-slate-50 border-2 border-transparent focus:border-brand-600 focus:ring-0 rounded-2xl outline-none font-black text-lg transition-colors placeholder:text-slate-400" placeholder="{{ __('ui.rent_phone_placeholder') }}" />
                                </div>
                            </div>
                            <button type="submit" class="inline-flex w-full items-center justify-center px-10 py-5 bg-brand-600 text-white font-bold tracking-tight h-[4.5rem] text-xl rounded-2xl shadow-xl shadow-brand-600/20 hover:bg-brand-700 transition-all active:scale-95 border border-transparent">
                                {{ __('ui.rent_send_request') }}
                                <x-icon-arrow-right class="w-5 h-5 shrink-0 ml-2"/>
                            </button>
                        </form>

                        <div class="mt-12 pt-8 border-t border-slate-100 space-y-4">
                            <div class="flex justify-between text-sm font-bold">
                                <span class="text-slate-400 uppercase tracking-widest text-[11px]">{{ __('ui.rent_weekly_rent') }}</span>
                                <span class="text-slate-900">€{{ $car['price'] }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold">
                                <span class="text-slate-400 uppercase tracking-widest text-[11px]">{{ __('ui.rent_deposit_label') }}</span>
                                <span class="text-slate-900">€{{ $car['deposit'] }}</span>
                            </div>
                            <div class="flex justify-between items-center pt-4">
                                <span class="font-black text-slate-900 uppercase tracking-tighter text-lg">{{ __('ui.rent_total_upfront') }}</span>
                                <span class="text-3xl font-black text-brand-600 tracking-tighter">€{{ $car['price'] + $car['deposit'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 p-8 rounded-3xl border border-slate-100 flex gap-6">
                        <x-icon-info class="text-slate-400 shrink-0 w-7 h-7"/>
                        <p class="text-xs text-slate-400 leading-relaxed font-bold uppercase tracking-widest">
                            {{ __('ui.rent_energy_note') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
