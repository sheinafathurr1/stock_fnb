<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $outlets = [
            [
                'nama' => 'Juice Bar FIT+',
                'kode_outlet' => 'OUT-UP6VLASEJX',
                'icon' => '🥤',
            ],
            [
                'nama' => 'Barista Lakeside',
                'kode_outlet' => 'OUT-AUWXFVYRPA',
                'icon' => '☕',
            ],
            [
                'nama' => 'Barista LC',
                'kode_outlet' => 'OUT-GCNV7MW5YK',
                'icon' => '🏪',
            ],
            [
                'nama' => 'Barista HC',
                'kode_outlet' => 'OUT-I0KWK8GSNN',
                'icon' => '🏢',
            ],
        ];

        foreach ($outlets as $outlet) {
            Outlet::firstOrCreate(['kode_outlet' => $outlet['kode_outlet']], $outlet);
        }
    }
}

