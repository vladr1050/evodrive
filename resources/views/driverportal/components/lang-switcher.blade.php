@php
    $locale = app()->getLocale();
    $locales = ['lv' => 'LV', 'en' => 'ENG', 'ru' => 'RUS'];
@endphp
<div class="flex items-center gap-2">
    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0h.5a2.5 2.5 0 002.5-2.5V8m0 4a2.5 2.5 0 01-2.5 2.5h-.5a2 2 0 00-2 2v1M12 12.5V16a2 2 0 002 2h.5a2.5 2.5 0 002.5-2.5v-1M12 8.055V8a2.5 2.5 0 012.5-2.5h.5a2 2 0 012 2v2.945M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div class="flex gap-2 text-xs font-bold">
        @foreach($locales as $code => $label)
            <a href="{{ url($code . '/' . implode('/', array_slice(request()->segments(), 1))) }}" class="{{ $locale === $code ? 'text-brand-600' : 'text-slate-400 hover:text-slate-600' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
