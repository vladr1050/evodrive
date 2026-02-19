<?php

namespace App\Http\Controllers;

use App\Models\FaqCategory;
use App\Models\Page;
use App\Models\RentalVehicle;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LandingController extends Controller
{
    public function google(string $locale): View
    {
        $page = Page::where('key', 'google_landing')->first();
        $sections = $page?->sections->keyBy('key') ?? collect();

        return view('landing.google', [
            'page' => $page,
            'sections' => $sections,
            'siteSettings' => SiteSetting::first(),
        ]);
    }

    public function meta(string $locale): View
    {
        $page = Page::where('key', 'meta_landing')->first();
        $sections = $page?->sections->keyBy('key') ?? collect();
        $rentalCars = RentalVehicle::where('is_active', true)->orderBy('sort_order')->get()->map->toPublicArray()->all();

        return view('landing.meta', [
            'page' => $page,
            'sections' => $sections,
            'siteSettings' => SiteSetting::first(),
            'rentalCars' => $rentalCars,
        ]);
    }

    public function rentDetail(string $locale, string $id): View
    {
        $vehicle = RentalVehicle::where('is_active', true)->find($id);

        if (! $vehicle) {
            throw new NotFoundHttpException('Vehicle not found.');
        }

        $car = $vehicle->toPublicArray();
        $carTitle = $car['make'] . ' ' . $car['model'];
        $metaDesc = $carTitle . ' — ' . __('ui.rent_weekly_rent') . ' €' . $car['price'] . '/week. ' . ($car['description'] ?? __('ui.rent_hero_sub'));
        $metaDesc = \Illuminate\Support\Str::limit(strip_tags($metaDesc), 160);

        return view('landing.rent-detail', [
            'car' => $car,
            'locale' => $locale,
            'metaDescription' => $metaDesc,
            'ogTitle' => $carTitle . ' — ' . __('ui.rent_now') . ' — EvoDrive.lv',
            'ogDescription' => $metaDesc,
            'ogImage' => $car['image'] ?? null,
        ]);
    }

    public function faq(string $locale): View
    {
        $categories = FaqCategory::with('items')
            ->orderBy('sort_order')
            ->get();

        return view('landing.faq', [
            'categories' => $categories,
            'siteSettings' => SiteSetting::first(),
            'metaDescription' => __('ui.faq_hero_sub'),
            'ogTitle' => __('ui.faq_title') . ' — EvoDrive.lv',
        ]);
    }
}
