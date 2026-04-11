@extends('layouts.apply')

@section('title', __('apply.step_phone_title'))

@push('scripts')
@vite(['resources/js/apply-wizard.js'])
@endpush

@section('content')
<style>
    @keyframes apply-phone-autofill { from { opacity: 1; } to { opacity: 1; } }
    input[name="phone"]:-webkit-autofill {
        animation-name: apply-phone-autofill;
        animation-duration: 0.001s;
    }
</style>
<div class="min-h-screen flex flex-col items-center justify-center p-4 md:p-8">
        <div class="w-full max-w-xl bg-white rounded-[48px] shadow-xl shadow-slate-200/50 border border-slate-200 overflow-hidden flex flex-col relative">
        <form id="apply-form" action="{{ route('apply.submit', ['locale' => app()->getLocale()]) }}" method="POST" class="flex flex-col">
            @csrf
            <input type="hidden" name="step" id="step-input" value="1">
            <input type="text" name="website_url" class="absolute -left-[9999px]" tabindex="-1" autocomplete="off">
            @foreach(['utm_source','utm_campaign','utm_medium','utm_content','utm_term'] as $utm)
                <input type="hidden" name="{{ $utm }}" value="{{ request($utm) }}">
            @endforeach
            @if(!empty($sessionData['rent_car_id']))
                <input type="hidden" name="rent_car_id" value="{{ $sessionData['rent_car_id'] }}">
            @endif
            <input type="hidden" name="intent" value="{{ $sessionData['intent'] ?? '' }}" id="intent-input">
            @php
                $applyStartFlowIndex = (! empty($sessionData['intent']) && $sessionData['intent'] === 'rent' && ! empty($sessionData['phone'])) ? 2 : 0;
            @endphp
            <input type="hidden" name="apply_start_flow_index" id="apply-start-flow-index" value="{{ $applyStartFlowIndex }}">
            <input type="hidden" name="atd_license" value="">
            <input type="hidden" name="driving_experience" value="">
            <input type="hidden" name="latvian_b1" value="">
            <input type="hidden" name="shift_preference" value="">

            {{-- Header --}}
            <div class="px-8 pt-10 pb-6 flex items-center justify-between">
                <div class="w-10 flex justify-start flex-shrink-0">
                    <button type="button" id="prev-btn" class="hidden p-2 -ml-2 text-slate-400 hover:text-slate-900 hover:bg-slate-50 rounded-full transition-all active:scale-90">
                        <x-icon-chevron-left class="w-6 h-6" />
                    </button>
                    <a href="{{ url('/' . app()->getLocale() . '/g') }}" id="logo-btn" class="block p-2 -ml-2 text-slate-400 hover:text-slate-900 hover:bg-slate-50 rounded-full transition-all active:scale-90">
                        <div class="w-6 h-6 bg-black rounded-[22%] flex items-center justify-center overflow-hidden shrink-0">
                            <img src="{{ asset('images/logo.png') }}" alt="Evo.drive" class="w-full h-full object-contain">
                        </div>
                    </a>
                </div>
                <div class="flex flex-col items-center gap-1.5 flex-shrink-0">
                    <span id="step-label" class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em]" data-template="{{ __('apply.step_of') }}">{{ __('apply.step_of', ['current' => 1, 'total' => 7]) }}</span>
                    <div class="h-1.5 w-24 bg-slate-100 rounded-full overflow-hidden">
                        <div id="progress-bar" class="h-full bg-brand-600 transition-all duration-500 ease-out" style="width: 14%;"></div>
                    </div>
                </div>
                <div class="w-10 flex justify-end flex-shrink-0">
                    <a href="{{ url('/' . app()->getLocale() . '/g') }}" class="p-2 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-full transition-all active:scale-90" aria-label="{{ __('apply.close') }}">
                        <x-icon-x class="w-5 h-5" />
                    </a>
                </div>
            </div>

            {{-- Content --}}
            <div class="px-8 pb-12 pt-4 flex-grow">
                <div data-flow-panel="phone" class="space-y-8">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tighter">{{ __('apply.step_phone_title') }}</h1>
                        <p class="text-slate-400 font-medium">{{ __('apply.step_phone_sub') }}</p>
                    </div>
                    <div class="relative group">
                        <div class="absolute left-6 top-1/2 -translate-y-1/2 font-black text-slate-400 text-xl group-focus-within:text-brand-600 transition-colors">+371</div>
                        <input type="tel" name="phone" inputmode="numeric" autocomplete="tel-national" autocorrect="off" spellcheck="false" data-testid="apply-phone" required minlength="8" maxlength="15" autofocus
                               class="w-full pl-24 pr-6 py-6 bg-slate-50 border-2 border-slate-200 focus:border-brand-600 focus:bg-white rounded-[24px] outline-none text-2xl font-black transition-all shadow-sm focus:shadow-xl focus:shadow-brand-600/5"
                               placeholder="2XXXXXXX" value="{{ old('phone', $sessionData['phone'] ?? '') }}">
                    </div>
                    <button type="button" id="next-btn" data-testid="apply-next" aria-describedby="apply-phone-error" class="w-full inline-flex items-center justify-center bg-brand-600 text-white font-bold py-5 rounded-2xl text-lg min-h-[72px] hover:bg-brand-700 transition-all shadow-lg shadow-brand-600/20 opacity-60">{{ __('apply.next_step') }}</button>
                    <p id="apply-phone-error" class="hidden text-center text-sm font-bold text-red-600" role="alert">{{ __('apply.phone_too_short') }}</p>
                </div>

                <div data-flow-panel="path" class="hidden space-y-8">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tighter">{{ __('apply.step_path_title') }}</h1>
                        <p class="text-slate-400 font-medium">{{ __('apply.step_path_sub') }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4">
                        <button type="button" data-goto data-intent="work" data-testid="apply-intent-work" class="p-6 text-left rounded-[28px] border-2 border-slate-50 bg-slate-50 hover:bg-slate-100 transition-all flex items-center gap-5">
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-white text-slate-400">
                                <x-icon-briefcase class="w-7 h-7" />
                            </div>
                            <div>
                                <div class="text-xl font-black text-slate-900">{{ __('apply.path_company_fleet') }}</div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.15em]">{{ __('apply.path_company_fleet_sub') }}</div>
                            </div>
                        </button>
                        <button type="button" data-goto data-intent="rent" data-testid="apply-intent-rent" class="p-6 text-left rounded-[28px] border-2 border-slate-50 bg-slate-50 hover:bg-slate-100 transition-all flex items-center gap-5">
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center bg-white text-slate-400">
                                <x-icon-car class="w-7 h-7" />
                            </div>
                            <div>
                                <div class="text-xl font-black text-slate-900">{{ __('apply.path_taxi_rental') }}</div>
                                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.15em]">{{ __('apply.path_taxi_rental_sub') }}</div>
                            </div>
                        </button>
                    </div>
                </div>

                <div data-flow-panel="qual" class="hidden space-y-8">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tighter">{{ __('apply.step_qual_title') }}</h1>
                        <p class="text-slate-400 font-medium">{{ __('apply.step_qual_sub') }}</p>
                    </div>
                    <div class="space-y-8">
                        <div class="space-y-4">
                            <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <x-icon-check class="w-[14px] h-[14px] text-brand-600" /> {{ __('apply.atd_question') }}
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" data-atd="yes" class="py-4 text-lg font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">{{ __('apply.yes') }}</button>
                                <button type="button" data-atd="no" class="py-4 text-lg font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">{{ __('apply.no') }}</button>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <label for="atd-number-input" class="text-[11px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <x-icon-check class="w-[14px] h-[14px] text-brand-600" /> {{ __('apply.atd_card_number_label') }}
                            </label>
                            <input type="text" name="atd_number" id="atd-number-input" data-testid="apply-atd-number" maxlength="50"
                                   class="w-full px-6 py-5 bg-slate-50 border-2 border-slate-200 focus:border-brand-600 focus:bg-white rounded-[24px] outline-none text-xl font-black transition-all shadow-sm"
                                   placeholder="{{ __('apply.atd_card_number_placeholder') }}"
                                   value="{{ old('atd_number', $sessionData['atd_number'] ?? '') }}"
                                   autocomplete="off">
                            <p class="text-xs text-slate-400 font-medium">{{ __('apply.atd_card_number_hint') }}</p>
                        </div>
                        <div class="space-y-4">
                            <div class="text-[11px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <x-icon-check class="w-[14px] h-[14px] text-brand-600" /> {{ __('apply.driving_experience') }}
                            </div>
                            <div class="grid grid-cols-3 gap-3">
                                <button type="button" data-exp="3-5" class="py-4 font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">3-5</button>
                                <button type="button" data-exp="5-10" class="py-4 font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">5-10</button>
                                <button type="button" data-exp="10+" class="py-4 font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">10+</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="next-btn-qual" data-testid="apply-next-qual" class="w-full inline-flex items-center justify-center bg-brand-600 text-white font-bold py-5 rounded-2xl text-lg min-h-[72px] hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all" disabled>{{ __('apply.continue') }}</button>
                </div>

                <div data-flow-panel="latvian" class="hidden space-y-8">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tighter">{{ __('apply.step_latvian_title') }}</h1>
                        <p class="text-slate-400 font-medium">{{ __('apply.step_latvian_sub') }}</p>
                    </div>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" data-latvian="yes" class="py-4 text-lg font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">{{ __('apply.yes') }}</button>
                            <button type="button" data-latvian="no" class="py-4 text-lg font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">{{ __('apply.no') }}</button>
                        </div>
                    </div>
                    <button type="button" id="next-btn-latvian" data-testid="apply-next-latvian" class="w-full inline-flex items-center justify-center bg-brand-600 text-white font-bold py-5 rounded-2xl text-lg min-h-[72px] hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all" disabled>{{ __('apply.continue') }}</button>
                </div>

                <div data-flow-panel="shifts" class="hidden space-y-8">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tighter">{{ __('apply.step_shifts_title') }}</h1>
                        <p class="text-slate-400 font-medium">{{ __('apply.step_shifts_sub') }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-3">
                        <button type="button" data-shift="early_day" class="py-4 px-4 text-left text-base font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">{{ __('apply.shift_early_day') }}</button>
                        <button type="button" data-shift="late_night" class="py-4 px-4 text-left text-base font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">{{ __('apply.shift_late_night') }}</button>
                        <button type="button" data-shift="mixed" class="py-4 px-4 text-left text-base font-black rounded-2xl border-2 border-slate-50 bg-slate-50 text-slate-400 transition-all">{{ __('apply.shift_mixed') }}</button>
                    </div>
                    <button type="button" id="next-btn-shifts" data-testid="apply-next-shifts" class="w-full inline-flex items-center justify-center bg-brand-600 text-white font-bold py-5 rounded-2xl text-lg min-h-[72px] hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all" disabled>{{ __('apply.continue') }}</button>
                </div>

                <div data-flow-panel="details" class="hidden space-y-8">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tighter">{{ __('apply.step_details_title') }}</h1>
                        <p class="text-slate-400 font-medium">{{ __('apply.step_details_sub') }}</p>
                    </div>
                    <div class="space-y-5">
                        <div class="relative group">
                            <x-icon-user class="absolute left-6 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-300 group-focus-within:text-brand-600 transition-colors" />
                            <input type="text" name="name" data-testid="apply-name" required
                                   class="w-full pl-16 pr-6 py-5 bg-slate-50 border-2 border-transparent focus:border-brand-600 focus:bg-white rounded-[24px] outline-none text-xl font-black transition-all shadow-sm"
                                   placeholder="{{ __('apply.name_placeholder') }}" value="{{ old('name', $sessionData['name'] ?? '') }}">
                        </div>
                        <div class="relative group">
                            <svg class="absolute left-6 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-300 group-focus-within:text-brand-600 transition-colors" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                            <input type="email" name="email" data-testid="apply-email" required
                                   class="w-full pl-16 pr-6 py-5 bg-slate-50 border-2 border-transparent focus:border-brand-600 focus:bg-white rounded-[24px] outline-none text-xl font-black transition-all shadow-sm"
                                   placeholder="{{ __('apply.email_placeholder') }}" value="{{ old('email', $sessionData['email'] ?? '') }}"
                                   autocomplete="email">
                        </div>
                        <div class="relative group">
                            <x-icon-map-pin class="absolute left-6 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-300 group-focus-within:text-brand-600 transition-colors" />
                            <input type="text" name="area" data-testid="apply-area" required
                                   class="w-full pl-16 pr-6 py-5 bg-slate-50 border-2 border-transparent focus:border-brand-600 focus:bg-white rounded-[24px] outline-none text-xl font-black transition-all shadow-sm"
                                   placeholder="{{ __('apply.area_placeholder') }}" value="{{ old('area', $sessionData['area'] ?? '') }}">
                        </div>
                    </div>
                    <button type="button" id="next-btn-details" data-testid="apply-next-details" class="w-full inline-flex items-center justify-center bg-brand-600 text-white font-bold py-5 rounded-2xl text-lg min-h-[72px] hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all" disabled>{{ __('apply.final_step') }}</button>
                </div>

                <div data-flow-panel="ready" class="hidden space-y-10 text-center py-6">
                    <div class="w-24 h-24 bg-brand-50 text-brand-600 rounded-[32px] flex items-center justify-center mx-auto shadow-xl shadow-brand-600/5 rotate-3">
                        <x-icon-shield class="w-12 h-12" />
                    </div>
                    <div class="space-y-4">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tighter">{{ __('apply.step_ready_title') }}</h1>
                        <p class="text-slate-500 text-lg font-medium leading-relaxed max-w-sm mx-auto">{{ __('apply.step_ready_sub') }}</p>
                    </div>
                    <div class="pt-2">
                        <button type="button" id="submit-btn" data-testid="apply-submit" class="w-full inline-flex items-center justify-center bg-brand-600 text-white font-bold py-6 rounded-2xl text-xl min-h-[80px] shadow-2xl shadow-brand-600/20 hover:bg-brand-700 transition-all active:scale-95">{{ __('apply.submit_button') }}</button>
                        <p class="mt-6 text-[10px] font-bold text-slate-300 uppercase tracking-widest">{{ __('apply.submit_terms') }}</p>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Footer --}}
    <div class="mt-8 flex items-center gap-4 text-slate-400">
        <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em]">
            <x-icon-shield class="w-[14px] h-[14px] text-brand-600" /> {{ __('apply.secured_app') }}
        </div>
        <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
        <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.2em]">
            <svg class="w-[14px] h-[14px] text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>
            {{ __('apply.support_371') }}
        </div>
    </div>
</div>
@endsection
