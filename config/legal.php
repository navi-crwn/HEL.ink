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
            // 'VERSION' => [
            //     'file' => 'terms-VERSION.md',
            //     'sha256' => '64-lowercase-hex-characters',
            //     'published_at' => 'YYYY-MM-DDTHH:MM:SSZ',
            // ],
        ],
    ],
];
