@props([
    'class' => 'h-8 w-auto',
])
@php
    $settings = \App\Models\SiteSetting::first();
    $logoUrl = ($settings && $settings->logo_path) ? \Illuminate\Support\Facades\Storage::url($settings->logo_path) : asset('images/logo.png');
@endphp
<img src="{{ $logoUrl }}" alt="EvoDrive" {{ $attributes->merge(['class' => $class]) }}>
