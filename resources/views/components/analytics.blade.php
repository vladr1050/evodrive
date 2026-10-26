@php
    $gtmId = config('services.google_tag_manager.container_id');
@endphp
@if($gtmId)
    {{-- Google Tag Manager (GA4 / Meta / TikTok / Ads configured inside GTM) --}}
    <script>
        window.dataLayer = window.dataLayer || [];
    </script>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $gtmId }}');</script>
@endif
