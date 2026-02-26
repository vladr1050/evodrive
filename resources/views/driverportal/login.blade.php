@extends('driverportal.layouts.guest')

@section('title', __('portal.title'))

@section('content')
<div class="min-h-screen bg-slate-50 flex flex-col items-center justify-center p-4 py-8">
    <div class="w-full max-w-md">
        {{-- Header Section --}}
        <div class="text-center mb-10 animate-fade-in">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-brand-600 rounded-[2.5rem] text-white font-bold text-4xl shadow-brand-2xl mb-6">E</div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ __('portal.title') }}</h1>
            <p class="text-slate-500 mt-2 font-medium">{{ __('portal.operational_access') }}</p>
        </div>

        {{-- Login Card --}}
        <div class="bg-white p-10 rounded-[2.5rem] shadow-xl shadow-slate-200/50 border border-slate-100 animate-zoom-in">
            <form method="POST" action="{{ route('driverportal.login.submit', ['locale' => app()->getLocale()]) }}" class="space-y-6">
                @csrf
                {{-- Email Field --}}
                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2.5 ml-1">{{ __('portal.email_address') }}</label>
                    <div class="relative group">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        </span>
                        <input id="email" name="email" type="email" data-testid="driverportal-email" value="{{ old('email') }}" required placeholder="driver@evodrive.lv" class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-brand-600/10 focus:border-brand-600 outline-none transition-all font-bold text-slate-700 placeholder:text-slate-300">
                    </div>
                    @error('email')
                        <p class="text-red-500 text-xs mt-1 ml-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password Field --}}
                <div>
                    <label for="password" class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-2.5 ml-1">{{ __('portal.password') }}</label>
                    <div class="relative group">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-brand-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input id="password" name="password" type="password" data-testid="driverportal-password" required placeholder="••••••••" class="w-full pl-12 pr-4 py-4 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-4 focus:ring-brand-600/10 focus:border-brand-600 outline-none transition-all font-bold text-slate-700 placeholder:text-slate-300">
                    </div>
                    @error('password')
                        <p class="text-red-500 text-xs mt-1 ml-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember Me & Forgot Password --}}
                <div class="flex items-center justify-between px-1">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <div class="relative w-5 h-5">
                            <input type="checkbox" name="remember" value="1" class="peer absolute opacity-0 w-full h-full cursor-pointer">
                            <div class="absolute inset-0 w-5 h-5 border-2 border-slate-200 rounded-lg peer-checked:bg-brand-600 peer-checked:border-brand-600 transition-all pointer-events-none"></div>
                            <div class="absolute inset-0 flex items-center justify-center text-white opacity-0 peer-checked:opacity-100 transition-opacity pointer-events-none">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                        <span class="text-sm text-slate-500 font-bold group-hover:text-slate-700 transition-colors">{{ __('portal.remember_me') }}</span>
                    </label>
                    <a href="#" class="text-sm text-brand-600 font-bold hover:underline">{{ __('portal.forgot_password') }}</a>
                </div>

                {{-- Error Message --}}
                @if(session('driverportal.error'))
                    <div class="flex items-center gap-2 p-4 bg-red-50 text-red-600 rounded-2xl text-sm font-bold animate-slide-in-top">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                        <span>{{ session('driverportal.error') }}</span>
                    </div>
                @endif

                {{-- Submit Button --}}
                <button type="submit" data-testid="driverportal-login-submit" class="w-full bg-brand-600 text-white font-bold py-5 rounded-2xl shadow-xl shadow-brand-600/30 hover:bg-brand-700 active:scale-[0.98] transition-all text-lg">
                    {{ __('portal.login_to_portal') }}
                </button>
            </form>
        </div>

        {{-- Footer Link --}}
        <div class="mt-10 text-center space-y-4 animate-fade-in" style="animation-delay: 0.2s;">
            <a href="{{ url(app()->getLocale()) }}" class="inline-flex items-center gap-2 text-slate-400 hover:text-slate-900 font-bold text-sm transition-all group">
                <svg class="shrink-0 group-hover:-translate-x-1 transition-transform" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                {{ __('portal.back_to_website') }}
            </a>
            @include('driverportal.components.lang-switcher')
        </div>
    </div>
</div>
@endsection
