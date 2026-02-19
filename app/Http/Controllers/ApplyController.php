<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class ApplyController extends Controller
{
    public function show(Request $request, string $locale): View
    {
        $sessionData = Session::get('apply_data', []);

        // Prefill from rent-detail GET params (phone, rent_car_id, atd_number, intent)
        $prefill = array_filter([
            'phone' => $request->get('phone'),
            'rent_car_id' => $request->get('rent_car_id'),
            'atd_number' => $request->get('atd'),
        ]);
        if (! empty($prefill['rent_car_id'])) {
            $prefill['intent'] = 'rent';
        }
        $sessionData = array_merge($sessionData, $prefill);

        return view('apply.index', [
            'sessionData' => $sessionData,
            'source' => $this->resolveSource($request),
            'metaDescription' => __('apply.step_phone_title') . ' — EvoDrive.lv. ' . __('ui.home_hero_sub'),
            'ogTitle' => __('apply.personal_info') . ' — EvoDrive.lv',
            'ogDescription' => __('ui.home_hero_sub'),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|min:8|max:15',
            'intent' => 'required|in:work,rent',
            'rent_car_id' => 'nullable|exists:rental_vehicles,id',
            'atd_number' => 'nullable|string|max:50',
            'atd_license' => 'required|in:yes,no',
            'driving_experience' => 'required|in:3-5,5-10,10+',
            'name' => 'required|string|max:255',
            'area' => 'required|string|max:100',
            'website_url' => 'nullable|max:0', // honeypot - must be empty
        ]);

        if (! empty($validated['website_url'])) {
            abort(422, 'Invalid request');
        }

        $source = $this->resolveSource($request);
        $sessionData = Session::get('apply_data', []);

        $lead = Lead::create([
            'phone' => $validated['phone'],
            'intent' => $validated['intent'],
            'rent_car_id' => $validated['rent_car_id'] ?? null,
            'atd_number' => $validated['atd_number'] ?? null,
            'atd_license' => $validated['atd_license'] === 'yes',
            'driving_experience' => $validated['driving_experience'],
            'name' => $validated['name'],
            'area' => $validated['area'],
            'source' => $source,
            'status' => 'new',
            'utm_source' => $request->get('utm_source') ?? Session::get('utm_source'),
            'utm_campaign' => $request->get('utm_campaign') ?? Session::get('utm_campaign'),
            'utm_medium' => $request->get('utm_medium') ?? Session::get('utm_medium'),
            'utm_content' => $request->get('utm_content') ?? Session::get('utm_content'),
            'utm_term' => $request->get('utm_term') ?? Session::get('utm_term'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Session::forget('apply_data');

        $locale = $request->route('locale', 'en');

        return redirect()->route('apply.thanks', ['locale' => $locale])
            ->with('phone', $lead->phone);
    }

    public function thanks(Request $request, string $locale): View
    {
        $phone = Session::get('phone', $request->session()->get('phone'));

        return view('apply.thanks', [
            'phone' => $phone,
            'metaDescription' => __('apply.done') . ' — ' . __('ui.home_hero_sub'),
            'ogTitle' => __('apply.done') . ' — EvoDrive.lv',
            'ogDescription' => __('ui.home_hero_sub'),
        ]);
    }

    private function resolveSource(Request $request): string
    {
        $utmSource = strtolower($request->get('utm_source', ''));
        if (str_contains($utmSource, 'google') || str_contains($utmSource, 'gclid')) {
            return 'google';
        }
        if (str_contains($utmSource, 'meta') || str_contains($utmSource, 'facebook') || str_contains($utmSource, 'fb')) {
            return 'meta';
        }

        // rent_car_id = came from rent-detail (meta/rent funnel)
        if ($request->filled('rent_car_id')) {
            return 'meta';
        }

        $path = $request->path();
        if (str_contains($path, '/g') || str_contains($path, 'landing/google')) {
            return 'google';
        }
        if (str_contains($path, '/m') || str_contains($path, 'landing/meta')) {
            return 'meta';
        }
        if (str_contains($path, '/rent/')) {
            return 'meta';
        }

        return 'unknown';
    }
}
