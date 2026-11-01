<?php

namespace App\Http\Controllers;

use App\Support\GithubStats;
use App\Support\UptimeStats;
use Illuminate\Http\JsonResponse;

/**
 * /stats/github.json and /stats/uptime.json: the numbers behind "Built in the open".
 * Public and numbers only (repo names, counts, dates, percentages); everything comes
 * from the database, so a request never reaches GitHub or the apps.
 */
class StatsController extends Controller
{
    public function github(GithubStats $stats): JsonResponse
    {
        return $this->json($stats->all());
    }

    public function uptime(UptimeStats $stats): JsonResponse
    {
        return $this->json($stats->all());
    }

    private function json(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'public, max-age=300');
    }
}
