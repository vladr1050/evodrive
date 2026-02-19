@php
    $page = $page ?? null;
    $locale = app()->getLocale();
@endphp
@if($page)
    <meta name="description" content="{{ $page->getTranslated('meta_description') ?? $page->getTranslated('meta_title') }}">
    <meta name="keywords" content="{{ $page->getTranslated('meta_keywords') ?? '' }}">
    <meta name="robots" content="{{ $page->robots ?? 'index,follow' }}">
    @if($page->canonical_url)
        <link rel="canonical" href="{{ $page->canonical_url }}">
    @endif
    <meta property="og:title" content="{{ $page->getTranslated('og_title') ?? $page->getTranslated('meta_title') }}">
    <meta property="og:description" content="{{ $page->getTranslated('og_description') ?? $page->getTranslated('meta_description') }}">
    @if($page->og_image)
        <meta property="og:image" content="{{ Storage::url($page->og_image) }}">
    @endif
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page->getTranslated('og_title') ?? $page->getTranslated('meta_title') }}">
    <meta name="twitter:description" content="{{ $page->getTranslated('og_description') ?? $page->getTranslated('meta_description') }}">
@else
    @php
        $metaDesc = $metaDescription ?? 'EvoDrive.lv — Drive. Earn. Repeat. Start working in taxi in Latvia this week.';
        $ogTitleVal = $ogTitle ?? 'EvoDrive.lv — Drive. Earn. Repeat.';
        $ogDescVal = $ogDescription ?? 'Start working in taxi in Latvia this week. Choose your path.';
    @endphp
    <meta name="description" content="{{ $metaDesc }}">
    <meta name="robots" content="index,follow">
    <meta property="og:title" content="{{ $ogTitleVal }}">
    <meta property="og:description" content="{{ $ogDescVal }}">
    @if(!empty($ogImage))
        <meta property="og:image" content="{{ str_starts_with($ogImage, 'http') ? $ogImage : url($ogImage) }}">
    @endif
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitleVal }}">
    <meta name="twitter:description" content="{{ $ogDescVal }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
@endif
@php
    $pathWithoutLocale = preg_replace('#^/[a-z]{2}#', '', request()->getRequestUri()) ?: '';
@endphp
<link rel="alternate" hreflang="en" href="{{ url('/en' . $pathWithoutLocale) }}">
<link rel="alternate" hreflang="ru" href="{{ url('/ru' . $pathWithoutLocale) }}">
<link rel="alternate" hreflang="lv" href="{{ url('/lv' . $pathWithoutLocale) }}">
<link rel="alternate" hreflang="x-default" href="{{ url('/en' . $pathWithoutLocale) }}">
