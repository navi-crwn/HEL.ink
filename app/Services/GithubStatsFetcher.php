<?php

namespace App\Services;

use App\Models\GithubStat;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks GitHub for the numbers behind the home page ("Built in the open") and keeps
 * the last good answer per repo in github_stats. Runs from `hop:github-stats` only;
 * page views never reach this class.
 *
 * Every request carries the ETag of the last answer, so "nothing changed" (304) costs no
 * quota. A rate limit (403/429), a timeout, a 5xx or a missing repo never loses data:
 * the stored value of that field stays. The optional token (config hop.github.token) is
 * only ever sent as a header to the GitHub API; it is not logged or stored anywhere.
 */
class GithubStatsFetcher
{
    /** @var list<string> the endpoints whose ETag is kept, by name */
    private const ENDPOINTS = ['repo', 'languages', 'participation', 'commits', 'contributors', 'releases', 'release', 'tags'];

    /**
     * @return array<string, string> repo => updated | unchanged | missing | failed | rate-limited | skipped
     */
    public function refresh(): array
    {
        $out = [];
        $limited = false;
        foreach ((array) config('hop.github.repos', []) as $repo) {
            $repo = (string) $repo;
            if ($limited) {
                $out[$repo] = 'skipped';

                continue;
            }
            try {
                $out[$repo] = $this->refreshRepo($repo);
            } catch (GithubRateLimited) {
                $limited = true;
                $out[$repo] = 'rate-limited';
                Log::warning('github-stats: rate limited, keeping the last numbers', ['repo' => $repo]);
            } catch (\Throwable $e) {
                $out[$repo] = 'failed';
                Log::warning('github-stats: failed, keeping the last numbers', ['repo' => $repo, 'error' => $e::class]);
            }
        }

        return $out;
    }

    private function refreshRepo(string $repo): string
    {
        $row = GithubStat::firstOrNew(['repo' => $repo]);
        $data = (array) ($row->payload ?? []);
        $etags = (array) ($row->etags ?? []);
        $before = $data;

        // The repo itself: created, last push, stars. 404 = it does not exist (or is private).
        $r = $this->call($repo, '', [], $etags['repo'] ?? null);
        if ($r === null || ! in_array($r['status'], [200, 304], true)) {
            return $r && $r['status'] === 404 ? 'missing' : 'failed';
        }
        if ($r['status'] === 200 && is_array($r['json'])) {
            $data['created_at'] = $this->iso($r['json']['created_at'] ?? null);
            $data['pushed_at'] = $this->iso($r['json']['pushed_at'] ?? null);
            $data['stars'] = isset($r['json']['stargazers_count']) ? (int) $r['json']['stargazers_count'] : ($data['stars'] ?? null);
            $etags['repo'] = $r['etag'];
        }

        $r = $this->call($repo, '/languages', [], $etags['languages'] ?? null);
        if ($r && $r['status'] === 200 && is_array($r['json'])) {
            $data['languages'] = collect($r['json'])->filter(fn ($b) => is_int($b) && $b > 0)->all();
            $etags['languages'] = $r['etag'];
        }

        // 52 weeks of commits. GitHub answers 202 while it computes them: keep the old value.
        $r = $this->call($repo, '/stats/participation', [], $etags['participation'] ?? null);
        if ($r && $r['status'] === 200 && is_array($r['json']['all'] ?? null) && $r['json']['all'] !== []) {
            $data['weeks'] = array_map('intval', array_slice(array_values($r['json']['all']), -52));
            $etags['participation'] = $r['etag'];
        }

        foreach (['commits' => 'commits', 'contributors' => 'contributors', 'releases' => 'releases'] as $key => $path) {
            $query = ['per_page' => 1] + ($key === 'contributors' ? ['anon' => 1] : []);
            $r = $this->call($repo, '/'.$path, $query, $etags[$key] ?? null);
            if ($r && $r['status'] === 200 && is_array($r['json'])) {
                $data[$key] = $this->countFrom($r['link'], count($r['json']));
                $etags[$key] = $r['etag'];
            } elseif ($r && $r['status'] === 204 && $key === 'contributors') {
                $data[$key] = 0;
            }
        }

        // The version: the latest release; without one, the newest tag.
        $r = $this->call($repo, '/releases/latest', [], $etags['release'] ?? null);
        if ($r && $r['status'] === 200 && is_array($r['json']) && ! empty($r['json']['tag_name'])) {
            $data['version'] = (string) $r['json']['tag_name'];
            $data['version_date'] = $this->iso($r['json']['published_at'] ?? $r['json']['created_at'] ?? null);
            $etags['release'] = $r['etag'];
        } elseif ($r && $r['status'] === 404) {
            unset($etags['release']);
            $t = $this->call($repo, '/tags', ['per_page' => 1], $etags['tags'] ?? null);
            if ($t && $t['status'] === 200 && is_array($t['json'])) {
                $data['version'] = $t['json'][0]['name'] ?? null;
                $data['version_date'] = null;
                $etags['tags'] = $t['etag'];
            }
        }

        $data['repo'] = $repo;
        $row->payload = $data;
        $row->etags = array_intersect_key(array_filter($etags), array_flip(self::ENDPOINTS));
        $row->fetched_at = now();
        $row->save();

        return $before === $data ? 'unchanged' : 'updated';
    }

    /**
     * One GET. null when the request failed (timeout, 5xx, network): the caller keeps old data.
     *
     * @param  array<string, scalar>  $query
     * @return array{status: int, json: mixed, etag: ?string, link: ?string}|null
     *
     * @throws GithubRateLimited
     */
    private function call(string $repo, string $path, array $query, ?string $etag): ?array
    {
        $headers = ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'HOP-hub-stats/1'];
        if ($etag) {
            $headers['If-None-Match'] = $etag;
        }
        $request = Http::withHeaders($headers)->timeout((int) config('hop.github.timeout', 10));
        if ($token = (string) config('hop.github.token')) {
            $request = $request->withToken($token);
        }
        try {
            /** @var Response $res */
            $res = $request->get(rtrim((string) config('hop.github.api'), '/').'/repos/'.$repo.$path, $query);
        } catch (\Throwable) {
            return null;
        }
        $status = $res->status();
        if ($status === 403 || $status === 429) {
            throw new GithubRateLimited;
        }
        if ($status >= 500) {
            return null;
        }

        return [
            'status' => $status,
            'json' => $status === 200 ? $res->json() : null,
            'etag' => $res->header('ETag') ?: null,
            'link' => $res->header('Link') ?: null,
        ];
    }

    /** With per_page=1 the page number of rel="last" is the total; without a Link header the body is the total. */
    private function countFrom(?string $link, int $bodyCount): int
    {
        if ($link && preg_match('/[?&]page=(\d+)[^>]*>;\s*rel="last"/', $link, $m)) {
            return (int) $m[1];
        }

        return $bodyCount;
    }

    private function iso(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->utc()->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
