<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegalPageController extends Controller
{
    public function privacy(string $locale): View
    {
        return $this->renderCmsLegalPage('privacy');
    }

    public function terms(string $locale): View
    {
        return $this->renderCmsLegalPage('terms');
    }

    private function renderCmsLegalPage(string $key): View
    {
        $page = Page::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->with(['sections' => fn ($q) => $q->orderBy('sort_order')])
            ->first();

        if (! $page) {
            throw new NotFoundHttpException;
        }

        return view('legal.cms', [
            'page' => $page,
            'legalMarkdown' => static function (?string $markdown): string {
                if ($markdown === null || trim($markdown) === '') {
                    return '';
                }

                return Str::markdown($markdown);
            },
        ]);
    }
}
