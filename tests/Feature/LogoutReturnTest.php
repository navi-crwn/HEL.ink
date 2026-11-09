<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
 * Sign-out from an app returns to that app: `return` is honoured only for a family app origin.
 */

function endOfChain(string $location): string
{
    // The chain nests the final address in the last ?next=; unwrap until none is left.
    while (($q = parse_url($location, PHP_URL_QUERY)) && str_contains($q, 'next=')) {
        parse_str($q, $out);
        $location = $out['next'];
    }

    return $location;
}

test('an app origin in return is where the chained logout ends, on GET and POST', function () {
    Queue::fake();
    $this->get('/logout?return='.urlencode('https://link.hel.ink/'))->assertRedirect();
    $loc = $this->get('/logout?return='.urlencode('https://link.hel.ink/'))->headers->get('Location');
    expect(endOfChain($loc))->toBe('https://link.hel.ink/');

    $loc = $this->actingAs(User::factory()->create())->post('/logout', ['return' => 'https://bio.hel.ink/'])->headers->get('Location');
    expect(endOfChain($loc))->toBe('https://bio.hel.ink/');
});

test('open-redirect attempts fall back to the hub home', function (string $bad) {
    Queue::fake();
    $loc = $this->get('/logout?return='.urlencode($bad))->headers->get('Location');
    expect(endOfChain($loc))->toBe(route('home'));
})->with([
    'other host' => 'https://evil.example/',
    'lookalike suffix' => 'https://link.hel.ink.evil.example/',
    'userinfo' => 'https://link.hel.ink@evil.example/',
    'userinfo reversed' => 'https://evil.example@link.hel.ink/',
    'backslash' => 'https://evil.example\@link.hel.ink/',
    'scheme-less' => '//evil.example/',
    'relative' => '/dashboard',
    'javascript' => 'javascript:alert(1)',
    'wrong scheme' => 'ftp://link.hel.ink/',
    'wrong port' => 'https://link.hel.ink:8443/',
    'plain http downgrade' => 'http://link.hel.ink/',
]);

test('no return keeps the hub home', function () {
    Queue::fake();
    expect(endOfChain($this->get('/logout')->headers->get('Location')))->toBe(route('home'));
});
