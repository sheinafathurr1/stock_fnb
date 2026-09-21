<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemOutletOwnership;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStockReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_outlet_can_be_opened_by_code(): void
    {
        $outlet = Outlet::factory()->create(['nama' => 'Barista Lakeside', 'kode_outlet' => 'OUT-LAKE']);
        $item = Item::factory()->create(['nama' => 'Gula Aren']);

        ItemOutletOwnership::factory()->outOfStock()->create([
            'item_id' => $item->id,
            'outlet_id' => $outlet->id,
        ]);

        $this->get('/stock-report?outlet=OUT-LAKE')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Pages/StockReport')
                ->where('selectedOutlet.name', 'Lakeside')
                ->has('stockItems', 1)
                ->where('stockItems.0.name', 'Gula Aren')
                ->where('stockItems.0.status', 'out_of_stock'));
    }

    public function test_a_soft_deleted_item_is_not_listed(): void
    {
        $outlet = Outlet::factory()->create(['kode_outlet' => 'OUT-LAKE']);
        $item = Item::factory()->deleted()->create();

        ItemOutletOwnership::factory()->create([
            'item_id' => $item->id,
            'outlet_id' => $outlet->id,
        ]);

        $this->get('/stock-report?outlet=OUT-LAKE')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('stockItems', 0));
    }

    public function test_a_missing_outlet_redirects_home(): void
    {
        $this->get('/stock-report')->assertRedirect('/');
        $this->get('/stock-report?outlet=')->assertRedirect('/');
        $this->get('/stock-report?outlet=nope')->assertRedirect('/');
    }

    public function test_an_array_outlet_parameter_does_not_error(): void
    {
        // Regression: ?outlet[]= reached trim() and raised a TypeError.
        $this->get('/stock-report?outlet[]=OUT-LAKE')->assertRedirect('/');
    }
}
