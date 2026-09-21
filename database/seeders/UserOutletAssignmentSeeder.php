<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Outlet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserOutletAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder assigns users to outlets based on their roles:
     * - Option 1: Assign ALL users to ALL outlets (backward compatible, nothing breaks)
     * - Option 2: Assign specific users to specific outlets (for testing role-based access)
     *
     * You can choose which approach to use by commenting/uncommenting below.
     */
    public function run(): void
    {
        $users = User::all();
        $outlets = Outlet::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run user seeder first.');
            return;
        }

        if ($outlets->isEmpty()) {
            $this->command->warn('No outlets found. Please run outlet seeder first.');
            return;
        }

        // Clear existing assignments (fresh start)
        DB::table('user_outlet_assignments')->truncate();

        /**
         * OPTION 1: Assign ALL users to ALL outlets (Backward Compatible)
         *
         * This ensures nothing breaks - everyone has access to everything.
         * Later, you can manually remove assignments to restrict access.
         */
        $this->assignAllToAll($users, $outlets);

        /**
         * OPTION 2: Custom Assignments (For Testing)
         *
         * Uncomment this and comment out Option 1 if you want to test
         * role-based access with specific user-outlet assignments.
         */
        // $this->assignCustom($users, $outlets);

        $this->command->info('User-outlet assignments completed successfully!');
    }

    /**
     * Assign all users to all outlets.
     * Safe approach - nothing breaks, everyone has full access.
     */
    private function assignAllToAll($users, $outlets): void
    {
        $assignments = [];
        $timestamp = now();

        foreach ($users as $user) {
            foreach ($outlets as $outlet) {
                $assignments[] = [
                    'user_id' => $user->id,
                    'outlet_id' => $outlet->id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        // Batch insert for performance
        DB::table('user_outlet_assignments')->insert($assignments);

        $this->command->info("Assigned {$users->count()} users to {$outlets->count()} outlets (all-to-all).");
    }

    /**
     * Custom assignments for testing role-based access.
     * Example: Assign specific baristas to specific outlets.
     */
    private function assignCustom($users, $outlets): void
    {
        $managers = $users->where('role', 'manager');
        $baristas = $users->where('role', 'barista');

        $timestamp = now();
        $assignments = [];

        // Example: Assign first manager to first 2 outlets
        if ($managers->isNotEmpty() && $outlets->count() >= 2) {
            $firstManager = $managers->first();
            $assignments[] = [
                'user_id' => $firstManager->id,
                'outlet_id' => $outlets[0]->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $assignments[] = [
                'user_id' => $firstManager->id,
                'outlet_id' => $outlets[1]->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $this->command->info("Assigned manager '{$firstManager->name}' to 2 outlets.");
        }

        // Example: Assign first barista to first outlet only
        if ($baristas->isNotEmpty() && $outlets->isNotEmpty()) {
            $firstBarista = $baristas->first();
            $assignments[] = [
                'user_id' => $firstBarista->id,
                'outlet_id' => $outlets[0]->id,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            $this->command->info("Assigned barista '{$firstBarista->name}' to 1 outlet.");
        }

        if (!empty($assignments)) {
            DB::table('user_outlet_assignments')->insert($assignments);
        }

        $this->command->warn('Custom assignments applied. Some users may have limited access.');
        $this->command->warn('Users without assignments will see all outlets (fallback behavior).');
    }
}
