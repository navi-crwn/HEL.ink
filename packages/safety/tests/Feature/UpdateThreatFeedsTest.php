<?php

use Hop\Safety\Console\UpdateThreatFeeds;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->storage = sys_get_temp_dir().'/hop-feeds-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($this->storage.'/app/threat-feeds');
    app()->useStoragePath($this->storage);
    $this->feedDir = $this->storage.'/app/threat-feeds';
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

function feedUrls(): array
{
    return UpdateThreatFeeds::FEEDS;
}

test('feeds are normalized, de-duplicated and written without leftovers', function () {
    Http::fake([
        feedUrls()['malware'] => Http::response("# comment\nhttps://Bad.example/Path/\nhttp://www.bad.example/Path\n\nhttp://other.example/x\n"),
        feedUrls()['phishing'] => Http::response("https://phish.example/login\n"),
    ]);

    $this->artisan('abuse:feeds')->assertSuccessful();

    expect(file_get_contents($this->feedDir.'/malware.txt'))->toBe("bad.example/path\nother.example/x");
    expect(file_get_contents($this->feedDir.'/phishing.txt'))->toBe('phish.example/login');
    expect(array_values(array_diff(scandir($this->feedDir), ['.', '..'])))->toBe(['malware.txt', 'phishing.txt']);
})->skip(! defined('CURLOPT_PROGRESSFUNCTION'), 'Requires ext-curl: without it the command fails closed before the download.');

test('a download over the size limit is refused and the previous file is kept', function () {
    config()->set('safety.feed_max_bytes', 100);
    file_put_contents($this->feedDir.'/malware.txt', 'old.example/a');
    file_put_contents($this->feedDir.'/phishing.txt', 'old.example/b');
    Http::fake([
        feedUrls()['malware'] => Http::response(str_repeat("https://big.example/aaaaaaaaaa\n", 20)),
        feedUrls()['phishing'] => Http::response("https://ok.example/p\n", 200, ['Content-Length' => '5000']),
    ]);

    $this->artisan('abuse:feeds')->expectsOutputToContain('malware: the feed is larger than 100 bytes')->expectsOutputToContain('phishing: the feed is larger than 100 bytes')->assertFailed();

    expect(file_get_contents($this->feedDir.'/malware.txt'))->toBe('old.example/a');
    expect(file_get_contents($this->feedDir.'/phishing.txt'))->toBe('old.example/b');
    expect(array_values(array_diff(scandir($this->feedDir), ['.', '..'])))->toBe(['malware.txt', 'phishing.txt']);
})->skip(! defined('CURLOPT_PROGRESSFUNCTION'), 'Requires ext-curl: without it the command fails closed before the download.');

test('an empty feed or a page that is not a URL list never replaces a good file', function () {
    file_put_contents($this->feedDir.'/malware.txt', 'old.example/a');
    file_put_contents($this->feedDir.'/phishing.txt', 'old.example/b');
    Http::fake([
        feedUrls()['malware'] => Http::response(''),
        feedUrls()['phishing'] => Http::response("<html><body>Service unavailable</body></html>\n# nothing\n"),
    ]);

    $this->artisan('abuse:feeds')->expectsOutputToContain('malware: the feed has no URLs')->expectsOutputToContain('phishing: the feed has no URLs')->assertFailed();

    expect(file_get_contents($this->feedDir.'/malware.txt'))->toBe('old.example/a');
    expect(file_get_contents($this->feedDir.'/phishing.txt'))->toBe('old.example/b');
    expect(array_values(array_diff(scandir($this->feedDir), ['.', '..'])))->toBe(['malware.txt', 'phishing.txt']);
})->skip(! defined('CURLOPT_PROGRESSFUNCTION'), 'Requires ext-curl: without it the command fails closed before the download.');

test('one failing feed does not stop the other and the run still succeeds', function () {
    file_put_contents($this->feedDir.'/malware.txt', 'old.example/a');
    Http::fake([
        feedUrls()['malware'] => Http::response('server error', 500),
        feedUrls()['phishing'] => Http::response("https://phish.example/x\n"),
    ]);

    $this->artisan('abuse:feeds')->assertSuccessful();

    expect(file_get_contents($this->feedDir.'/malware.txt'))->toBe('old.example/a');
    expect(file_get_contents($this->feedDir.'/phishing.txt'))->toBe('phish.example/x');
})->skip(! defined('CURLOPT_PROGRESSFUNCTION'), 'Requires ext-curl: without it the command fails closed before the download.');

test('without curl the command fails closed, keeps the previous files and makes no request', function () {
    file_put_contents($this->feedDir.'/malware.txt', 'old.example/a');
    file_put_contents($this->feedDir.'/phishing.txt', 'old.example/b');
    // No fakes here on purpose: anything that reaches the transport layer is counted and refused by this
    // middleware, which runs before the real handler and never opens a socket.
    $attempts = 0;
    Http::globalMiddleware(function () use (&$attempts) {
        return function ($request, $options) use (&$attempts) {
            $attempts++;

            throw new RuntimeException('network attempted in test');
        };
    });

    $this->artisan('abuse:feeds')
        ->expectsOutputToContain('malware: the curl extension is required to size-limit the feed download')
        ->expectsOutputToContain('phishing: the curl extension is required to size-limit the feed download')
        ->assertFailed();

    expect($attempts)->toBe(0, 'a feed download was attempted without the size-limit guard')
        ->and(file_get_contents($this->feedDir.'/malware.txt'))->toBe('old.example/a')
        ->and(file_get_contents($this->feedDir.'/phishing.txt'))->toBe('old.example/b')
        ->and(array_values(array_diff(scandir($this->feedDir), ['.', '..'])))->toBe(['malware.txt', 'phishing.txt']);
})->skip(defined('CURLOPT_PROGRESSFUNCTION'), 'This checks the no-curl fallback.');

test('the default limit is 64 MB and can be set in the config', function () {
    expect(config('safety.feed_max_bytes'))->toBe(64 * 1024 * 1024);
});
