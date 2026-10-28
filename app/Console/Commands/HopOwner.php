<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class HopOwner extends Command
{
    protected $signature = 'hop:owner {email : The account that owns HOP}';

    protected $description = 'Give an account the owner role (admin of every HOP app)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }
        $user->roles()->firstOrCreate(['role' => Role::OWNER, 'app_slug' => null]);
        $this->info($user->email.' is now the owner.');

        return self::SUCCESS;
    }
}
