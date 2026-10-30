<?php

namespace App\Http\Controllers;

use App\Services\Watchdog;
use App\Support\FamilyStory;

class MarketingPageController extends Controller
{
    public function about()
    {
        return view('marketing.about');
    }

    public function contact()
    {
        return view('marketing.contact');
    }

    public function privacy()
    {
        return view('marketing.privacy');
    }

    public function terms()
    {
        return view('marketing.terms');
    }

    public function plans()
    {
        return view('marketing.plans');
    }

    public function help()
    {
        return view('marketing.help');
    }

    public function faq()
    {
        return view('marketing.faq');
    }

    public function products()
    {
        return view('marketing.products');
    }

    public function howItWorks()
    {
        return view('marketing.how-it-works');
    }

    public function security()
    {
        return view('marketing.security');
    }

    public function openSource()
    {
        return view('marketing.open-source', ['stories' => FamilyStory::all()]);
    }

    public function signup()
    {
        return view('marketing.signup');
    }

    /** hel.ink/apps/{app}: what the app is, what it does, where it lives. */
    public function app(string $app, Watchdog $watchdog)
    {
        $story = FamilyStory::find($app);
        abort_unless($story, 404);

        return view('marketing.app', ['app' => $story, 'status' => $watchdog->summary()[$app] ?? null]);
    }
}
