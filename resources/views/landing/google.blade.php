@extends('layouts.app')

@section('title', $page?->getTranslated('meta_title') ?? __('ui.emp_hero_h1_1') . ' ' . __('ui.emp_hero_h1_2') . ' — EvoDrive.lv')

@push('scripts')
    @if($page && $page->key === 'google_landing')
        @include('components.job-posting-schema')
    @endif
@endpush

@section('content')
<div class="bg-white min-h-screen pb-20">
    @php
        $locale = app()->getLocale();
        $heroContent = ($sections['hero'] ?? null)?->getContentForLocale() ?? [];
        $heroImg = $heroContent['hero_image'] ?? 'https://images.unsplash.com/photo-1560958089-b8a1929cea89?auto=format&fit=crop&w=1200&q=80';
    @endphp

    {{-- Hero Section --}}
    <section class="pt-16 pb-20 md:pt-24 md:pb-32 px-4 max-w-7xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div>
                <span class="inline-block bg-brand-50 text-brand-600 text-[10px] font-black uppercase tracking-[0.2em] px-3 py-1.5 rounded-full mb-8">{{ __('ui.emp_badge') }}</span>
                <h1 class="text-5xl md:text-7xl font-black text-slate-900 mb-8 leading-[0.9] tracking-tighter">
                    {{ __('ui.emp_hero_h1_1') }}<br/>
                    <span class="text-brand-600">{{ __('ui.emp_hero_h1_2') }}</span>
                </h1>
                <p class="text-xl text-slate-500 mb-12 max-w-md font-medium leading-relaxed">
                    {{ __('ui.emp_hero_sub') }}
                </p>
                <a href="{{ $applyUrl }}"
                   class="inline-flex items-center justify-center bg-brand-600 text-white font-bold h-16 px-12 text-lg rounded-2xl shadow-2xl shadow-brand-600/30 hover:bg-brand-700 transition-all active:scale-95 w-full sm:w-auto">
                    {{ __('ui.apply_2_min') }}
                </a>
            </div>
            <div class="relative">
                <img src="{{ $heroImg }}" alt="Fleet" loading="lazy"
                     class="w-full h-[500px] object-cover rounded-[40px] shadow-2xl grayscale-[0.1]">
            </div>
        </div>
    </section>

    {{-- Process Section --}}
    <section class="py-24 bg-slate-50 border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-black text-slate-900 mb-16 tracking-tight">{{ __('ui.emp_how_it_works') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach([['n' => '01', 't' => 'emp_step_1_t', 'd' => 'emp_step_1_d'], ['n' => '02', 't' => 'emp_step_2_t', 'd' => 'emp_step_2_d'], ['n' => '03', 't' => 'emp_step_3_t', 'd' => 'emp_step_3_d']] as $step)
                    <div class="p-10 bg-white border border-slate-100 rounded-[32px] text-left">
                        <div class="text-4xl font-black text-slate-100 mb-6">{{ $step['n'] }}</div>
                        <h4 class="text-xl font-black text-slate-900 mb-2">{{ __('ui.' . $step['t']) }}</h4>
                        <p class="text-sm font-medium text-slate-400 leading-relaxed">{{ __('ui.' . $step['d']) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Income Block --}}
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4">
            <div class="bg-slate-900 rounded-[48px] p-12 md:p-20 text-white overflow-hidden relative">
                <div class="absolute top-0 right-0 w-1/3 h-full bg-brand-600 opacity-20 blur-[120px] -z-0"></div>
                <div class="relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    <div>
                        <h2 class="text-4xl font-black mb-8 tracking-tighter">{{ __('ui.emp_projected_earnings') }}</h2>
                        <p class="text-slate-400 text-lg mb-12">{{ __('ui.emp_earnings_desc') }}</p>
                        <div class="space-y-6">
                            <div class="flex justify-between items-center py-4 border-b border-slate-800">
                                <span class="text-slate-400 font-bold">{{ __('ui.emp_avg_hour_rate') }}</span>
                                <span class="text-2xl font-black">{{ __('ui.emp_hour_rate_val') }}</span>
                            </div>
                            <div class="flex justify-between items-center py-4 border-b border-slate-800">
                                <span class="text-slate-400 font-bold">{{ __('ui.emp_weekly_net') }}</span>
                                <span class="text-4xl font-black text-brand-600">{{ __('ui.emp_weekly_net_val') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach([['icon' => 'wallet', 't' => 'emp_benefit_1_t', 'd' => 'emp_benefit_1_d'], ['icon' => 'shield', 't' => 'emp_benefit_2_t', 'd' => 'emp_benefit_2_d'], ['icon' => 'wrench', 't' => 'emp_benefit_3_t', 'd' => 'emp_benefit_3_d'], ['icon' => 'car', 't' => 'emp_benefit_4_t', 'd' => 'emp_benefit_4_d']] as $item)
                            <div class="p-6 bg-white/5 rounded-3xl border border-white/10">
                                @if($item['icon'] === 'wallet')
                                    <x-icon-wallet class="text-brand-600 mb-4 w-6 h-6" />
                                @elseif($item['icon'] === 'shield')
                                    <x-icon-shield class="text-brand-600 mb-4 w-6 h-6" />
                                @elseif($item['icon'] === 'wrench')
                                    <x-icon-wrench class="text-brand-600 mb-4 w-6 h-6" />
                                @else
                                    <x-icon-car class="text-brand-600 mb-4 w-6 h-6" />
                                @endif
                                <div class="font-bold text-sm mb-1">{{ __('ui.' . $item['t']) }}</div>
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{{ __('ui.' . $item['d']) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="py-32 text-center">
        <div class="max-w-xl mx-auto px-4">
            <h2 class="text-4xl font-black text-slate-900 mb-8 tracking-tighter">{{ __('ui.emp_final_cta') }}</h2>
            <a href="{{ $applyUrl }}"
               class="inline-flex items-center justify-center w-full bg-brand-600 text-white font-bold h-20 text-xl rounded-2xl shadow-2xl shadow-brand-600/30 hover:bg-brand-700 transition-all active:scale-95">
                {{ __('ui.apply_now') }}
                <x-icon-arrow-right class="w-6 h-6 ml-3 shrink-0"/>
            </a>
        </div>
    </section>
</div>
@endsection
