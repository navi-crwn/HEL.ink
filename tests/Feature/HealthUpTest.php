<?php

// Apps probe the hub's /up (2xx, no session) before a silent account re-check; it must stay a plain health route.
test('/up answers 2xx without a session on every host', function () {
    foreach (['https://hub.hel.test/up', 'https://hel.test/up', 'http://localhost/up'] as $url) {
        $this->get($url)->assertSuccessful();
    }
});
