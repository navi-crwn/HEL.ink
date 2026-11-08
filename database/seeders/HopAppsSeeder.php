<?php

namespace Database\Seeders;

use App\Support\EventRegistry;
use Illuminate\Database\Seeder;

/**
 * Makes sure PixelHop, NoteHop, FileHop, LinkHop and BioHop have a row in `apps` with the `events` capability, so the
 * hub's account events reach them. Safe to run again and in production: a row that exists is left alone.
 * Secrets come from the environment (HOP_SSO_SECRET_<APP>, or the shared HOP_SSO_SECRET for LinkHop and
 * BioHop), never from here. Run it after LinkHop and BioHop run hop/auth's event receiver.
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
