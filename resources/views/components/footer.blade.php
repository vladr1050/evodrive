@php
    $locale = app()->getLocale();
    $settings = $siteSettings ?? \App\Models\SiteSetting::first();
@endphp
<footer class="bg-white border-t border-slate-100 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
            <div class="col-span-1 md:col-span-2 space-y-4">
                <div class="flex items-center gap-2">
                    <x-logo class="h-6 w-auto"/>
                    <span class="text-xl font-bold tracking-tight text-slate-900">EvoDrive<span class="text-brand-600">.lv</span></span>
                </div>
                <p class="text-slate-400 text-sm max-w-sm font-medium leading-relaxed">
                    {{ __('ui.footer_tagline') }}
                </p>
            </div>
            <div>
                <h3 class="text-[10px] font-bold text-slate-900 uppercase tracking-[0.2em] mb-6">{{ __('ui.footer_drive') }}</h3>
                <ul class="space-y-4">
                    <li><a href="{{ route('work', ['locale' => $locale]) }}" class="text-sm font-bold text-slate-400 hover:text-brand-600 transition-colors">{{ __('ui.nav_work_fleet') }}</a></li>
                    <li><a href="{{ route('rent', ['locale' => $locale]) }}" class="text-sm font-bold text-slate-400 hover:text-brand-600 transition-colors">{{ __('ui.nav_rent_taxi') }}</a></li>
                    <li><a href="{{ $applyUrl }}" class="text-sm font-bold text-slate-400 hover:text-brand-600 transition-colors">{{ __('ui.apply_now') }}</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-[10px] font-bold text-slate-900 uppercase tracking-[0.2em] mb-6">{{ __('ui.footer_support') }}</h3>
                <ul class="space-y-4">
                    <li><a href="{{ route('legal.privacy', ['locale' => $locale]) }}" class="text-sm font-bold text-slate-400 hover:text-brand-600 transition-colors">{{ __('ui.footer_privacy') }}</a></li>
                    <li><a href="{{ route('legal.terms', ['locale' => $locale]) }}" class="text-sm font-bold text-slate-400 hover:text-brand-600 transition-colors">{{ __('ui.footer_terms') }}</a></li>
                    <li><a href="mailto:support@evodrive.lv" class="text-sm font-bold text-slate-400 hover:text-brand-600 transition-colors">support@evodrive.lv</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-16 pt-8 border-t border-slate-50 flex flex-col md:flex-row justify-between items-center gap-4">
            <p class="text-[10px] font-bold text-slate-300 uppercase tracking-widest">&copy; {{ date('Y') }} EvoDrive Latvia. {{ __('ui.footer_rights') }}</p>
            <div class="text-[10px] font-bold text-slate-300 uppercase tracking-widest">{{ __('ui.footer_tagline2') }}</div>
        </div>
    </div>
</footer>
