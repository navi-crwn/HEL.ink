<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Support\FamilyStory;
use App\Support\Seo;
use Illuminate\Http\Response;

/**
 * sitemap.xml: the pages of the public host (hel.ink), each once, with its language alternates.
 * The URLs are built from hop.public_url, so the file is the same on the hub host and never lists it.
 */
class SitemapController extends Controller
{
    private const PAGES = ['', 'about', 'products', 'how-it-works', 'security', 'open-source', 'signup', 'plans', 'faq', 'help', 'status', 'changelog', 'announcements', 'contact', 'privacy', 'terms'];

    public function __invoke(): Response
    {
        $paths = self::PAGES;
        if (config('hop.public_host') !== config('hop.hub_host')) {
            foreach (FamilyStory::APPS as $app) {
                $paths[] = 'apps/'.$app;
            }
        }
        // The sitemap must answer while the database is down: then without the announcement pages.
        try {
            foreach (Announcement::live()->limit(200)->get() as $announcement) {
                $paths[] = 'announcements/'.$announcement->getRouteKey();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach (array_unique($paths) as $path) {
            $xml .= '  <url>'."\n".'    <loc>'.e(Seo::url($path)).'</loc>'."\n";
            foreach (Seo::alternates($path) as $code => $href) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="'.e($code).'" href="'.e($href).'"/>'."\n";
            }
            $xml .= '  </url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
