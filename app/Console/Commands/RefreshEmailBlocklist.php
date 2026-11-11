<?php

namespace App\Console\Commands;

use App\Support\EmailDomains;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Fetches the disposable-email-domains list and keeps the last good copy when the download fails or looks wrong. Without
 * options it writes the server's copy (storage/framework); with --snapshot it rewrites the file committed in the repository,
 * which is what to run before a release.
 */
class RefreshEmailBlocklist extends Command
{
    protected $signature = 'hop:email-blocklist:refresh {--snapshot : write the committed snapshot instead of the server copy (run before a release)}';

    protected $description = 'Refresh the list of throwaway e-mail domains the hub refuses';

    private const MIN_BYTES = 20000;

    private const MIN_DOMAINS = 1000;

    public function handle(): int
    {
        $url = (string) config('hop.email.blocklist_url');
        try {
            $res = Http::timeout(30)->get($url);
        } catch (\Throwable $e) {
            return $this->keep('the download failed ('.class_basename($e).')');
        }
        if (! $res->successful()) {
            return $this->keep('the source answered '.$res->status());
        }
        $body = (string) $res->body();
        $lines = array_filter(array_map('trim', preg_split('/\R/', $body) ?: []), fn ($l) => $l !== '' && $l[0] !== '#');
        $domains = EmailDomains::parse($body);
        if (strlen($body) < self::MIN_BYTES || count($domains) < self::MIN_DOMAINS || count($domains) < 0.95 * count($lines)) {
            return $this->keep('the answer does not look like the list ('.count($domains).' domains from '.count($lines).' lines)');
        }
        $target = $this->option('snapshot') ? EmailDomains::snapshotPath() : EmailDomains::refreshedPath();
        $before = is_file($target) ? count(EmailDomains::parse((string) file_get_contents($target))) : 0;
        if ($before > 0 && count($domains) < 0.5 * $before) {
            return $this->keep('the new list is less than half of the current one ('.count($domains).' against '.$before.')');
        }
        $tmp = $target.'.tmp';
        if (file_put_contents($tmp, implode("\n", $domains)."\n") === false || ! rename($tmp, $target)) {
            @unlink($tmp);

            return $this->keep('the file could not be written');
        }
        EmailDomains::forget();
        $this->info(count($domains).' domains written to '.$target.'.');
        if ($this->option('snapshot')) {
            $this->line('Update the commit and the date in resources/data/disposable_email_blocklist.NOTICE.md, then commit both files.');
        }

        return self::SUCCESS;
    }

    private function keep(string $why): int
    {
        $this->error('The list was not changed: '.$why.'.');

        return self::FAILURE;
    }
}
