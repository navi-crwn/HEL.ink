<?php

use Hop\Safety\UrlGuard;

/** A guard whose DNS answers come from a fixed table instead of the network. */
function fakeGuard(array $dns): UrlGuard
{
    return new class($dns) extends UrlGuard
    {
        public int $lookups = 0;

        public function __construct(private array $dns) {}

        protected function resolveHost(string $host): array
        {
            $this->lookups++;

            return filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->dns[$host] ?? []);
        }
    };
}

test('resolve returns the validated target with scheme, host, port and public ips', function () {
    $guard = fakeGuard(['Example.com' => ['93.184.216.34', '2606:2800:220:1::1', '93.184.216.34']]);

    expect($guard->resolve('https://Example.com/path?q=1'))->toBe([
        'scheme' => 'https',
        'host' => 'example.com',
        'port' => 443,
        'ips' => ['93.184.216.34', '2606:2800:220:1::1'],
    ]);
    expect($guard->resolve('http://Example.com:8080/')['port'])->toBe(8080);
    expect($guard->resolve('http://Example.com/')['port'])->toBe(80);
});

test('resolve resolves the host exactly once', function () {
    $guard = fakeGuard(['example.com' => ['93.184.216.34']]);
    $guard->resolve('https://example.com/');
    expect($guard->lookups)->toBe(1);
    $guard->isSafe('https://example.com/');
    expect($guard->lookups)->toBe(2);
});

test('resolve refuses what isSafe refuses', function () {
    $guard = fakeGuard([
        'internal.example' => ['10.0.0.5'],
        'mixed.example' => ['93.184.216.34', '127.0.0.1'],
        'meta.example' => ['169.254.169.254'],
        'v6.example' => ['::1'],
        'public.example' => ['93.184.216.34'],
    ]);

    foreach ([
        null, '', 'not a url', '/relative', 'ftp://public.example/', 'javascript://public.example/',
        'https://internal.example/', 'https://mixed.example/', 'https://meta.example/', 'https://v6.example/',
        'https://unknown.example/', 'http://127.0.0.1/', 'http://[::1]/', 'http://10.1.2.3/', 'http://169.254.169.254/latest/meta-data/',
    ] as $url) {
        expect($guard->resolve($url))->toBeNull((string) $url);
        expect($guard->isSafe($url))->toBeFalse((string) $url);
    }
    expect($guard->isSafe('https://public.example/'))->toBeTrue();
});

test('isSafe keeps its answers for ip literals', function () {
    $guard = new UrlGuard;
    expect($guard->isSafe('http://93.184.216.34/'))->toBeTrue();
    expect($guard->isSafe('http://127.0.0.1/admin'))->toBeFalse();
    expect($guard->isSafe('http://[::1]/'))->toBeFalse();
    expect($guard->resolve('http://93.184.216.34:81/x'))->toBe(['scheme' => 'http', 'host' => '93.184.216.34', 'port' => 81, 'ips' => ['93.184.216.34']]);
    expect($guard->resolve('http://[2606:2800:220:1::1]/')['host'])->toBe('2606:2800:220:1::1');
});

test('curlResolve builds CURLOPT_RESOLVE entries for every validated address', function () {
    $guard = fakeGuard(['example.com' => ['93.184.216.34', '2606:2800:220:1::1']]);
    $target = $guard->resolve('https://example.com:8443/x');

    expect($guard->curlResolve($target))->toBe([
        'example.com:8443:93.184.216.34',
        'example.com:8443:[2606:2800:220:1::1]',
    ]);
});

test('a host that changes its answer after resolve cannot change the pinned addresses', function () {
    $dns = ['rebind.example' => ['93.184.216.34']];
    $guard = new class($dns) extends UrlGuard
    {
        public function __construct(public array $dns) {}

        protected function resolveHost(string $host): array
        {
            return $this->dns[$host] ?? [];
        }
    };
    $target = $guard->resolve('https://rebind.example/');
    $guard->dns['rebind.example'] = ['127.0.0.1'];

    expect($guard->curlResolve($target))->toBe(['rebind.example:443:93.184.216.34']);
    expect($guard->isSafe('https://rebind.example/'))->toBeFalse();
});
