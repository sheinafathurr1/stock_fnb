<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class DiagnoseLoginCommand extends Command
{
    protected $signature = 'login:diagnose {username} {--password= : Check this password against the stored hash}';

    protected $description = 'Explain why a given account can or cannot sign in';

    /**
     * Prefixes of hashes Laravel's bcrypt/argon hasher can verify.
     */
    private const SUPPORTED_PREFIXES = ['$2y$', '$2a$', '$2b$', '$argon2i$', '$argon2id$'];

    public function handle(): int
    {
        $username = $this->argument('username');
        $user = User::where('username', $username)->first();

        if (!$user) {
            $this->error("No user with username \"{$username}\".");

            $byEmail = User::where('email', $username)->first();
            if ($byEmail) {
                $this->line("  An account has that as its <options=bold>email</>; its username is "
                    . ($byEmail->username === null ? '<fg=red>not set</>' : "\"{$byEmail->username}\"") . '.');
                $this->line('  Sign-in uses the username, not the email.');
            }

            return self::FAILURE;
        }

        $this->line("Found user #{$user->id} ({$user->name})");

        // 1. Role
        $role = trim((string) $user->role);
        if ($role === '') {
            $this->warn('  role      : not set -> cannot sign in (a manager role is required)');
        } elseif (strtolower($role) === 'manager') {
            $this->info("  role      : {$role} -> may sign in");
        } else {
            $this->warn("  role      : {$role} -> cannot sign in (only managers may)");
        }

        // 2. Password hash format
        $hash = (string) $user->password;
        $supported = false;
        foreach (self::SUPPORTED_PREFIXES as $prefix) {
            if (str_starts_with($hash, $prefix)) {
                $supported = true;
                break;
            }
        }

        if ($supported) {
            $this->info('  password  : bcrypt/argon hash -> readable by this app');
        } else {
            $this->warn('  password  : NOT a bcrypt/argon hash (length ' . strlen($hash) . ')');
            $this->line('              Laravel cannot verify legacy formats such as MD5 or SHA1.');
            $this->line('              Reset it with:');
            $this->line("              php artisan login:reset-password {$username}");
        }

        // 3. Optional password check
        if ($password = $this->option('password')) {
            $matches = $supported && Hash::check($password, $hash);
            $matches
                ? $this->info('  check     : the supplied password matches')
                : $this->warn('  check     : the supplied password does NOT match');
        }

        return self::SUCCESS;
    }
}
