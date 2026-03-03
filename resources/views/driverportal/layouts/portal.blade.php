@php
    $locale = app()->getLocale();
    $seg = request()->segment(3) ?? '';
    $navItems = [
        ['route' => 'driverportal.dashboard', 'segment' => 'dashboard', 'label' => __('portal.dashboard'), 'icon' => 'dashboard'],
        ['route' => 'driverportal.shifts', 'segment' => 'shifts', 'label' => __('portal.shifts'), 'icon' => 'shifts'],
        ['route' => 'driverportal.profile', 'segment' => 'profile', 'label' => __('portal.profile'), 'icon' => 'profile'],
    ];
    $driver = auth()->guard('driver')->user();
    $initials = $driver ? strtoupper(mb_substr($driver->first_name ?? '', 0, 1) . mb_substr($driver->last_name ?? '', 0, 1)) : '?';
    $driverLabel = $driver ? 'Driver #' . $driver->id : '';
    $locales = ['en' => 'EN', 'lv' => 'LV', 'ru' => 'RU'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('portal.title')) - Evo.drive</title>
    @include('components.analytics')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        ::selection {
            background-color: #2563eb;
            color: white;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .sticky-cta-shadow { box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.05); }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
        .portal-main { max-height: calc(100vh - 5rem); -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body class="bg-slate-50 selection:bg-brand-600 selection:text-white overflow-hidden" x-data="{ isSidebarOpen: false }">
    <div class="flex h-screen overflow-hidden">
        {{-- Mobile Sidebar Overlay --}}
        <div
            x-show="isSidebarOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-900/50 z-40 lg:hidden backdrop-blur-sm"
            @click="isSidebarOpen = false"
            aria-hidden="true"
        ></div>

        {{-- Sidebar --}}
        <aside
            class="fixed inset-y-0 left-0 z-50 w-72 min-h-screen bg-slate-900 text-white transition-transform duration-300 transform lg:translate-x-0 lg:static lg:inset-0 lg:h-screen lg:min-h-0 shrink-0"
            :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex flex-col h-full min-h-0">
                {{-- Sidebar Logo --}}
                <div class="p-8">
                    <a href="{{ url($locale) }}" class="text-2xl font-bold tracking-tight inline-flex items-baseline">
                        <span class="text-white">Evo.</span><span class="text-brand-600 -ml-px">drive</span>
                    </a>
                </div>

                {{-- Sidebar Navigation --}}
                <nav class="flex-1 px-4 space-y-2 overflow-y-auto">
                    @foreach($navItems as $item)
                        @php $active = ($seg === $item['segment']); @endphp
                        <a href="{{ route($item['route'], ['locale' => $locale]) }}"
                           class="flex items-center gap-4 px-4 py-4 rounded-2xl transition-all font-bold group {{ $active ? 'bg-brand-600 text-white shadow-brand-lg' : 'text-slate-400 hover:bg-slate-800 hover:text-white' }}">
                            @if($item['icon'] === 'dashboard')
                                <svg class="shrink-0" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                            @elseif($item['icon'] === 'shifts')
                                <svg class="shrink-0" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            @else
                                <svg class="shrink-0" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            @endif
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                {{-- Sidebar Footer --}}
                <div class="px-4 pt-4 pb-8 border-t border-slate-800 shrink-0">
                    <form method="POST" action="{{ route('driverportal.logout', ['locale' => $locale]) }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-4 px-4 py-4 w-full text-slate-400 hover:text-white transition-all font-bold group">
                            <svg class="shrink-0 group-hover:translate-x-1 transition-transform" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                            <span>{{ __('portal.logout') }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Main Content Wrapper --}}
        <div class="flex-1 flex flex-col min-w-0 min-h-0 overflow-hidden">
            {{-- Top Bar --}}
            <header class="h-20 bg-white border-b border-slate-200 flex items-center justify-between px-4 lg:px-10 shrink-0 z-30">
                <button
                    type="button"
                    @click="isSidebarOpen = true"
                    class="lg:hidden p-2 text-slate-500 hover:bg-slate-100 rounded-xl transition-colors"
                    aria-label="{{ __('portal.dashboard') }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/></svg>
                </button>

                <div class="flex-1 lg:flex-none lg:ml-auto flex items-center justify-end gap-6">
                    {{-- Language Switcher --}}
                    <div class="flex items-center gap-2 bg-slate-100 p-1.5 rounded-2xl border border-slate-200">
                        @foreach($locales as $code => $label)
                            <a href="{{ url($code . '/' . implode('/', array_slice(request()->segments(), 1))) }}"
                               class="px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wider transition-all {{ $locale === $code ? 'bg-white text-brand-600 shadow-sm' : 'text-slate-400 hover:text-slate-600' }}">{{ $label }}</a>
                        @endforeach
                    </div>

                    {{-- User Profile --}}
                    <div class="flex items-center gap-3 pl-6 border-l border-slate-200">
                        <div class="text-right hidden sm:block">
                            <p class="text-sm font-bold text-slate-900">{{ $driver ? $driver->name : '' }}</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ $driverLabel }}</p>
                        </div>
                        <div class="w-10 h-10 bg-[#dbeafe] text-[#2563eb] rounded-2xl flex items-center justify-center font-bold shrink-0" title="{{ $driver ? $driver->name : '' }}">
                            {{ $initials }}
                        </div>
                    </div>
                </div>
            </header>

            {{-- Page Content --}}
            <main class="portal-main flex-1 min-h-0 overflow-y-auto overflow-x-hidden p-4 lg:p-10 animate-fade-in">
                <div class="max-w-7xl mx-auto">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
