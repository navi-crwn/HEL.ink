# Legal acceptance gate

This gate records the exact local terms artifact accepted when a **new account** is created. It does not claim legal compliance and does not publish or approve legal wording. No binding legal text is shipped; the registry is empty and the only artifacts under `tests/Fixtures` are synthetic.

## Deployment remains inactive by default

With no required version, web, Google OAuth, and app-delegated registration retain their previous behavior, no acceptance is created, and metadata is `{"active":false,"terms":null}`.

Activation requires an owner-approved immutable artifact and an explicit deployment configuration:

```php
'terms' => [
    'required_version' => 'VERSION_IDENTIFIER',
    'published' => [
        'VERSION_IDENTIFIER' => [
            'file' => 'terms-VERSION_IDENTIFIER.md',
            'sha256' => 'SHA256_OF_EXACT_FILE_BYTES',
            'published_at' => 'RFC3339_TIMESTAMP',
        ],
    ],
],
```

`legal.artifact_directory` is the only trusted local root (default `resources/legal`). A filename is a simple `.txt`, `.md`, or `.html` basename: traversal, absolute paths, remote URLs, symlink escapes, missing files, malformed descriptors/digests, invalid timestamps, and future publication times fail closed. The server reads only this local file; it never fetches a supplied or registered remote URL. Artifacts are served as inert text with `nosniff`.

At publication, independently calculate the lowercase SHA-256 over the exact bytes and retain a durable copy. Every acceptance rechecks that digest and stores it with version, policy, source, and acceptance time. Existing acceptance rows are never updated to a new digest. Descriptor or content drift returns 503 for artifact/metadata, blocks account creation, and does **not** silently publish or accept replacement text.

**Never reuse a published version for changed content.** Any byte change requires a new version, filename/digest descriptor, and publication time. Do not edit a descriptor or artifact in place to make drift disappear; investigate and restore the original bytes. Removing an old version from the registry makes its route unavailable but does not erase recorded acceptance evidence. No production migration or activation has been performed in this change.

## Versioned artifact and staged consumer discovery

Users follow exact-version links on both email signup and Google consent to `GET /legal/terms/{version}` on the same hub origin. A valid registered artifact is `Cache-Control: public, max-age=31536000, immutable`; unknown versions are 404 and invalid registered artifacts are 503. The generic marketing `/terms` page remains separate and is not an acceptance binding.

Staged app consumers discover `GET /api/legal/terms`. This unsigned public endpoint returns only `active` and, when active, terms `policy`, `version`, same-origin `url`, `sha256`, and `published_at`; no contents, secrets, or user identity are exposed. Metadata is `Cache-Control: no-store, private`. Fetch it before presenting acceptance, after app restart, and after any version validation failure; never treat cached metadata as a permanent registry. Consumers must not invent, reuse an older, or select an arbitrary version. Active invalid configuration returns 503, never a guessed valid version.

For signed app registration:

- **Gate inactive:** omit `terms_version`. Existing clients remain compatible and successful responses omit `terms`.
- **Gate active:** send the discovered exact `terms_version` in the signed JSON body with `tos_accepted`. Missing/mismatched versions receive 422 plus current structured `legal` metadata; success includes the acceptance, including its retained digest.
- **Active but invalid:** registration returns 503 and creates no account.

The web form carries a server-provided version but the server validates it against current configuration. Terms acceptance remains separate from any future privacy policy; only the `terms` policy is registered or recorded here.

## OAuth and transactions

Google sign-up uses explicit CSRF-protected consent. The session contains one structured value with `version` and `at`; callback consumes it once, requires exact current-version match, and rejects consent older than 600 seconds. Missing, expired, replayed, or mismatched consent cannot create a new account. No real OAuth request is needed by synthetic tests.

User creation and its digest-bearing acceptance record share a transaction on all three creation paths. Login, notifications, mail, and other post-creation effects occur only after transaction success.

## Shared-database migration and rollback

`up()` **fails loudly if `legal_acceptances` already exists**. It never adopts a table whose provenance/schema is unknown and never silently accepts drift. An operator must inspect a preexisting shared table and plan a separate, reviewed reconciliation rather than rerun this migration.

`down()` is intentionally non-destructive: it never drops acceptance evidence or a preexisting shared table. For an isolated disposable test database, discard that entire database through its owner-managed test lifecycle. Any future shared-database rollback must be separately reviewed and non-destructive; do not change this to `dropIfExists`.
