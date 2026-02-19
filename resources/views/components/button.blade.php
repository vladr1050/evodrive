@props([
    'variant' => 'primary',
    'size' => 'md',
    'fullWidth' => false,
    'tag' => 'button',
])

@php
    $baseStyles = 'inline-flex items-center justify-center font-bold tracking-tight transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg';
    $variants = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 shadow-sm border border-transparent',
        'secondary' => 'bg-slate-900 text-white hover:bg-black shadow-sm border border-transparent',
        'outline' => 'bg-transparent text-slate-900 border border-slate-200 hover:border-slate-900',
        'ghost' => 'bg-transparent text-slate-500 hover:text-slate-900 hover:bg-slate-50',
    ];
    $sizes = [
        'sm' => 'px-4 py-2 text-xs',
        'md' => 'px-6 py-3 text-sm',
        'lg' => 'px-10 py-5 text-base',
    ];
    $widthClass = $fullWidth ? 'w-full' : '';
    $userClass = $attributes->get('class', '');
    $classes = trim("$baseStyles " . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']) . " $widthClass $userClass");
@endphp

@if($tag === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => $attributes->get('type', 'button')]) }}>
        {{ $slot }}
    </button>
@endif
