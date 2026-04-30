@extends('layouts.app')

@php
    $title = $page->getTranslated('meta_title') ?? $page->getTranslated('title') ?? config('app.name');
@endphp
@section('title', $title . ' — Evo.drive')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-20">
    <h1 class="text-4xl font-black text-slate-900 mb-8 tracking-tight">{{ $page->getTranslated('title') }}</h1>
    <div class="prose prose-slate max-w-none text-slate-600 prose-headings:font-black prose-headings:tracking-tight prose-a:text-brand-600">
        @php $hasBody = false; @endphp
        @foreach($page->sections as $section)
            @php
                $c = $section->getContentForLocale();
                $heading = isset($c['heading']) ? trim((string) $c['heading']) : '';
                $body = isset($c['body']) ? trim((string) $c['body']) : '';
            @endphp
            @if($heading !== '')
                <h2 class="text-2xl font-black text-slate-900 mt-10 mb-4">{{ $heading }}</h2>
            @endif
            @if($body !== '')
                @php $hasBody = true; @endphp
                <div class="legal-cms-section">
                    {!! $legalMarkdown($body) !!}
                </div>
            @endif
        @endforeach
        @if(! $hasBody)
            <p>{{ __('ui.legal_placeholder') }}</p>
        @endif
    </div>
    <p class="mt-12">
        <a href="{{ route('home', ['locale' => app()->getLocale()]) }}" class="text-brand-600 font-bold hover:underline">{{ __('ui.error_404_go_home') }}</a>
    </p>
</div>
@endsection
