<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Kategori;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get categories
        $sirup = Kategori::where('nama', 'Sirup')->first();
        $buah = Kategori::where('nama', 'Buah-buahan')->first();
        $bahanBaku = Kategori::where('nama', 'Bahan Baku')->first();
        $kemasan = Kategori::where('nama', 'Kemasan')->first();

        // Syrups
        $items = [
            // Sirup
            ['nama' => 'Sirup Vanilla', 'kategori_id' => $sirup->id],
            ['nama' => 'Sirup Karamel', 'kategori_id' => $sirup->id],
            ['nama' => 'Sirup Cokelat', 'kategori_id' => $sirup->id],
            ['nama' => 'Sirup Hazelnut', 'kategori_id' => $sirup->id],
            ['nama' => 'Sirup Stroberi', 'kategori_id' => $sirup->id],
            ['nama' => 'Sirup Mint', 'kategori_id' => $sirup->id],

            // Buah-buahan
            ['nama' => 'Buah Stroberi', 'kategori_id' => $buah->id],
            ['nama' => 'Buah Pisang', 'kategori_id' => $buah->id],
            ['nama' => 'Buah Mangga', 'kategori_id' => $buah->id],
            ['nama' => 'Jeruk', 'kategori_id' => $buah->id],

            // Bahan Baku
            ['nama' => 'Kopi Espresso', 'kategori_id' => $bahanBaku->id],
            ['nama' => 'Susu', 'kategori_id' => $bahanBaku->id],
            ['nama' => 'Gula', 'kategori_id' => $bahanBaku->id],
            ['nama' => 'Bubuk Cokelat', 'kategori_id' => $bahanBaku->id],
            ['nama' => 'Krim Kocok', 'kategori_id' => $bahanBaku->id],
            ['nama' => 'Daun Mint', 'kategori_id' => $bahanBaku->id],

            // Kemasan
            ['nama' => 'Gelas Kertas', 'kategori_id' => $kemasan->id],
            ['nama' => 'Sedotan', 'kategori_id' => $kemasan->id],
            ['nama' => 'Tutup Gelas', 'kategori_id' => $kemasan->id],
        ];

        foreach ($items as $item) {
            Item::create($item);
        }
    }
}

