<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed stock reporting system tables
        $this->call([
            KategoriSeeder::class,
            OutletSeeder::class,
            ItemSeeder::class,
            ItemOutletOwnershipSeeder::class,
            // Accounts last: baristas are rostered against the seeded outlets.
            DemoAccountSeeder::class,
        ]);
    }
}
