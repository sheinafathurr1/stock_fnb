<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Outlet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestBaristaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $testBaristas = [
            [
                'name' => 'Test Barista 1',
                'email' => 'barista1@test.com',
                'password' => Hash::make('password'),
                'role' => 'barista',
            ],
            [
                'name' => 'Test Barista 2',
                'email' => 'barista2@test.com',
                'password' => Hash::make('password'),
                'role' => 'barista',
            ],
            [
                'name' => 'Test Barista 3',
                'email' => 'barista3@test.com',
                'password' => Hash::make('password'),
                'role' => 'barista',
            ],
        ];

        // Get all outlet codes
        $outletCodes = Outlet::pluck('kode_outlet')->toArray();
        $today = now('Asia/Jakarta')->toDateString();

        foreach ($testBaristas as $index => $baristaData) {
            // Create barista user
            $barista = User::firstOrCreate(
                ['email' => $baristaData['email']],
                $baristaData
            );

            // Assign to outlet (rotate through outlets)
            $outletCode = $outletCodes[$index % count($outletCodes)];

            // Create schedule for today
            DB::table('jadwal_shift')->updateOrInsert(
                [
                    'id_user' => $barista->id,
                    'id_outlet' => $outletCode,
                    'tanggal' => $today,
                ],
                [
                    'status' => 'Approve',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Test baristas created and scheduled for today!');
    }
}
