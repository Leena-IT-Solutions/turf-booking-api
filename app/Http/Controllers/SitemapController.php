<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Generate dynamic sitemap XML.
     */
    public function index(): Response
    {
        $urls = [
            ['loc' => url('/'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => url('/features'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => url('/how-it-works'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => url('/pricing'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => url('/faqs'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['loc' => url('/contact'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => url('/for-turf-owners'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => url('/download'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => url('/privacy-policy'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => url('/refund-policy'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => url('/terms-and-conditions'), 'lastmod' => '2026-08-16T22:00:00+05:30', 'changefreq' => 'monthly', 'priority' => '0.5'],
        ];

        return response()->view('sitemap', compact('urls'))
            ->header('Content-Type', 'text/xml');
    }
}
