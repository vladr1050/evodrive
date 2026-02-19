<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, string $locale): View
    {
        return view('home', [
            'siteSettings' => SiteSetting::first(),
            'metaDescription' => __('ui.home_hero_sub'),
            'ogTitle' => __('ui.home_hero_title') . ' ' . __('ui.home_hero_repeat') . ' — EvoDrive.lv',
            'ogDescription' => __('ui.home_hero_sub'),
        ]);
    }
}
