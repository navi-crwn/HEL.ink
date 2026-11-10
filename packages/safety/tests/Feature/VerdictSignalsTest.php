<?php

use Hop\Safety\LinkSafety;

// The URL-only signals the verdict engine adds to LinkSafety::assess(), so every app scores a link the same way.

beforeEach(fn () => config(['safety.providers.spamhaus_dbl' => false]));

it('scores a temporary tunnel host as a hard block', function (string $url) {
    $r = app(LinkSafety::class)->assess($url);

    expect($r['signals'])->toContain('tunnel_host')
        ->and($r['score'])->toBeGreaterThanOrEqual(100)
        ->and(config('safety.block_categories'))->toContain('tunnel_host');
})->with([
    'ngrok free' => ['https://1234-5678.ngrok-free.app/login'],
    'ngrok io' => ['https://abcd.ngrok.io/'],
    'cloudflare quick tunnel' => ['https://quiet-river-cat.trycloudflare.com/'],
    'localtunnel' => ['https://brave-cats-sing.loca.lt/'],
    'serveo' => ['https://demo.serveo.net/'],
    'localhost run' => ['https://x7k2.localhost.run/'],
    'upper case' => ['https://ABCD.NGROK-FREE.APP/'],
]);

it('does not call the tunnel providers own sites tunnels', function (string $url) {
    expect(app(LinkSafety::class)->assess($url)['signals'])->not->toContain('tunnel_host');
})->with([
    'ngrok docs' => ['https://ngrok.com/docs'],
    'cloudflare' => ['https://www.cloudflare.com/'],
    'look-alike' => ['https://notngrok-free.app/'],
]);

it('scores an executable download as a hard block', function (string $url) {
    $r = app(LinkSafety::class)->assess($url);

    expect($r['signals'])->toContain('executable_download')
        ->and($r['score'])->toBeGreaterThanOrEqual(100)
        ->and(config('safety.block_categories'))->toContain('executable_download');
})->with([
    'apk' => ['https://files.example.com/app.apk'],
    'exe' => ['https://files.example.com/setup.exe'],
    'scr' => ['https://files.example.com/photo.scr'],
    'msi' => ['https://files.example.com/x.msi'],
    'bat' => ['https://files.example.com/run.bat'],
    'cmd' => ['https://files.example.com/run.cmd'],
    'ps1' => ['https://files.example.com/run.ps1'],
    'jar' => ['https://files.example.com/app.jar'],
    'with query' => ['https://files.example.com/app.APK?dl=1'],
]);

it('recognises every high-risk TLD, including two-label ones, from one configured list', function (string $url) {
    $r = app(LinkSafety::class)->assess($url);

    expect($r['signals'])->toContain('risky_tld');
})->with([
    'xyz' => ['https://promo.example.xyz/'],
    'top' => ['https://example.top/'],
    'click' => ['https://example.click/'],
    'icu' => ['https://bgp09.icu/'],
    'buzz' => ['https://example.buzz/'],
    'sbs' => ['https://example.sbs/'],
    'my.id' => ['https://hadiah.my.id/'],
    'biz.id' => ['https://undian.biz.id/'],
    'cfd' => ['https://example.cfd/'],
    'rest' => ['https://example.rest/'],
    'zip' => ['https://example.zip/'],
    'mov' => ['https://example.mov/'],
]);

it('does not call an ordinary Indonesian domain a risky TLD', function (string $url) {
    expect(app(LinkSafety::class)->assess($url)['signals'])->not->toContain('risky_tld');
})->with([
    'co.id' => ['https://bca.co.id/'],
    'id' => ['https://dana.id/'],
    'go.id' => ['https://kominfo.go.id/'],
    'label named like a tld' => ['https://xyz.example.com/'],
]);

it('gives a risky TLD the weight of the owner design', function () {
    expect(config('safety.weights.risky_tld'))->toBe(30);
});

it('scores an Indonesian bank, wallet or institution name on an unofficial host or path as brand spoofing', function (string $url) {
    $r = app(LinkSafety::class)->assess($url);

    expect($r['signals'])->toContain('brand_spoof');
})->with([
    'klikbca subdomain' => ['https://klikbca.com.example.xyz/'],
    'brimo in a label' => ['https://brimo-update.example.com/'],
    'livin label' => ['https://livin.example.net/'],
    'mandiri inside a label' => ['https://bankmandiri-verifikasi.example.com/'],
    'bni as a label' => ['https://bni.example.com/'],
    'bsi as a path segment' => ['https://example.com/bsi/login'],
    'dana as a path segment' => ['https://example.com/dana'],
    'kaget' => ['https://example.com/kaget-100k'],
    'ovo label' => ['https://ovo.example.com/'],
    'gopay path' => ['https://example.com/gopay'],
    'shopeepay inside a label' => ['https://shopeepay-promo.example.com/'],
    'telegram inside a label' => ['https://telegramweb.example.com/'],
    'whatsapp inside a path segment' => ['https://example.com/whatsapp-verification'],
    'kominfo inside a label' => ['https://kominfo-blokir.example.com/'],
    'undian' => ['https://example.com/undian'],
    'blokir' => ['https://example.com/blokir'],
    'tarif' => ['https://example.com/tarif'],
    'upper case' => ['https://KLIKBCA.example.com/'],
    'percent encoded path' => ['https://example.com/%62%6e%69/login'],
]);

it('does not flag the official domain or its subdomains', function (string $url) {
    expect(app(LinkSafety::class)->assess($url)['signals'])->not->toContain('brand_spoof');
})->with([
    'klikbca' => ['https://klikbca.com/'],
    'bca' => ['https://www.bca.co.id/id/klikbca'],
    'bri' => ['https://bri.co.id/brimo'],
    'mandiri' => ['https://bankmandiri.co.id/livin'],
    'bni' => ['https://bni.co.id/'],
    'bsi' => ['https://www.bankbsi.co.id/'],
    'dana' => ['https://dana.id/kaget'],
    'ovo' => ['https://ovo.id/'],
    'gopay' => ['https://gopay.co.id/'],
    'shopee' => ['https://shopee.co.id/shopeepay'],
    'telegram' => ['https://telegram.org/'],
    'telegram handle' => ['https://t.me/telegram'],
    'whatsapp' => ['https://www.whatsapp.com/'],
    'wa.me' => ['https://wa.me/6281234567890'],
    'kominfo' => ['https://kominfo.go.id/'],
    'komdigi' => ['https://komdigi.go.id/'],
    'subdomain of official' => ['https://m.klikbca.com/login'],
]);

it('only matches a short brand word as a whole label or path segment', function (string $url) {
    expect(app(LinkSafety::class)->assess($url)['signals'])->not->toContain('brand_spoof');
})->with([
    'bandana' => ['https://bandana.com/'],
    'bandana path' => ['https://example.com/bandana'],
    'ovoid' => ['https://ovoid.example.com/'],
    'bsides' => ['https://bsides.example.com/'],
    'tarifa' => ['https://example.com/tarifa-beach'],
    'omnibus' => ['https://omnibus.example.com/'],
    'living' => ['https://living.example.com/'],
    'brimonte' => ['https://brimonte.example.com/'],
    'kagetsu' => ['https://example.com/kagetsu'],
    'gopayments' => ['https://example.com/gopayments'],
    'undianta' => ['https://example.com/undianta'],
    'blokiran' => ['https://example.com/blokiran'],
]);

it('does not flag a look-alike of an official domain', function (string $url) {
    expect(app(LinkSafety::class)->assess($url)['signals'])->toContain('brand_spoof');
})->with([
    'suffix only' => ['https://notklikbca.com/'],
    'official domain as a label' => ['https://klikbca.com.evil.example/'],
    'official domain in the path' => ['https://evil.example/klikbca.com/login'],
]);

it('gives brand spoofing the weight of the owner design without refusing it at creation time', function () {
    expect(config('safety.weights.brand_spoof'))->toBe(55)
        ->and(config('safety.block_categories'))->not->toContain('brand_spoof');
});
