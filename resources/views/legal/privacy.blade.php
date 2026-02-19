@extends('layouts.app')

@section('title', __('ui.legal_privacy_title') . ' — EvoDrive.lv')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-20">
    <h1 class="text-4xl font-black text-slate-900 mb-8 tracking-tight">{{ __('ui.legal_privacy_title') }}</h1>
    <div class="prose prose-slate max-w-none text-slate-600">
        <p>{{ __('ui.legal_placeholder') }}</p>
    </div>
    <p class="mt-12">
        <a href="{{ route('home', ['locale' => app()->getLocale()]) }}" class="text-brand-600 font-bold hover:underline">{{ __('ui.error_404_go_home') }}</a>
    </p>
</div>
@endsection
