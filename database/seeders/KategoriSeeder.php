<?php

namespace Database\Seeders;

use App\Models\Kategori;
use Illuminate\Database\Seeder;

class KategoriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['nama' => 'Sirup'],
            ['nama' => 'Buah-buahan'],
            ['nama' => 'Bahan Baku'],
            ['nama' => 'Kemasan'],
        ];

        foreach ($categories as $category) {
            // Idempotent: this app may be seeded into a database that already
            // carries reference data from an earlier run.
            Kategori::firstOrCreate(['nama' => $category['nama']], $category);
        }
    }
}

