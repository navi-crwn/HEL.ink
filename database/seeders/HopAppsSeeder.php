<?php

namespace Database\Seeders;

use App\Support\EventRegistry;
use Illuminate\Database\Seeder;

/**
 * Makes sure PixelHop and NoteHop have a row in `apps` with the `events` capability, so the hub's
 * account events reach them. Safe to run again and in production: a row that exists is left alone.
 * Secrets come from the environment (HOP_SSO_SECRET_PIXELHOP, HOP_SSO_SECRET_NOTEHOP), never from here.
 */
class HopAppsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (EventRegistry::ensure() as $slug => $result) {
            $this->command?->line(sprintf('%-10s %s', $slug, $result));
        }
        foreach (EventRegistry::warnings() as $warning) {
            $this->command?->warn($warning);
        }
    }
}
