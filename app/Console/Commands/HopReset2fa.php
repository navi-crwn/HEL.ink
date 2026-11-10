<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;

class HopReset2fa extends Command
{
    protected $signature = 'hop:reset-2fa {email : The account whose two-factor setup is cleared}';

    protected $description = 'Clear the two-factor secret, recovery codes and confirmation of an account (lost authenticator)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'two_factor_reset',
            'model' => 'User',
            'model_id' => $user->id,
            'description' => 'Two-factor reset from the server console',
        ]);
        $this->info('Two-factor cleared for '.$user->email.'. Sign in with the password, then turn it on again in Settings.');

        return self::SUCCESS;
    }
}
