<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DiagnoseLoginCommand extends Command
{
    protected $signature = 'login:diagnose {username} {--password= : Check this password the way signing in does}';

    protected $description = 'Explain why a given account can or cannot sign in';

    /**
     * Prefixes of hashes Laravel's bcrypt/argon hasher can verify.
     */
    private const SUPPORTED_PREFIXES = ['$2y$', '$2a$', '$2b$', '$argon2i$', '$argon2id$'];

    public function handle(): int
    {
        $username = $this->argument('username');

        // Read the raw row: the model casts `password`, and a trailing space
        // in `username` is invisible unless the value is delimited.
        $row = DB::table('users')->where('username', $username)->first();

        if (!$row) {
            return $this->reportMissing($username);
        }

        $this->line("Found user #{$row->id} ({$row->name})");
        $this->line('  username  : ' . $this->delimit($row->username));

        $this->reportWhitespace($row->username, $username);
        $this->reportRole($row->role);
        $supported = $this->reportPasswordHash((string) $row->password);

        if ($password = $this->option('password')) {
            $this->checkPassword($username, $password, (string) $row->password, $supported);
        } else {
            $this->newLine();
            $this->line('  Add --password=... to test the password itself.');
        }

        return self::SUCCESS;
    }

    /**
     * Nothing matched; say what else the username might be.
     */
    private function reportMissing(string $username): int
    {
        $this->error("No user with username \"{$username}\".");

        $byEmail = DB::table('users')->where('email', $username)->first();
        if ($byEmail) {
            $this->line('  An account has that as its <options=bold>email</>; its username is '
                . $this->delimit($byEmail->username) . '.');
            $this->line('  Sign-in uses the username, not the email.');

            return self::FAILURE;
        }

        // A stored value padded with spaces will not match an exact lookup.
        $loose = DB::table('users')
            ->whereRaw('TRIM(username) = ?', [trim($username)])
            ->first();

        if ($loose) {
            $this->warn('  An account has ' . $this->delimit($loose->username)
                . ' — same text, but with surrounding whitespace.');
            $this->line('  Fix it with:');
            $this->line("  php artisan tinker --execute=\"DB::table('users')->where('id',{$loose->id})->update(['username'=>trim('{$loose->username}')]);\"");
        }

        $this->newLine();
        $this->line('Usernames on file:');
        foreach (DB::table('users')->orderBy('id')->limit(25)->get(['id', 'username']) as $user) {
            $this->line("  #{$user->id}  " . $this->delimit($user->username));
        }

        return self::FAILURE;
    }

    /**
     * Surface padding that an exact lookup would trip over.
     */
    private function reportWhitespace(?string $stored, string $typed): void
    {
        if ($stored !== null && $stored !== trim($stored)) {
            $this->warn('              stored with surrounding whitespace — depending on the');
            $this->warn('              column collation, signing in may not match it');
        }

        if ($stored !== null && $typed !== $stored && trim($typed) === trim($stored)) {
            $this->warn('              what you passed differs from the stored value only by whitespace');
        }
    }

    /**
     * Only managers may sign in.
     */
    private function reportRole(?string $role): void
    {
        $role = trim((string) $role);

        if ($role === '') {
            $this->warn('  role      : not set -> cannot sign in (a manager role is required)');
        } elseif (strtolower($role) === 'manager') {
            $this->info("  role      : {$role} -> may sign in");
        } else {
            $this->warn("  role      : {$role} -> cannot sign in (only managers may)");
        }
    }

    /**
     * Report the hash format, and whether it looks intact.
     */
    private function reportPasswordHash(string $hash): bool
    {
        $supported = false;
        foreach (self::SUPPORTED_PREFIXES as $prefix) {
            if (str_starts_with($hash, $prefix)) {
                $supported = true;
                break;
            }
        }

        $length = strlen($hash);

        if (!$supported) {
            $this->warn("  password  : NOT a bcrypt/argon hash (length {$length})");
            $this->line('              Laravel cannot verify legacy formats such as MD5 or SHA1.');

            return false;
        }

        // A bcrypt hash is always exactly 60 characters. A shorter one means
        // the column truncated it, and it can never match.
        if (str_starts_with($hash, '$2') && $length !== 60) {
            $this->error("  password  : bcrypt hash is {$length} characters, expected 60");
            $this->line('              It was truncated in storage and can never match.');
            $this->line('              Widen the column to VARCHAR(255), then reset the password.');

            return false;
        }

        $this->info("  password  : {$this->hashLabel($hash)} -> readable by this app");

        return true;
    }

    /**
     * Describe the hash algorithm and work factor.
     */
    private function hashLabel(string $hash): string
    {
        $parts = explode('$', $hash);

        return str_starts_with($hash, '$2')
            ? "bcrypt (cost {$parts[2]})"
            : 'argon';
    }

    /**
     * Compare two paths: the lookup signing in performs, and a direct hash
     * check on the row this command found. When they disagree, the password
     * is fine and the lookup is what fails.
     */
    private function checkPassword(string $username, string $password, string $hash, bool $supported): void
    {
        $this->newLine();

        // The hasher throws on a hash it does not recognise, so both paths
        // are guarded; an unreadable hash is simply a non-match here.
        $hashMatches = $supported && $this->quietly(fn () => Hash::check($password, $hash));
        $loginWouldWork = $this->quietly(
            fn () => Auth::validate(['username' => $username, 'password' => $password]),
        );

        $hashMatches
            ? $this->info('  hash check: the password matches the stored hash')
            : $this->warn('  hash check: the password does NOT match the stored hash');

        $loginWouldWork
            ? $this->info('  sign-in   : credentials accepted')
            : $this->warn('  sign-in   : credentials rejected');

        if ($hashMatches && !$loginWouldWork) {
            $this->newLine();
            $this->error('  The password is right but signing in still fails.');
            $this->line('  The account lookup is what is failing, not the password.');
            $this->line('  Check config/auth.php: the "users" provider must point at');
            $this->line('  App\\Models\\User, and that model must use the same table.');
        }

        if (!$hashMatches && $supported) {
            $this->newLine();
            $this->line('  The stored hash is valid, so this password simply is not the one');
            $this->line('  it was made from. Set a known one with:');
            $this->line("  php artisan login:reset-password {$username}");
        }
    }

    /**
     * Run a check that may reject the stored hash outright.
     */
    private function quietly(callable $check): bool
    {
        try {
            return (bool) $check();
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Show a value with delimiters so padding is visible.
     */
    private function delimit(?string $value): string
    {
        return $value === null ? '<fg=red>(null)</>' : "\"{$value}\"";
    }
}
