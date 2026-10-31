<?php

namespace App\Http\Controllers;

use App\Support\Legal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use LogicException;

class LegalArtifactController extends Controller
{
    public function show(string $version): Response|JsonResponse
    {
        try {
            $artifact = Legal::artifact($version);
        } catch (LogicException) {
            return response()->json(['error' => 'artifact_unavailable'], 503);
        }
        if ($artifact === null) {
            abort(404);
        }

        // Serve artifacts as inert text; HTML source is content, not executable UI.
        $type = 'text/plain';

        return response($artifact['contents'], 200, [
            'Content-Type' => $type.'; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'ETag' => '"'.$artifact['sha256'].'"',
        ]);
    }

    public function metadata(): JsonResponse
    {
        try {
            return response()->json(Legal::consumerMetadata())
                ->header('Cache-Control', 'no-store, private');
        } catch (LogicException) {
            return response()->json(['error' => 'not_configured', 'message' => 'Terms metadata is temporarily unavailable.'], 503)
                ->header('Cache-Control', 'no-store, private');
        }
    }
}
