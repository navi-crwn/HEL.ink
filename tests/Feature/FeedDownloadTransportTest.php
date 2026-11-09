<?php

use Hop\Safety\Console\UpdateThreatFeeds;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\Process\Process;

/**
 * The feed downloader over a real connection (a throwaway PHP server on loopback), not Http::fake().
 * The size limit is enforced by curl's progress callback while the body is still arriving; the faked
 * tests in packages/safety only reach the size check that runs after the download. Without ext-curl
 * the command refuses to download at all (covered there), so these need curl.
 */
function startFeedServer(): array
{
    $dir = sys_get_temp_dir().'/hop-feed-server-'.bin2hex(random_bytes(4));
    mkdir($dir);
    file_put_contents($dir.'/router.php', <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: text/plain');
if ($path === '/feed') {
    echo "https://bad.example/a\nhttps://bad.example/b\n";

    return;
}
if ($path === '/declared') {
    // Says up front that it is far too big, then would trickle.
    header('Content-Length: 50000000');
    echo str_repeat('x', 1024);
    flush();
    sleep(20);

    return;
}
if ($path === '/endless') {
    // No length: 200 MB in 64 KiB chunks unless the client hangs up.
    while (ob_get_level()) {
        ob_end_flush();
    }
    $chunk = str_repeat("https://bad.example/x\n", 2979);
    for ($i = 0; $i < 3200 && ! connection_aborted(); $i++) {
        echo $chunk;
        flush();
    }

    return;
}
http_response_code(404);
PHP);
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $server = new Process([PHP_BINARY, '-S', "127.0.0.1:{$port}", $dir.'/router.php'], $dir);
    $server->start();
    $deadline = microtime(true) + 5;
    while (microtime(true) < $deadline) {
        if ($probe = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1)) {
            fclose($probe);

            return [$server, "http://127.0.0.1:{$port}", $dir];
        }
        usleep(20000);
    }
    $server->stop(0);

    throw new RuntimeException('the loopback feed server did not start: '.$server->getErrorOutput());
}

function feedDownload(string $url, string $sink, int $max): void
{
    $command = new UpdateThreatFeeds;
    (new ReflectionMethod($command, 'download'))->invoke($command, $url, $sink, $max);
}

beforeEach(function () {
    [$this->server, $this->base, $this->dir] = startFeedServer();
    $this->sink = $this->dir.'/sink';
});

afterEach(function () {
    $this->server->stop(0);
    array_map('unlink', glob($this->dir.'/*'));
    rmdir($this->dir);
});

test('a feed within the limit arrives whole over a real connection', function () {
    feedDownload($this->base.'/feed', $this->sink, 1024);

    expect(file_get_contents($this->sink))->toBe("https://bad.example/a\nhttps://bad.example/b\n");
})->skip(! defined('CURLOPT_PROGRESSFUNCTION'), 'Requires ext-curl.');

test('a body that grows past the limit is cut off while it is still arriving', function () {
    $max = 256 * 1024;
    $started = microtime(true);

    expect(fn () => feedDownload($this->base.'/endless', $this->sink, $max))
        ->toThrow(RuntimeException::class, 'the feed is larger than '.$max.' bytes');

    clearstatcache(true, $this->sink);
    // The server would send 200 MB. The transfer stops within a few buffers of the limit, not after the body.
    expect(filesize($this->sink))->toBeLessThan(8 * 1024 * 1024)
        ->and(microtime(true) - $started)->toBeLessThan(15);
})->skip(! defined('CURLOPT_PROGRESSFUNCTION'), 'Requires ext-curl.');

test('a feed that declares a size over the limit is refused from its headers', function () {
    $started = microtime(true);

    expect(fn () => feedDownload($this->base.'/declared', $this->sink, 1024 * 1024))
        ->toThrow(RuntimeException::class, 'the feed is larger than');

    clearstatcache(true, $this->sink);
    // The server holds the connection for 20 s; the client leaves as soon as it has read the length.
    expect((int) @filesize($this->sink))->toBeLessThan(64 * 1024)
        ->and(microtime(true) - $started)->toBeLessThan(10);
})->skip(! defined('CURLOPT_PROGRESSFUNCTION'), 'Requires ext-curl.');

test('a missing feed is an error, not an empty file that passes', function () {
    expect(fn () => feedDownload($this->base.'/nothing', $this->sink, 1024))->toThrow(RequestException::class);
})->skip(! defined('CURLOPT_PROGRESSFUNCTION'), 'Requires ext-curl.');
