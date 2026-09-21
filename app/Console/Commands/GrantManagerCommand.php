<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantManagerCommand extends Command
{
    protected $signature = 'users:grant-manager
                            {username?* : One or more usernames}
                            {--from-role= : Promote everyone currently holding this role instead}
                            {--revoke : Take the manager role away rather than granting it}
                            {--revoke-to=barista : Role to set when revoking}';

    protected $description = 'Grant or revoke the manager role, one account or many at once';

    public function handle(): int
    {
        $usernames = $this->argument('username');
        $fromRole = $this->option('from-role');

        if ($usernames === [] && $fromRole === null) {
            $this->error('Name at least one username, or pass --from-role.');

            return self::FAILURE;
        }

        $query = $fromRole !== null
            ? User::whereRaw('LOWER(TRIM(COALESCE(role, ""))) = ?', [strtolower(trim($fromRole))])
            : User::whereIn('username', $usernames);

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->error($fromRole !== null
                ? "No account currently has the role \"{$fromRole}\"."
                : 'No account matched those usernames.');

            return self::FAILURE;
        }

        if ($fromRole === null) {
            $missing = collect($usernames)->diff($users->pluck('username'));

            if ($missing->isNotEmpty()) {
                $this->warn('Not found, and skipped: ' . $missing->implode(', '));
            }
        }

        $revoking = (bool) $this->option('revoke');
        $newRole = $revoking ? (string) $this->option('revoke-to') : 'manager';

        $this->newLine();
        $this->line($revoking
            ? "About to revoke manager from {$users->count()} account(s), setting role to \"{$newRole}\":"
            : "About to grant manager to {$users->count()} account(s):");

        foreach ($users as $user) {
            $current = trim((string) $user->role) === '' ? '(not set)' : $user->role;
            $this->line("  {$user->username}  ({$user->name})  role: {$current} -> {$newRole}");
        }

        $this->newLine();

        // Granting manager hands over the whole dashboard, so confirm it.
        if (!$this->confirm('Apply this change?', false)) {
            $this->line('Nothing changed.');

            return self::SUCCESS;
        }

        $blocked = [];

        foreach ($users as $user) {
            $user->role = $newRole;
            $user->save();

            if (trim((string) $user->username) === '') {
                $blocked[] = $user->id;
            }
        }

        $this->info("Updated {$users->count()} account(s).");

        if ($blocked !== []) {
            $this->warn('Some have no username and still cannot sign in: #' . implode(', #', $blocked));
        }

        return self::SUCCESS;
    }
}
