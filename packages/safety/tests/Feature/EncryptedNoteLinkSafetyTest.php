<?php

use Hop\Safety\EncryptedNoteLink;
use Hop\Safety\LinkSafety;
use Illuminate\Support\Facades\Http;

// Fake key only.
const SAFETY_KEY = 'FAKEKEY0notehop0audit0000000000000000000';

// rejectionReason() asks the online providers; keep the DNS one (Spamhaus) off so these tests never touch the network.
beforeEach(fn () => config(['safety.providers.spamhaus_dbl' => false]));

it('refuses an encrypted note address as a destination and as text', function () {
    $safety = app(LinkSafety::class);
    expect($safety->rejectionReason('https://note.hel.ink/x#k='.SAFETY_KEY))->toBe(EncryptedNoteLink::MESSAGE)
        ->and($safety->rejectionReason('https://ｎote.hel.ink/x#k='.SAFETY_KEY, '', ['http', 'https', 'mailto']))->toBe(EncryptedNoteLink::MESSAGE)
        ->and($safety->rejectionReason(null, 'quoted https://n.hel.ink/x#a=ADMIN0FAKE00'))->toBe(EncryptedNoteLink::MESSAGE)
        ->and($safety->rejectionReason('https://note.hel.ink/x'))->toBeNull()
        ->and($safety->rejectionReason('https://example.com/#k='.SAFETY_KEY))->toBeNull();
});

it('refuses every spelling a browser opens as the NoteHop host, the way the app calls it', function () {
    $safety = app(LinkSafety::class);
    $variants = json_decode((string) file_get_contents(__DIR__.'/../fixtures/note-link-variants.json'), true, flags: JSON_THROW_ON_ERROR);

    $leaks = $extra = [];
    foreach ($variants as $v) {
        $refused = $safety->rejectionReason($v['url'], '', ['http', 'https']) === EncryptedNoteLink::MESSAGE;
        if ($v['opens_note'] && ! $refused) {
            $leaks[] = $v['label'];
        }
        if (! $v['opens_note'] && $refused) {
            $extra[] = $v['label'];
        }
    }

    expect($leaks)->toBe([])
        // refused on purpose (the key is still in the stored address): an empty k followed by a real one, a key
        // behind an encoded # in the path, and a NoteHop address that only sits in another address's query
        ->and($extra)->toBe(['fragment k=&k=KEY (empty then real)', '%23k= in path', 'note host only in query']);
});

it('reports the signal and gives it no weight', function () {
    $r = app(LinkSafety::class)->assess('https://note.hel.ink/x#k='.SAFETY_KEY);
    expect($r['signals'])->toContain('encrypted_note_key')->and($r['score'])->toBe(0);
    expect(config('safety.block_categories'))->toContain('encrypted_note_key');
});

it('takes the note hosts from config, so a self-hosted install is covered', function () {
    config(['safety.note_hosts' => ['secrets.example.net']]);
    $safety = app(LinkSafety::class);

    expect($safety->rejectionReason('https://secrets.example.net/x#?k='.SAFETY_KEY))->toBe(EncryptedNoteLink::MESSAGE)
        ->and($safety->rejectionReason("https://\u{FF53}ecrets.example.net/x#k=".SAFETY_KEY))->toBe(EncryptedNoteLink::MESSAGE);
});
