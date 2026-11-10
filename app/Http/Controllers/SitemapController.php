<?php

namespace App\Http\Controllers;

use App\Support\FamilyStory;
use Illuminate\Http\Response;

/** sitemap.xml for the public host: the marketing pages and one product page per app. */
class SitemapController extends Controller
{
    private const PAGES = ['', 'about', 'products', 'how-it-works', 'security', 'open-source', 'plans', 'faq', 'help', 'status', 'changelog', 'announcements', 'contact', 'privacy', 'terms'];

    public function __invoke(): Response
    {
        $paths = self::PAGES;
        if (config('hop.public_host') !== config('hop.hub_host')) {
            foreach (FamilyStory::APPS as $app) {
                $paths[] = 'apps/'.$app;
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($paths as $path) {
            $xml .= '  <url><loc>'.e(url($path)).'</loc></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
