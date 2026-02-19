@php
    $path = request()->path();
    $locale = app()->getLocale();
    $isWorkActive = str_ends_with($path, 'g');
    $isRentActive = str_ends_with($path, 'm');
    $isFaqActive = str_contains($path, 'faq');
    $settings = $siteSettings ?? \App\Models\SiteSetting::first();
    $navLinks = [
        ['name' => __('ui.nav_work_fleet'), 'path' => route('work', ['locale' => $locale]), 'icon' => 'briefcase'],
        ['name' => __('ui.nav_rent_taxi'), 'path' => route('rent', ['locale' => $locale]), 'icon' => 'car'],
        ['name' => __('ui.faq_title'), 'path' => route('landing.faq', ['locale' => $locale]), 'icon' => 'faq'],
    ];
    $langLabels = ['lv' => 'LV', 'en' => 'ENG', 'ru' => 'RUS'];
    $langOrder = ['lv', 'en', 'ru'];
@endphp
<nav class="sticky top-0 z-50 w-full bg-white/90 backdrop-blur-md border-b border-slate-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <a href="{{ route('home', ['locale' => $locale]) }}" class="flex items-center gap-2 group">
                <x-logo class="h-8 w-auto group-hover:scale-105 transition-transform"/>
                <span class="text-xl font-bold tracking-tight text-slate-900">EvoDrive<span class="text-brand-600">.lv</span></span>
            </a>

            <div class="hidden md:flex items-center space-x-8">
                @foreach($navLinks as $link)
                    <a href="{{ $link['path'] }}"
                       class="text-sm font-semibold transition-colors flex items-center gap-1.5 {{ ($link['icon'] === 'briefcase' ? $isWorkActive : ($link['icon'] === 'car' ? $isRentActive : $isFaqActive)) ? 'text-brand-600' : 'text-slate-500 hover:text-slate-900' }}">
                        {{ $link['name'] }}
                    </a>
                @endforeach
                <a href="{{ $applyUrl }}"
                   class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-brand-600 transition-colors">
                    {{ __('ui.apply_now') }}
                </a>
                <div class="flex items-center gap-1 bg-slate-50 p-1 rounded-lg border border-slate-100">
                    @foreach($langOrder as $loc)
                        @php $suffix = preg_replace('#^/[a-z]{2}#', '', request()->getRequestUri() ?: '/'); @endphp
                        <a href="{{ url('/' . $loc . ($suffix ?: '/')) }}"
                           class="px-2 py-0.5 rounded text-[10px] font-bold transition-all {{ $locale === $loc ? 'bg-white shadow-sm text-brand-600' : 'text-slate-400 hover:text-slate-600' }}">
                            {{ $langLabels[$loc] }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center md:hidden">
                <button type="button" id="navbar-menu-toggle" aria-expanded="false" aria-controls="navbar-mobile-menu"
                        class="p-2 rounded-md text-slate-600">
                    <span id="navbar-icon-menu"><x-icon-menu class="w-6 h-6" /></span>
                    <span id="navbar-icon-x" class="hidden"><x-icon-x class="w-6 h-6" /></span>
                </button>
            </div>
        </div>
    </div>

    <div id="navbar-mobile-menu" class="hidden md:hidden bg-white border-b border-slate-100">
        <div class="px-4 pt-2 pb-6 space-y-1">
            @foreach($navLinks as $link)
                <a href="{{ $link['path'] }}" class="block px-3 py-3 rounded-lg text-base font-bold flex items-center gap-3 {{ ($link['icon'] === 'briefcase' ? $isWorkActive : ($link['icon'] === 'car' ? $isRentActive : $isFaqActive)) ? 'text-brand-600' : 'text-slate-500 hover:text-slate-900' }}">
                    @if($link['icon'] === 'briefcase')
                        <x-icon-briefcase class="w-5 h-5" />
                    @elseif($link['icon'] === 'car')
                        <x-icon-car class="w-5 h-5" />
                    @else
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                    {{ $link['name'] }}
                </a>
            @endforeach
            <a href="{{ $applyUrl }}" class="block px-3 py-3 rounded-lg text-base font-bold bg-brand-600 text-white text-center mt-4">
                {{ __('ui.apply_now') }}
            </a>
            <div class="pt-4 flex justify-center gap-4">
                @foreach($langOrder as $loc)
                    @php $suffix = preg_replace('#^/[a-z]{2}#', '', request()->getRequestUri() ?: '/'); @endphp
                    <a href="{{ url('/' . $loc . ($suffix ?: '/')) }}"
                       class="px-4 py-2 rounded-lg text-sm font-bold {{ $locale === $loc ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-400' }}">
                        {{ $langLabels[$loc] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</nav>
