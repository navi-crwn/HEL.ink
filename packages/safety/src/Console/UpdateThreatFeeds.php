<?php

namespace Hop\Safety\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads free, keyless threat feeds into storage/app/threat-feeds/:
 * URLhaus online malware URLs and the OpenPhish community phishing feed.
 * LinkSafety matches exact URLs (not hosts) so shared platforms aren't blocked.
 */
class UpdateThreatFeeds extends Command
{
    protected $signature = 'abuse:feeds';

    protected $description = 'Download URLhaus and OpenPhish threat feeds';

    public const FEEDS = [
        'malware' => 'https://urlhaus.abuse.ch/downloads/text_online/',
        'phishing' => 'https://openphish.com/feed.txt',
    ];

    public function handle(): int
    {
        $dir = storage_path('app/threat-feeds');
        File::ensureDirectoryExists($dir);
        $max = max(1, (int) config('safety.feed_max_bytes', 64 * 1024 * 1024));
        $failed = 0;
        foreach (self::FEEDS as $category => $url) {
            $download = tempnam($dir, $category.'.dl.');
            $staged = tempnam($dir, $category.'.new.');
            try {
                $this->download($url, $download, $max);
                $count = $this->stage($download, $staged);
                if ($count === 0) {
                    throw new \RuntimeException('the feed has no URLs');
                }
                chmod($staged, 0644);
                // Same directory, so the rename is atomic: readers see the old list or the new one, never half of it.
                if (! rename($staged, "{$dir}/{$category}.txt")) {
                    throw new \RuntimeException('could not replace the feed file');
                }
            } catch (\Throwable $e) {
                $this->warn("{$category}: {$e->getMessage()}");
                $failed++;

                continue; // keep the previous file
            } finally {
                File::delete([$download, $staged]);
            }
            $this->info("{$category}: {$count} URLs");
        }

        return $failed === count(self::FEEDS) ? self::FAILURE : self::SUCCESS;
    }

    /** Download $url into $path, giving up as soon as it is clear the body is bigger than $max bytes. */
    private function download(string $url, string $path, int $max): void
    {
        $tooBig = fn () => new \RuntimeException('the feed is larger than '.$max.' bytes');
        $exceeded = false;
        try {
            $response = Http::timeout(60)->withOptions([
                'sink' => $path,
                'curl' => [
                    // A non-zero return stops the transfer at once, instead of filling the disk until the timeout.
                    CURLOPT_NOPROGRESS => false,
                    CURLOPT_PROGRESSFUNCTION => function ($handle, $total, $downloaded) use ($max, &$exceeded) {
                        return ($exceeded = $total > $max || $downloaded > $max) ? 1 : 0;
                    },
                ],
            ])->get($url)->throw();
        } catch (\Throwable $e) {
            throw $exceeded ? $tooBig() : $e;
        }
        clearstatcache(true, $path);
        if ((int) filesize($path) > $max || (int) $response->header('Content-Length') > $max) {
            throw $tooBig();
        }
    }

    /** Write the normalized, de-duplicated URLs of the downloaded feed to $to; returns how many. */
    private function stage(string $from, string $to): int
    {
        $seen = [];
        $in = new \SplFileObject($from, 'r');
        while (! $in->eof()) {
            $line = trim((string) $in->fgets());
            // A feed is a list of URLs: comments, blanks and anything else (an HTML error page) are not.
            if ($line === '' || ! preg_match('#^https?://#i', $line)) {
                continue;
            }
            $seen[self::normalize($line)] = true;
        }
        file_put_contents($to, implode("\n", array_keys($seen)));

        return count($seen);
    }

    public static function normalize(string $url): string
    {
        return rtrim(strtolower(preg_replace('#^https?://(www\.)?#i', '', trim($url))), '/');
    }
}
