<?php

// The hub serves the HOP asset manifest itself (public/hop-assets/apps.json), so it reads
// the bundled copy in packages/ui instead of fetching its own URL. scripts/hop_assets.py
// check keeps that copy identical to the published manifest.
return [
    'assets' => [
        'remote' => false,
    ],
];
