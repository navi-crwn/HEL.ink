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
            '2026-11-11' => [
                'file' => 'terms-2026-11-11.md',
                'sha256' => '3cdc133152c74582416366a03d590c34ca66efe1bda13fea706a673c06c51c3e',
                'published_at' => '2026-11-11T00:00:00Z',
            ],
            '2026-10-31' => [
                'file' => 'terms-2026-10-31.md',
                'sha256' => '4ca047cb41f0b32310fee38f8834d70a83ef5cecfe98fa3bf138481fbd56a7e3',
                'published_at' => '2026-10-31T00:00:00Z',
            ],
        ],
    ],
];
