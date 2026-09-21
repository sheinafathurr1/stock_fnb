<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Outlet;
use App\Models\ItemOutletOwnership;
use Illuminate\Database\Seeder;

class ItemOutletOwnershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $outlets = Outlet::all();
        $items = Item::all();

        // Get specific outlets
        $juiceBar = $outlets->where('kode_outlet', 'OUT-UP6VLASEJX')->first();
        $lakeside = $outlets->where('kode_outlet', 'OUT-AUWXFVYRPA')->first();
        $lc = $outlets->where('kode_outlet', 'OUT-GCNV7MW5YK')->first();
        $hc = $outlets->where('kode_outlet', 'OUT-I0KWK8GSNN')->first();

        // Common items that all outlets have
        $commonItemNames = [
            'Susu', 'Gula', 'Gelas Kertas', 'Sedotan', 'Tutup Gelas'
        ];

        // Coffee shop items (all barista outlets)
        $baristaItemNames = [
            'Kopi Espresso', 'Sirup Vanilla', 'Sirup Karamel', 'Sirup Cokelat',
            'Sirup Hazelnut', 'Bubuk Cokelat', 'Krim Kocok', 'Daun Mint'
        ];

        // Juice bar specific items
        $juiceBarItemNames = [
            'Buah Stroberi', 'Buah Pisang', 'Buah Mangga', 'Jeruk',
            'Sirup Stroberi', 'Sirup Mint'
        ];

        // Assign common items to all outlets
        foreach ($commonItemNames as $itemName) {
            $item = $items->where('nama', $itemName)->first();
            if ($item) {
                foreach ($outlets as $outlet) {
                    ItemOutletOwnership::firstOrCreate(
                        ['item_id' => $item->id, 'outlet_id' => $outlet->id],
                        ['current_status' => 'in_stock'],
                    );
                }
            }
        }

        // Assign barista items to coffee shops (Lakeside, LC, HC)
        $baristaOutlets = [$lakeside, $lc, $hc];
        foreach ($baristaItemNames as $itemName) {
            $item = $items->where('nama', $itemName)->first();
            if ($item) {
                foreach ($baristaOutlets as $outlet) {
                    // Vary the status for realism
                    $status = $this->getRandomStatus();
                    ItemOutletOwnership::firstOrCreate(
                        ['item_id' => $item->id, 'outlet_id' => $outlet->id],
                        ['current_status' => $status],
                    );
                }
            }
        }

        // Assign juice bar items
        foreach ($juiceBarItemNames as $itemName) {
            $item = $items->where('nama', $itemName)->first();
            if ($item) {
                $status = $this->getRandomStatus();
                ItemOutletOwnership::firstOrCreate(
                    ['item_id' => $item->id, 'outlet_id' => $juiceBar->id],
                    ['current_status' => $status],
                );
            }
        }
    }

    /**
     * Get a random status with realistic distribution.
     * 70% in_stock, 20% almost_out, 10% out_of_stock
     */
    private function getRandomStatus(): string
    {
        $rand = rand(1, 100);
        
        if ($rand <= 70) {
            return 'in_stock';
        } elseif ($rand <= 90) {
            return 'almost_out';
        } else {
            return 'out_of_stock';
        }
    }
}
