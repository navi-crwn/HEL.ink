<?php

use Hop\Safety\EncryptedNoteLink;

// Fake key only. The host list is passed in, so this runs without a Laravel app.
const NOTE_KEY = 'FAKEKEY0notehop0audit0000000000000000000';

function notes(): EncryptedNoteLink
{
    return new EncryptedNoteLink(['note.hel.ink', 'n.hel.ink']);
}

function intentFor(string $inner): string
{
    return 'intent://open/item#Intent;scheme=myapp;package=com.example.app;S.browser_fallback_url='.rawurlencode($inner).';end';
}

dataset('refused', [
    'baseline' => ['https://note.hel.ink/AbC123xyz0#k='.NOTE_KEY],
    'short host' => ['https://n.hel.ink/AbC123xyz0#k='.NOTE_KEY],
    'http' => ['http://note.hel.ink/x#k='.NOTE_KEY],
    'upper host and scheme' => ['HTTPS://NOTE.HEL.INK/x#k='.NOTE_KEY],
    'trailing dot' => ['https://note.hel.ink./x#k='.NOTE_KEY],
    'user info' => ['https://user@note.hel.ink/x#k='.NOTE_KEY],
    'decoy user info' => ['https://example.com@note.hel.ink/x#k='.NOTE_KEY],
    'port' => ['https://note.hel.ink:443/x#k='.NOTE_KEY],
    'empty port' => ['https://note.hel.ink:/x#k='.NOTE_KEY],
    'backslash ends the host' => ['https://note.hel.ink\\@example.com/x#k='.NOTE_KEY],
    'backslashes after the scheme' => ['https:\\\\note.hel.ink\\x#k='.NOTE_KEY],
    'tab in host' => ["https://note.hel\t.ink/x#k=".NOTE_KEY],
    'tab in the scheme' => ["ht\ttps://note.hel.ink/x#k=".NOTE_KEY],
    'newline in fragment' => ["https://note.hel.ink/x#k=\n".NOTE_KEY],
    'newline before k' => ["https://note.hel.ink/x#\nk=".NOTE_KEY],
    'owner token only' => ['https://note.hel.ink/x#a=ADMINTOKEN0FAKE0000000000000000000000000'],
    'a then k' => ['https://note.hel.ink/x#a=x&k='.NOTE_KEY],
    'new then k' => ['https://note.hel.ink/x#new=1&k='.NOTE_KEY],
    'percent-encoded name' => ['https://note.hel.ink/x#%6B='.NOTE_KEY],
    'leading space' => [' https://note.hel.ink/x#k='.NOTE_KEY],
    'leading control char' => ["\x01https://note.hel.ink/x#k=".NOTE_KEY],
    'another leading control char' => ["\x1fhttps://note.hel.ink/x#k=".NOTE_KEY],
    'www prefix' => ['https://www.note.hel.ink/x#k='.NOTE_KEY],
    'scheme-relative' => ['//note.hel.ink/x#k='.NOTE_KEY],
    'no scheme' => ['note.hel.ink/x#k='.NOTE_KEY],
    'no scheme with a port' => ['note.hel.ink:443/x#k='.NOTE_KEY],
    'one slash after the scheme' => ['https:/note.hel.ink/x#k='.NOTE_KEY],
    'no slash after the scheme' => ['https:note.hel.ink/x#k='.NOTE_KEY],
    'three slashes' => ['https:///note.hel.ink/x#k='.NOTE_KEY],
    'four slashes' => ['https:////note.hel.ink/x#k='.NOTE_KEY],
    'a scheme the forms put in front of another' => ['https://https:/note.hel.ink/x#k='.NOTE_KEY],
    'question mark before k (URLSearchParams drops it)' => ['https://note.hel.ink/x#?k='.NOTE_KEY],
    'question mark before k on the short host' => ['https://n.hel.ink/x#?k='.NOTE_KEY],
    'question mark before the owner token' => ['https://note.hel.ink/x#?a=ADMINTOKEN0FAKE0000000000000000000000000'],
    'full-width host letter' => ['https://ｎote.hel.ink/x#k='.NOTE_KEY],
    'full-width short host' => ['https://ｎ.hel.ink/x#k='.NOTE_KEY],
    'full-width whole host' => ['https://ｎｏｔｅ．ｈｅｌ．ｉｎｋ/x#k='.NOTE_KEY],
    'circled letter' => ["https://\u{24DD}ote.hel.ink/x#k=".NOTE_KEY],
    'double-struck letter' => ["https://\u{2115}ote.hel.ink/x#k=".NOTE_KEY],
    'ideographic full stop' => ['https://note。hel。ink/x#k='.NOTE_KEY],
    'full-width full stop' => ['https://note．hel．ink/x#k='.NOTE_KEY],
    'soft hyphen' => ["https://no\u{00AD}te.hel.ink/x#k=".NOTE_KEY],
    'zero-width space' => ["https://no\u{200B}te.hel.ink/x#k=".NOTE_KEY],
    'percent-encoded dot' => ['https://note%2ehel%2eink/x#k='.NOTE_KEY],
    'percent-encoded letter' => ['https://%6Eote.hel.ink/x#k='.NOTE_KEY],
    'android intent fallback' => [intentFor('https://note.hel.ink/x#k='.NOTE_KEY)],
    'android intent fallback with a full-width host' => [intentFor('https://ｎote.hel.ink/x#?k='.NOTE_KEY)],
]);

dataset('allowed', [
    'no fragment' => ['https://note.hel.ink/AbC123xyz0'],
    'empty k' => ['https://note.hel.ink/x#k='],
    'k only as a query' => ['https://note.hel.ink/x?k='.NOTE_KEY],
    'upper K (not read by note.js)' => ['https://note.hel.ink/x#K='.NOTE_KEY],
    'plus before the name' => ['https://note.hel.ink/x#+k='.NOTE_KEY],
    'two question marks' => ['https://note.hel.ink/x#??k='.NOTE_KEY],
    'a name that only ends in k' => ['https://note.hel.ink/x#xk='.NOTE_KEY],
    'a second # swallows the key' => ['https://note.hel.ink/x#x#k='.NOTE_KEY],
    'other fragment' => ['https://note.hel.ink/x#section-2'],
    'other host' => ['https://example.com/x#k='.NOTE_KEY],
    'subdomain of a note host' => ['https://evil.note.hel.ink/x#k='.NOTE_KEY],
    'look-alike host' => ['https://note.hel.ink.example.com/x#k='.NOTE_KEY],
    'look-alike suffix' => ['https://xnote.hel.ink/x#k='.NOTE_KEY],
    'look-alike label' => ['https://xn--note-.hel.ink/x#k='.NOTE_KEY],
    'note host only in the user info' => ['https://note.hel.ink@example.com/x#k='.NOTE_KEY],
    'note host after a backslash in the user info' => ['https://example.com\\@note.hel.ink/x#k='.NOTE_KEY],
    'a zero-width joiner (invalid in a browser)' => ["https://no\u{200D}te.hel.ink/x#k=".NOTE_KEY],
    'javascript scheme' => ['javascript:alert(1)//#k='.NOTE_KEY],
    'mailto' => ['mailto:a@note.hel.ink#k='.NOTE_KEY],
    'intent whose fallback is another site' => [intentFor('https://example.com/x#k='.NOTE_KEY)],
    'intent whose fallback has no key' => [intentFor('https://note.hel.ink/x')],
    'empty' => [''],
]);

it('refuses an encrypted note address however it is written', function (string $url) {
    expect(notes()->matches($url))->toBeTrue();
})->with('refused');

it('leaves every other address alone', function (string $url) {
    expect(notes()->matches($url))->toBeFalse();
})->with('allowed');

it('agrees with a WHATWG URL parser on 64 spellings, and never lets one through', function () {
    // tests/fixtures/note-link-variants.json: addresses with a made-up key; opens_note is what the WHATWG URL
    // parser (Node, tests/fixtures/note-link-truth.js) says: a browser would open a NoteHop host with a k or a secret.
    $variants = json_decode((string) file_get_contents(__DIR__.'/../fixtures/note-link-variants.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($variants)->toHaveCount(64);

    $leaks = $extra = [];
    foreach ($variants as $v) {
        $refused = notes()->matches($v['url']);
        if ($v['opens_note'] && ! $refused) {
            $leaks[] = $v['label'];
        }
        if (! $v['opens_note'] && $refused) {
            $extra[] = $v['label'];
        }
    }

    expect($leaks)->toBe([])
        // refused on purpose: the key is still in the stored address
        ->and($extra)->toBe(['fragment k=&k=KEY (empty then real)']);
});

it('finds an encrypted note address inside free text', function () {
    expect(notes()->containsKey('read it at https://note.hel.ink/x#k='.NOTE_KEY.' please'))->toBeTrue()
        ->and(notes()->containsKey('note.hel.ink/x#k='.NOTE_KEY))->toBeTrue()
        ->and(notes()->containsKey('(see https://n.hel.ink/x%23k='.NOTE_KEY.')'))->toBeTrue()
        ->and(notes()->containsKey("line one\nhttps://n.hel.ink/x#a=ADMINTOKEN0FAKE0000000000000000000000000\n"))->toBeTrue()
        ->and(notes()->containsKey('see https://ｎote.hel.ink/x#?k='.NOTE_KEY))->toBeTrue();
    expect(notes()->containsKey('my c# notes, issue #12 and https://example.com/#k='.NOTE_KEY))->toBeFalse()
        ->and(notes()->containsKey('https://note.hel.ink/x with no fragment'))->toBeFalse()
        ->and(notes()->containsKey(null))->toBeFalse();
});

it('finds the address in text that is not valid UTF-8 (a /u pattern would give up and let it through)', function () {
    $text = "caf\xE9 \xFF\xFE https://note.hel.ink/x#k=".NOTE_KEY;
    expect(mb_check_encoding($text, 'UTF-8'))->toBeFalse()
        ->and(notes()->containsKey($text))->toBeTrue()
        ->and(notes()->scrub($text))->not->toContain(NOTE_KEY);
});

it('finds an address hidden inside another address or an Android intent fallback', function () {
    $nested = 'https://example.com/go?to='.rawurlencode('https://note.hel.ink/x#k='.NOTE_KEY);
    $double = 'https://example.com/go?to='.rawurlencode(rawurlencode('https://n.hel.ink/x#k='.NOTE_KEY));
    expect(notes()->containsKey($nested))->toBeTrue()
        ->and(notes()->containsKey(intentFor('https://note.hel.ink/x#k='.NOTE_KEY)))->toBeTrue()
        ->and(notes()->containsKey($double))->toBeTrue()
        ->and(notes()->containsKey('https://example.com/go?to='.rawurlencode('https://example.com/x#k=1')))->toBeFalse();
});

it('cuts the whole fragment off', function () {
    expect(EncryptedNoteLink::withoutFragment('https://note.hel.ink/x#k='.NOTE_KEY))->toBe('https://note.hel.ink/x')
        ->and(EncryptedNoteLink::withoutFragment('https://example.com/x'))->toBe('https://example.com/x')
        ->and(EncryptedNoteLink::withoutFragment(null))->toBeNull();
});

it('takes only the secret parameters out of a fragment', function () {
    expect(EncryptedNoteLink::withoutSecrets('https://note.hel.ink/x#k='.NOTE_KEY))->toBe('https://note.hel.ink/x')
        ->and(EncryptedNoteLink::withoutSecrets('https://note.hel.ink/x#new=1&k='.NOTE_KEY.'&a=ADMIN'))->toBe('https://note.hel.ink/x#new=1')
        ->and(EncryptedNoteLink::withoutSecrets('https://note.hel.ink/x#?k='.NOTE_KEY.'&x=1'))->toBe('https://note.hel.ink/x#?x=1')
        ->and(EncryptedNoteLink::withoutSecrets('https://note.hel.ink/x#%6B='.NOTE_KEY))->toBe('https://note.hel.ink/x')
        ->and(EncryptedNoteLink::withoutSecrets("https://note.hel.ink/x#\nk=".NOTE_KEY))->toBe('https://note.hel.ink/x')
        ->and(EncryptedNoteLink::withoutSecrets('https://note.hel.ink/x#section'))->toBe('https://note.hel.ink/x#section')
        ->and(EncryptedNoteLink::withoutSecrets('https://note.hel.ink/x'))->toBe('https://note.hel.ink/x');
    $out = EncryptedNoteLink::withoutSecrets(intentFor('https://note.hel.ink/x#new=1&k='.NOTE_KEY));
    expect($out)->toBe(intentFor('https://note.hel.ink/x#new=1'))->and($out)->not->toContain(NOTE_KEY);
});

it('scrubs only the fragments of NoteHop addresses that carry a secret', function () {
    $text = 'see https://note.hel.ink/x#k='.NOTE_KEY.'&a=ADMIN0FAKE00 and https://example.com/page#a=1 and n.hel.ink/y%23k='.NOTE_KEY.' end';
    $out = notes()->scrub($text);
    expect($out)->toBe('see https://note.hel.ink/x and https://example.com/page#a=1 and n.hel.ink/y end')
        ->and($out)->not->toContain(NOTE_KEY)
        ->and(notes()->scrub('https://note.hel.ink/x#section'))->toBe('https://note.hel.ink/x#section')
        ->and(notes()->scrub('https://note.hel.ink/x#new=1&k='.NOTE_KEY))->toBe('https://note.hel.ink/x#new=1')
        ->and(notes()->scrub('see https://ｎote.hel.ink/x#?k='.NOTE_KEY))->toBe('see https://ｎote.hel.ink/x');
    $nested = 'https://example.com/go?to='.rawurlencode('https://note.hel.ink/x#k='.NOTE_KEY).' and '.intentFor('https://note.hel.ink/x#k='.NOTE_KEY);
    expect(notes()->scrub($nested))->not->toContain(NOTE_KEY)
        ->and(notes()->containsKey(notes()->scrub($nested)))->toBeFalse();
});

it('scrubs JSON columns whose escapes hide the host', function () {
    $json = json_encode(['url' => 'https://ｎote.hel.ink/x#k='.NOTE_KEY, 'other' => 'https://example.com/#a=1', 'n' => 3]);
    expect($json)->toContain('\\uff4e');
    $out = notes()->scrubJson($json);
    expect($out)->not->toContain(NOTE_KEY)
        ->and(json_decode($out, true))->toBe(['url' => 'https://ｎote.hel.ink/x', 'other' => 'https://example.com/#a=1', 'n' => 3])
        ->and(notes()->scrubJson('plain https://note.hel.ink/x#k='.NOTE_KEY))->toBe('plain https://note.hel.ink/x');
});

it('reads hosts from registered sources and ignores a source that throws', function () {
    EncryptedNoteLink::resolveHostsUsing(fn () => ['notes.example.org']);
    EncryptedNoteLink::resolveHostsUsing(fn () => throw new RuntimeException('no database yet'));
    try {
        $n = new EncryptedNoteLink;
        expect($n->hosts())->toContain('notes.example.org')
            ->and($n->matches('https://notes.example.org/x#k='.NOTE_KEY))->toBeTrue()
            ->and($n->matches("https://\u{FF4E}otes.example.org/x#?k=".NOTE_KEY))->toBeTrue();
    } finally {
        EncryptedNoteLink::resolveHostsUsing(null);
    }
});

it('compares a self-hosted domain given with capitals or a trailing dot the same way', function () {
    $n = new EncryptedNoteLink(['Geheim.Example.NET.', 'https://www.other.example/path']);
    expect($n->hosts())->toBe(['geheim.example.net', 'other.example'])
        ->and($n->matches('https://geheim.example.net/x#k='.NOTE_KEY))->toBeTrue()
        ->and($n->matches("https://geheim\u{FF0E}example.net/x#k=".NOTE_KEY))->toBeTrue()
        ->and($n->matches('https://other.example/x#?k='.NOTE_KEY))->toBeTrue();
});

it('normalises hosts the way a browser does', function () {
    expect(EncryptedNoteLink::normalizeHost('ＮＯＴＥ.hel.ink.'))->toBe('note.hel.ink')
        ->and(EncryptedNoteLink::normalizeHost('www.Note.Hel.Ink'))->toBe('note.hel.ink')
        ->and(EncryptedNoteLink::normalizeHost('[::1]'))->toBe('::1');
});

it('is built on ext-intl, which composer.json requires', function () {
    expect(function_exists('idn_to_ascii'))->toBeTrue()
        ->and(json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true)['require'])->toHaveKey('ext-intl');
});

it('refuses rather than guesses when ext-intl is missing and the host is not plain ASCII', function () {
    $noIntl = new class(['note.hel.ink']) extends EncryptedNoteLink
    {
        protected static function intlAvailable(): bool
        {
            return false;
        }
    };

    // plain forms are still read exactly (percent escapes do not need ext-intl) ...
    expect($noIntl::normalizeHost('NOTE%2Ehel.ink.'))->toBe('note.hel.ink')
        ->and($noIntl::normalizeHost('example.com'))->toBe('example.com')
        ->and($noIntl->matches('https://NOTE%2Ehel.ink/x#k='.NOTE_KEY))->toBeTrue()
        ->and($noIntl->matches('https://example.com/x#k='.NOTE_KEY))->toBeFalse()
        // ... anything that needs the UTS 46 table cannot be told apart from a NoteHop host, so it is unreadable and refused
        ->and($noIntl::normalizeHost('ｎote.hel.ink'))->toBeNull()
        ->and($noIntl->matches('https://ｎote.hel.ink/x#k='.NOTE_KEY))->toBeTrue()
        ->and($noIntl->matches("https://no\u{AD}te.hel.ink/x#?k=".NOTE_KEY))->toBeTrue()
        // while a non-ASCII host with nothing secret in the fragment is an ordinary address
        ->and($noIntl->matches('https://ｎote.hel.ink/x#section'))->toBeFalse();
});

it('does not choke on an address without a host', function (string $url) {
    expect(notes()->matches($url))->toBeFalse()
        ->and(notes()->containsKey($url))->toBeFalse()
        ->and(notes()->scrub($url))->toBe($url);
})->with(['bare fragment' => ['#k='.NOTE_KEY], 'empty host' => ['https://#k='.NOTE_KEY], 'only slashes' => ['https:///#k='.NOTE_KEY], 'issue number' => ['see issue #12'], 'empty user info host' => ['https://user@/x#k='.NOTE_KEY]]);
