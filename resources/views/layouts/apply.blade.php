<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('apply.personal_info')) — EvoDrive.lv</title>
    @php
        $siteFavicon = \App\Models\SiteSetting::first()?->favicon_path;
        $faviconUrl = $siteFavicon ? asset(\Illuminate\Support\Facades\Storage::url($siteFavicon)) : asset('images/logo.png');
    @endphp
    <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
    @include('layouts.seo')
    @include('components.analytics')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body { font-family: 'Inter', sans-serif; -webkit-font-smoothing: antialiased; }</style>
</head>
<body class="bg-white text-slate-900 min-h-screen flex flex-col">
    @yield('content')
    @stack('scripts')
</body>
</html>
