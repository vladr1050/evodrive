@extends('layouts.apply')

@section('title', __('apply.done'))

@section('content')
<div class="min-h-screen bg-white flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-[40px] p-12 text-center space-y-8 shadow-2xl shadow-slate-200/50 border border-slate-100">
        <div class="w-20 h-20 bg-emerald-500 rounded-[28px] flex items-center justify-center text-white mx-auto shadow-xl shadow-emerald-500/20 rotate-12">
            <svg class="w-10 h-10 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="space-y-4">
            <h1 class="text-4xl font-black text-slate-900 tracking-tighter">{{ __('apply.done') }}</h1>
            <p class="text-slate-500 text-lg leading-relaxed font-medium">
                {{ __('apply.thanks_before') }}<span class="text-slate-900 font-bold">{{ e($phone ?? '') }}</span>{{ __('apply.thanks_after') }}
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="{{ url('/' . app()->getLocale()) }}"
               class="flex-1 inline-flex items-center justify-center bg-brand-600 text-white font-bold py-5 rounded-2xl text-lg min-h-[64px] hover:bg-brand-700 transition-all active:scale-95 shadow-lg shadow-brand-600/10">
                {{ __('apply.return_home') }}
            </a>
            <a href="{{ route('landing.faq', ['locale' => app()->getLocale()]) }}"
               class="flex-1 inline-flex items-center justify-center bg-slate-100 text-slate-900 font-bold py-5 rounded-2xl text-lg min-h-[64px] hover:bg-slate-200 transition-all active:scale-95">
                {{ __('ui.faq_title') }}
            </a>
        </div>
    </div>
</div>
@endsection
