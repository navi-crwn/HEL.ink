<?php

namespace App\Support;

use App\Models\GithubStat;

/**
 * The stored GitHub numbers for the home page and /stats/github.json. Reads the
 * database only (written by hop:github-stats): a page view never calls GitHub.
 * Without data (nothing fetched yet, or no table on a fresh dev database) it returns
 * the empty state, never zeros.
 */
class GithubStats
{
    /**
     * @return array{fetched_at: ?string, repos: list<array<string, mixed>>}
     */
    public function all(): array
    {
        try {
            $rows = GithubStat::query()->get()->keyBy('repo');
        } catch (\Throwable) {
            return ['fetched_at' => null, 'repos' => []];
        }
        $repos = [];
        $latest = null;
        foreach ((array) config('hop.github.repos', []) as $repo) {
            $row = $rows->get($repo);
            $data = $row?->payload;
            if (! is_array($data) || $data === []) {
                continue;
            }
            $repos[] = [
                'repo' => (string) $repo,
                'name' => substr((string) strrchr('/'.$repo, '/'), 1),
                'url' => 'https://github.com/'.$repo,
                'created_at' => $data['created_at'] ?? null,
                'pushed_at' => $data['pushed_at'] ?? null,
                'stars' => $this->int($data['stars'] ?? null),
                'commits' => $this->int($data['commits'] ?? null),
                'releases' => $this->int($data['releases'] ?? null),
                'contributors' => $this->int($data['contributors'] ?? null),
                'version' => isset($data['version']) ? (string) $data['version'] : null,
                'version_date' => $data['version_date'] ?? null,
                'languages' => $this->languages((array) ($data['languages'] ?? [])),
                'weeks' => $this->weeks($data['weeks'] ?? null),
                'fetched_at' => $row->fetched_at?->toIso8601String(),
            ];
            if ($row->fetched_at && (! $latest || $row->fetched_at->gt($latest))) {
                $latest = $row->fetched_at;
            }
        }

        return ['fetched_at' => $latest?->toIso8601String(), 'repos' => $repos];
    }

    /** @return list<array{name: string, percent: float}> the four biggest languages and "Other", percentages of bytes. */
    private function languages(array $bytes): array
    {
        arsort($bytes);
        $total = array_sum($bytes);
        if ($total <= 0) {
            return [];
        }
        $out = [];
        $other = 0;
        $i = 0;
        foreach ($bytes as $name => $n) {
            if ($i++ < 4) {
                $out[] = ['name' => (string) $name, 'percent' => round($n * 100 / $total, 1)];
            } else {
                $other += $n;
            }
        }
        if ($other > 0) {
            $out[] = ['name' => 'Other', 'percent' => round($other * 100 / $total, 1)];
        }

        return $out;
    }

    /** @return list<int>|null */
    private function weeks(mixed $weeks): ?array
    {
        if (! is_array($weeks) || $weeks === []) {
            return null;
        }

        return array_map('intval', array_values($weeks));
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
