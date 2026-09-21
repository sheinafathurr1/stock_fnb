<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemOutletOwnership;
use App\Models\JadwalShift;
use App\Models\Outlet;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    private function manager(?Outlet $scopedTo = null): User
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'email_verified_at' => now(),
        ]);

        // A manager with no shift history sees every outlet; one with history
        // is limited to the outlets they are rostered at.
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

    private function reportFor(Outlet $outlet, string $status = 'OUT', ?string $date = null): Report
    {
        $item = Item::factory()->create();

        ItemOutletOwnership::factory()->create([
            'item_id' => $item->id,
            'outlet_id' => $outlet->id,
        ]);

        return Report::create([
            'outlet_id' => $outlet->id,
            'user_id' => User::factory()->create(['role' => 'barista'])->id,
            'item_id' => $item->id,
            'report_status' => $status,
            'reported_for_date' => $date ?? now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function test_accepting_a_report_updates_the_stock_status(): void
    {
        $outlet = Outlet::factory()->create();
        $report = $this->reportFor($outlet, 'OUT');

        $this->actingAs($this->manager())
            ->postJson(route('reports.accept', $report))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('item_outlet_ownership', [
            'item_id' => $report->item_id,
            'outlet_id' => $outlet->id,
            'current_status' => 'out_of_stock',
        ]);

        $this->assertDatabaseHas('report', ['id' => $report->id, 'accepted' => true]);
    }

    public function test_accepting_a_restock_report_puts_the_item_back_in_stock(): void
    {
        $outlet = Outlet::factory()->create();
        $report = $this->reportFor($outlet, 'READY');

        ItemOutletOwnership::where('item_id', $report->item_id)
            ->update(['current_status' => 'out_of_stock']);

        $this->actingAs($this->manager())
            ->postJson(route('reports.accept', $report))
            ->assertOk();

        $this->assertDatabaseHas('item_outlet_ownership', [
            'item_id' => $report->item_id,
            'outlet_id' => $outlet->id,
            'current_status' => 'in_stock',
        ]);
    }

    public function test_a_manager_cannot_accept_a_report_from_an_outlet_they_do_not_cover(): void
    {
        $theirs = Outlet::factory()->create();
        $other = Outlet::factory()->create();
        $report = $this->reportFor($other);

        $this->actingAs($this->manager($theirs))
            ->postJson(route('reports.accept', $report))
            ->assertStatus(403);

        $this->assertDatabaseHas('report', ['id' => $report->id, 'accepted' => false]);
    }

    public function test_the_acceptance_timestamp_is_stored_in_utc(): void
    {
        // Regression: accepted_at was written as Jakarta wall-clock time into
        // a UTC column, so the reports list showed it seven hours ahead.
        $report = $this->reportFor(Outlet::factory()->create());

        $this->actingAs($this->manager())
            ->postJson(route('reports.accept', $report))
            ->assertOk();

        $this->assertEqualsWithDelta(
            now()->getTimestamp(),
            $report->fresh()->accepted_at->getTimestamp(),
            60,
        );
    }

    public function test_a_report_cannot_be_accepted_twice(): void
    {
        $report = $this->reportFor(Outlet::factory()->create());
        $manager = $this->manager();

        $this->actingAs($manager)->postJson(route('reports.accept', $report))->assertOk();
        $this->actingAs($manager)->postJson(route('reports.accept', $report))->assertStatus(400);
    }

    public function test_reset_only_deletes_the_day_in_view(): void
    {
        $outlet = Outlet::factory()->create();
        $today = $this->reportFor($outlet, 'OUT', now('Asia/Jakarta')->toDateString());
        $yesterday = $this->reportFor($outlet, 'OUT', now('Asia/Jakarta')->subDay()->toDateString());

        $this->actingAs($this->manager())
            ->delete(route('reports.reset', ['date' => now('Asia/Jakarta')->toDateString()]))
            ->assertRedirect();

        $this->assertDatabaseMissing('report', ['id' => $today->id]);
        $this->assertDatabaseHas('report', ['id' => $yesterday->id]);
    }

    public function test_reset_only_deletes_the_outlet_in_view(): void
    {
        $outletA = Outlet::factory()->create();
        $outletB = Outlet::factory()->create();
        $reportA = $this->reportFor($outletA);
        $reportB = $this->reportFor($outletB);

        $this->actingAs($this->manager())
            ->delete(route('reports.reset', [
                'date' => now('Asia/Jakarta')->toDateString(),
                'outlet' => $outletA->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseMissing('report', ['id' => $reportA->id]);
        $this->assertDatabaseHas('report', ['id' => $reportB->id]);
    }

    public function test_reset_never_touches_an_outlet_the_manager_does_not_cover(): void
    {
        $theirs = Outlet::factory()->create();
        $other = Outlet::factory()->create();
        $ours = $this->reportFor($theirs);
        $theirsNot = $this->reportFor($other);

        $this->actingAs($this->manager($theirs))
            ->delete(route('reports.reset', ['date' => now('Asia/Jakarta')->toDateString()]))
            ->assertRedirect();

        $this->assertDatabaseMissing('report', ['id' => $ours->id]);
        $this->assertDatabaseHas('report', ['id' => $theirsNot->id]);
    }

    public function test_the_poll_endpoint_hides_outlets_the_manager_does_not_cover(): void
    {
        $theirs = Outlet::factory()->create();
        $other = Outlet::factory()->create();
        $visible = $this->reportFor($theirs);
        $hidden = $this->reportFor($other);

        $response = $this->actingAs($this->manager($theirs))
            ->getJson(route('reports.poll'))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($hidden->id));
    }

    public function test_the_poll_endpoint_rejects_a_malformed_date(): void
    {
        $this->actingAs($this->manager())
            ->getJson(route('reports.poll', ['date' => 'not-a-date']))
            ->assertStatus(422);
    }

    public function test_the_reports_page_counts_every_pending_report_not_just_the_page(): void
    {
        $outlet = Outlet::factory()->create();

        for ($i = 0; $i < 25; $i++) {
            $this->reportFor($outlet);
        }

        $this->actingAs($this->manager())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reports/Pages/Reports')
                ->where('pendingCount', 25)
                ->where('reports.total', 25)
                ->has('reports.data', 20));
    }
}
