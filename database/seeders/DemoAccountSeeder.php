<?php

namespace Database\Seeders;

use App\Models\JadwalShift;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * A fresh install previously had no manager at all, so nobody could reach
     * /dashboard after seeding. This creates one manager plus a barista per
     * outlet, each rostered for today so they can actually file a report.
     */
    public function run(): void
    {
        $outlets = Outlet::orderBy('id')->get();

        if ($outlets->isEmpty()) {
            $this->command->warn('No outlets found. Run OutletSeeder first.');

            return;
        }

        // No shift is seeded for the manager: a user with no roster history
        // sees every outlet, which is what a demo install wants.
        User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Demo Manager',
                'username' => 'manager',
                'role' => 'manager',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $today = now('Asia/Jakarta')->toDateString();

        foreach ($outlets as $index => $outlet) {
            $number = $index + 1;

            $barista = User::firstOrCreate(
                ['email' => "barista{$number}@example.com"],
                [
                    'name' => "Demo Barista {$number}",
                    'username' => "barista{$number}",
                    'role' => 'barista',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            // The reporting form only offers baristas on today's approved
            // roster, so seed a shift for each of them.
            $this->rosterToday($barista, $outlet->kode_outlet, $today);
        }

        $this->command->info('Manager: manager@example.com / password');
        $this->command->info('Baristas: barista1@example.com … / password');
    }

    /**
     * Give a user an approved shift at an outlet today.
     */
    private function rosterToday(User $user, string $outletCode, string $date): void
    {
        JadwalShift::firstOrCreate(
            [
                'id_user' => $user->id,
                'id_outlet' => $outletCode,
                'tanggal' => $date,
            ],
            ['status' => JadwalShift::STATUS_APPROVED],
        );
    }
}
