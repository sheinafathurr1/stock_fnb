<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetPasswordCommand extends Command
{
    protected $signature = 'login:reset-password {username} {--password= : Skip the prompt and use this password}';

    protected $description = 'Set an account password, rehashing it in a format this app can verify';

    public function handle(): int
    {
        $user = User::where('username', $this->argument('username'))->first();

        if (!$user) {
            $this->error("No user with username \"{$this->argument('username')}\".");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('New password');

        if (!is_string($password) || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        // The model casts `password` as hashed, so assign the plain value.
        $user->password = $password;
        $user->save();

        $this->info("Password updated for {$user->name} ({$user->username}).");

        if (trim((string) $user->role) === '') {
            $this->warn('This account still has no role, so it cannot sign in yet.');
        }

        return self::SUCCESS;
    }
}
