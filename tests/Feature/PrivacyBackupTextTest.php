<?php

use Hop\Ui\Locales;

$key = 'Backup copies are not edited: copies kept on our own server are deleted automatically after 14 days.';

test('the privacy page states the local backup period and no longer promises six months', function () {
    $html = $this->get('/privacy')->assertOk()->getContent();

    expect($html)->toContain('after 14 days')->not->toContain('six months');
});

test('the backup sentence is translated in all nine languages', function () use ($key) {
    $en = collect(json_decode(file_get_contents(base_path('lang/en.json')), true));
    expect($en->keys()->contains(fn ($k) => str_contains($k, 'Backup copies are not edited')))->toBeTrue();

    foreach (Locales::codes() as $code) {
        $catalog = json_decode(file_get_contents(base_path("lang/{$code}.json")), true);
        $entries = array_filter($catalog, fn ($v, $k) => str_contains($k, 'Backup copies are not edited'), ARRAY_FILTER_USE_BOTH);
        expect($entries)->toHaveCount(1);
        $text = reset($entries);
        expect($text)->toContain('14')->not->toMatch('/six months|sechs Monat|enam bulan|6 か月|seis meses|seis mes|six mois|шести месяц|六个月/u');
        if ($code !== 'en') {
            expect($text)->not->toContain($key);
        }
    }
});

test('the 14 days on the privacy page match the backup script default', function () {
    expect(file_get_contents(base_path('scripts/backup.sh')))->toContain('HOP_BACKUP_KEEP_DAYS:-14');
});
