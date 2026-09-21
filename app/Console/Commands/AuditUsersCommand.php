<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditUsersCommand extends Command
{
    protected $signature = 'users:audit';

    protected $description = 'Report which accounts can sign in, and what stands in the way';

    /**
     * Prefixes of hashes Laravel's bcrypt/argon hasher can verify.
     */
    private const SUPPORTED_PREFIXES = ['$2y$', '$2a$', '$2b$', '$argon2i$', '$argon2id$'];

    public function handle(): int
    {
        $total = User::count();

        if ($total === 0) {
            $this->warn('No users in this database.');

            return self::SUCCESS;
        }

        $this->line("Users: <options=bold>{$total}</>");
        $this->newLine();

        $this->reportRoles();
        $this->reportUsernames();
        $this->reportPasswords();

        return self::SUCCESS;
    }

    /**
     * Which roles exist, and which of them may sign in.
     */
    private function reportRoles(): void
    {
        $this->line('<options=bold>Roles</> (only "manager", in any casing, may sign in)');

        $roles = User::query()
            ->select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->orderByDesc('total')
            ->get();

        $managers = 0;

        foreach ($roles as $row) {
            $value = $row->role;
            $label = ($value === null || trim($value) === '') ? '(not set)' : $value;
            $canSignIn = strtolower(trim((string) $value)) === 'manager';

            if ($canSignIn) {
                $managers += $row->total;
                $this->info(sprintf('  %-24s %5d  can sign in', $label, $row->total));
            } else {
                $this->line(sprintf('  %-24s %5d  cannot sign in', $label, $row->total));
            }
        }

        $this->newLine();

        if ($managers === 0) {
            $this->warn('  No account has the manager role, so nobody can sign in yet.');
            $this->line('  Promote one with: php artisan users:grant-manager <username>');
        } else {
            $this->info("  {$managers} account(s) can sign in.");
        }

        $this->newLine();
    }

    /**
     * Sign-in is by username, so a blank or duplicated one is a problem.
     */
    private function reportUsernames(): void
    {
        $this->line('<options=bold>Usernames</> (sign-in is by username)');

        $blank = User::query()
            ->where(fn ($q) => $q->whereNull('username')->orWhere('username', ''))
            ->count();

        $blank === 0
            ? $this->info('  every account has one')
            : $this->warn("  {$blank} account(s) have none and cannot sign in");

        $duplicates = User::query()
            ->select('username', DB::raw('COUNT(*) as total'))
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->groupBy('username')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('total', 'username');

        if ($duplicates->isEmpty()) {
            $this->info('  all are unique');
        } else {
            $this->error('  duplicated, so sign-in would match whichever row comes first:');
            foreach ($duplicates as $username => $count) {
                $this->line("    {$username} ({$count} accounts)");
            }
        }

        $this->newLine();
    }

    /**
     * Legacy hashes from another application cannot be verified here.
     */
    private function reportPasswords(): void
    {
        $this->line('<options=bold>Password hashes</>');

        $unsupported = [];

        User::query()->select(['id', 'username', 'password'])->chunkById(500, function ($users) use (&$unsupported) {
            foreach ($users as $user) {
                $hash = (string) $user->password;
                $supported = false;

                foreach (self::SUPPORTED_PREFIXES as $prefix) {
                    if (str_starts_with($hash, $prefix)) {
                        $supported = true;
                        break;
                    }
                }

                if (!$supported) {
                    $unsupported[] = $user->username ?: "#{$user->id}";
                }
            }
        });

        if ($unsupported === []) {
            $this->info('  all readable by this app');
        } else {
            $count = count($unsupported);
            $this->warn("  {$count} account(s) use a format this app cannot verify (e.g. MD5 or SHA1)");
            $this->line('    ' . implode(', ', array_slice($unsupported, 0, 10))
                . ($count > 10 ? ', …' : ''));
            $this->line('    Rehash one with: php artisan login:reset-password <username>');
        }

        $this->newLine();
    }
}
