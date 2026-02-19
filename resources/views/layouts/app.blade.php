<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    @include('layouts.seo')

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; scroll-behavior: smooth; }
        .sticky-cta-shadow { box-shadow: 0 -4px 20px -5px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="w-full overflow-x-hidden bg-white text-slate-900 selection:bg-brand-600 selection:text-white min-h-screen flex flex-col">
    @if(!isset($hideNav) || !$hideNav)
        @include('components.navbar')
    @endif

    <main class="flex-grow">
        @yield('content')
    </main>

    @if(!isset($hideFooter) || !$hideFooter)
        @include('components.footer')
    @endif

    @if(!isset($hideStickyCta) || !$hideStickyCta)
        <div class="fixed bottom-0 left-0 right-0 p-4 bg-white/80 backdrop-blur-md border-t border-slate-100 sticky-cta-shadow z-50">
            <a href="{{ $applyUrl }}"
               class="flex items-center justify-center w-full bg-brand-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-brand-600/20 active:scale-95 transition-transform min-h-[56px]">
                {{ __('ui.apply_in_2_minutes') }}
            </a>
        </div>
        <div class="h-24"></div>
    @endif

    @stack('scripts')
</body>
</html>
