<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemOutletOwnership;
use App\Models\JadwalShift;
use App\Models\Kategori;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(?Outlet $scopedTo = null): User
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);

        if ($scopedTo) {
            JadwalShift::create([
                'id_outlet' => $scopedTo->kode_outlet,
                'id_user' => $manager->id,
                'tanggal' => now('Asia/Jakarta')->toDateString(),
                'status' => JadwalShift::STATUS_APPROVED,
            ]);
        }

        return $manager;
    }

    public function test_a_manager_can_create_an_item_at_their_own_outlet(): void
    {
        $outlet = Outlet::factory()->create();
        $kategori = Kategori::factory()->create();

        $this->actingAs($this->manager($outlet))
            ->post(route('items.store'), [
                'nama' => 'Sirup Vanilla',
                'kategori_id' => $kategori->id,
                'outlet_ids' => [$outlet->id],
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('item', ['nama' => 'Sirup Vanilla']);
        $this->assertDatabaseHas('item_outlet_ownership', [
            'outlet_id' => $outlet->id,
            'current_status' => 'in_stock',
        ]);
    }

    public function test_a_manager_cannot_create_an_item_at_an_outlet_they_do_not_cover(): void
    {
        $theirs = Outlet::factory()->create();
        $other = Outlet::factory()->create();
        $kategori = Kategori::factory()->create();

        $this->actingAs($this->manager($theirs))
            ->post(route('items.store'), [
                'nama' => 'Sirup Vanilla',
                'kategori_id' => $kategori->id,
                'outlet_ids' => [$other->id],
            ])
            ->assertSessionHasErrors('outlet_ids.0');

        $this->assertDatabaseCount('item', 0);
    }

    public function test_updating_an_item_leaves_outlets_the_manager_cannot_see_alone(): void
    {
        $theirs = Outlet::factory()->create();
        $other = Outlet::factory()->create();
        $item = Item::factory()->create();

        ItemOutletOwnership::factory()->create(['item_id' => $item->id, 'outlet_id' => $theirs->id]);
        ItemOutletOwnership::factory()->create(['item_id' => $item->id, 'outlet_id' => $other->id]);

        $this->actingAs($this->manager($theirs))
            ->put(route('items.update', $item), [
                'nama' => $item->nama,
                'kategori_id' => $item->kategori_id,
                'outlet_ids' => [$theirs->id],
            ])
            ->assertRedirect(route('dashboard'));

        // The other outlet was never on the form, so it must survive the save.
        $this->assertDatabaseHas('item_outlet_ownership', [
            'item_id' => $item->id,
            'outlet_id' => $other->id,
        ]);
        $this->assertDatabaseHas('item_outlet_ownership', [
            'item_id' => $item->id,
            'outlet_id' => $theirs->id,
        ]);
    }

    public function test_removing_an_outlet_from_the_form_detaches_it(): void
    {
        $outletA = Outlet::factory()->create();
        $outletB = Outlet::factory()->create();
        $item = Item::factory()->create();

        ItemOutletOwnership::factory()->create(['item_id' => $item->id, 'outlet_id' => $outletA->id]);
        ItemOutletOwnership::factory()->create(['item_id' => $item->id, 'outlet_id' => $outletB->id]);

        // A manager with no shift history covers every outlet.
        $this->actingAs($this->manager())
            ->put(route('items.update', $item), [
                'nama' => $item->nama,
                'kategori_id' => $item->kategori_id,
                'outlet_ids' => [$outletA->id],
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('item_outlet_ownership', [
            'item_id' => $item->id,
            'outlet_id' => $outletB->id,
        ]);
    }

    public function test_a_barista_cannot_reach_the_dashboard(): void
    {
        $barista = User::factory()->create(['role' => 'barista', 'email_verified_at' => now()]);

        $this->actingAs($barista)->get(route('dashboard'))->assertRedirect('/');
    }

    public function test_the_dashboard_stat_cards_only_count_the_filtered_stock(): void
    {
        $outletA = Outlet::factory()->create();
        $outletB = Outlet::factory()->create();

        ItemOutletOwnership::factory()->outOfStock()->create(['outlet_id' => $outletA->id]);
        ItemOutletOwnership::factory()->almostOut()->create(['outlet_id' => $outletB->id]);
        ItemOutletOwnership::factory()->outOfStock()->create(['outlet_id' => $outletB->id]);

        $this->actingAs($this->manager())
            ->get(route('dashboard', ['outlet' => $outletA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventory/Pages/Dashboard')
                ->where('stockSummary.outOfStock', 1)
                ->where('stockSummary.almostOut', 0));
    }
}
