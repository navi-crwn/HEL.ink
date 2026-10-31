<?php

/*
 * INACTIVE and empty by default: no legal text or binding version is shipped.
 * A published descriptor must name a local artifact, its lowercase SHA-256,
 * and an RFC3339 publication time that is not in the future. Never reuse a
 * version for changed content. See docs/legal-gate.md.
 */
return [
    // Only artifacts below this directory can be served or accepted. Remote
    // URLs are not supported. Tests override this with synthetic fixtures.
    'artifact_directory' => resource_path('legal'),

    'terms' => [
        'required_version' => env('LEGAL_TERMS_REQUIRED_VERSION'),

        'published' => [
            '2026-10-31' => [
                'file' => 'terms-2026-10-31.md',
                'sha256' => 'a838dc17fb1d00b904c97b9c5b07bad26eb87fa3c72d45a87c5b2f768f6d11a5',
                'published_at' => '2026-10-31T00:00:00Z',
            ],
        ],
    ],
];
