@php
    $locale = in_array(request()->segment(1), ['en', 'ru', 'lv']) ? request()->segment(1) : (app()->getLocale() ?: 'en');
    app()->setLocale($locale);
@endphp
@extends('layouts.app')

@section('title', __('ui.error_404_title') . ' — EvoDrive.lv')

@section('content')
<div class="min-h-[60vh] flex flex-col items-center justify-center px-4 py-20">
    <div class="text-center max-w-md">
        <div class="text-8xl font-black text-slate-200 mb-6">404</div>
        <h1 class="text-3xl font-black text-slate-900 mb-4 tracking-tight">{{ __('ui.error_404_title') }}</h1>
        <p class="text-slate-500 font-medium mb-10">
            {{ __('ui.error_404_message') }}
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('home', ['locale' => $locale]) }}"
               class="inline-flex items-center justify-center bg-brand-600 text-white font-bold py-4 px-8 rounded-xl hover:bg-brand-700 transition-all">
                {{ __('ui.error_404_go_home') }}
            </a>
            <a href="{{ $applyUrl ?? route('apply.show', ['locale' => $locale]) }}"
               class="inline-flex items-center justify-center bg-slate-100 text-slate-900 font-bold py-4 px-8 rounded-xl hover:bg-slate-200 transition-all">
                {{ __('ui.start_application') }}
            </a>
        </div>
    </div>
</div>
@endsection
