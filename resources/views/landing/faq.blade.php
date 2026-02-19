@extends('layouts.app')

@push('scripts')
    @include('components.faq-page-schema', ['categories' => $categories])
@endpush

@section('title', __('ui.faq_title') . ' — EvoDrive.lv')

@section('content')
@php
    $locale = app()->getLocale();
    $defaultSlug = $categories->contains('slug', 'general') ? 'general' : ($categories->first()?->slug ?? 'general');
@endphp
<style>
.faq-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; }
</style>
<div class="min-h-screen bg-white py-20 px-4 font-sans text-slate-900" id="faq-page" data-default-category="{{ $defaultSlug }}">
    <div class="mx-auto max-w-3xl">
        {{-- Support Badge --}}
        <div class="mb-6 flex justify-center">
            <span class="inline-flex rounded-full border border-slate-200 px-3 py-1 text-[10px] font-bold tracking-widest text-slate-500 uppercase">
                {{ __('ui.faq_support_center') }}
            </span>
        </div>

        {{-- Header --}}
        <div class="mb-10 text-center">
            <h1 class="mb-4 text-4xl font-extrabold tracking-tight sm:text-5xl">
                {{ __('ui.faq_hero_help_prefix') }}<span class="text-brand-600">{{ __('ui.faq_hero_help_highlight') }}</span>
            </h1>
            <p class="mx-auto max-w-[520px] text-lg font-medium text-slate-500">
                {{ __('ui.faq_hero_sub') }}
            </p>
        </div>

        {{-- Search Input --}}
        <div class="relative mx-auto mb-12 max-w-xl">
            <div class="pointer-events-none absolute inset-y-0 left-4 flex items-center">
                <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input type="text" id="faq-search" placeholder="{{ __('ui.faq_search_placeholder') }}"
                class="h-11 w-full rounded-[14px] border border-slate-200 bg-slate-50 pl-11 pr-4 text-sm font-medium text-slate-900 outline-none transition-all placeholder:text-slate-400 focus:border-brand-600 focus:bg-white">
        </div>

        {{-- Category Tabs --}}
        <nav class="mb-12 flex flex-wrap justify-center gap-[10px]" id="faq-tabs" aria-label="{{ __('ui.faq_categories') }}">
            @foreach($categories as $cat)
                <button type="button"
                    data-category="{{ $cat->slug }}"
                    class="faq-tab inline-flex h-9 items-center rounded-[12px] border px-5 text-xs font-bold transition-all {{ $cat->slug === $defaultSlug ? 'active bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300 hover:text-slate-800' }}">
                    {{ $cat->getTranslatedTitle() }}
                </button>
            @endforeach
        </nav>

        {{-- FAQ Accordion List --}}
        <div class="mb-20 space-y-3" id="faq-list">
            @foreach($categories as $cat)
                @foreach($cat->items as $item)
                    @php
                        $q = $item->getTranslatedQuestion();
                        $a = $item->getTranslatedAnswer();
                    @endphp
                    @if($q && $a)
                        <div class="faq-item bg-white border border-slate-100 rounded-2xl transition-all duration-300 overflow-hidden hover:border-slate-200"
                            data-category="{{ $cat->slug }}">
                            <button type="button" class="faq-trigger w-full px-6 py-5 text-left flex items-center justify-between gap-4 group">
                                <span class="faq-question text-base font-bold transition-colors text-slate-600 group-hover:text-slate-900">{{ $q }}</span>
                                <div class="faq-chevron flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center transition-all bg-slate-50 text-slate-300 group-hover:text-slate-400">
                                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </button>
                            <div class="faq-content overflow-hidden transition-all duration-300 ease-in-out max-h-0">
                                <div class="px-6 pt-2 pb-6 text-slate-500 text-sm leading-relaxed font-medium">
                                    <div class="h-px w-full bg-slate-50 mb-4"></div>
                                    {{ $a }}
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endforeach
        </div>

        {{-- CTA Card (dark theme) --}}
        <section class="py-8">
            <div class="max-w-3xl mx-auto bg-slate-900 rounded-[40px] p-10 md:p-14 text-center">
                <div class="flex flex-col items-center">
                    <div class="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center text-brand-600 mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    </div>
                    <h2 class="text-2xl md:text-3xl font-black text-white tracking-tight mb-3">{{ __('ui.faq_still_questions') }}</h2>
                    <p class="text-slate-400 text-sm font-medium max-w-md mx-auto mb-10 leading-relaxed">
                        {{ __('ui.faq_still_sub_compact') }}
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 w-full justify-center">
                        <a href="{{ $applyUrl }}" class="inline-flex h-12 items-center justify-center rounded-xl bg-brand-600 px-8 text-xs font-bold text-white hover:bg-brand-700 transition-all w-full sm:w-auto">
                            {{ __('ui.start_application') }}
                        </a>
                        <a href="mailto:support@evodrive.lv" class="inline-flex h-12 items-center justify-center rounded-xl border border-white/10 px-8 text-xs font-bold text-white hover:bg-white/5 transition-all w-full sm:w-auto">
                            {{ __('ui.faq_chat_support') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- Bottom Subtle Badge --}}
        <div class="pb-16 text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-100 rounded-full text-[9px] font-black text-slate-300 uppercase tracking-[0.3em]">
                <x-icon-shield class="w-3.5 h-3.5 text-brand-600" /> {{ __('ui.faq_footer_badge') }}
            </div>
        </div>
    </div>
</div>

@push('scripts')
@vite(['resources/js/faq.js'])
@endpush
@endsection
