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

class StockReportSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::factory()->create(['kode_outlet' => 'OUT-TEST01']);
        $this->item = Item::factory()->create();

        ItemOutletOwnership::factory()->create([
            'item_id' => $this->item->id,
            'outlet_id' => $this->outlet->id,
        ]);
    }

    /**
     * Create a barista rostered at the test outlet today.
     */
    private function scheduledBarista(string $status = JadwalShift::STATUS_APPROVED): User
    {
        $barista = User::factory()->create(['role' => 'barista']);

        JadwalShift::create([
            'id_outlet' => $this->outlet->kode_outlet,
            'id_user' => $barista->id,
            'tanggal' => now('Asia/Jakarta')->toDateString(),
            'status' => $status,
        ]);

        return $barista;
    }

    private function payload(User $barista, string $status = 'OUT'): array
    {
        return [
            'outlet_id' => $this->outlet->id,
            'user_id' => $barista->id,
            'items' => [
                ['item_id' => $this->item->id, 'status' => $status],
            ],
        ];
    }

    public function test_a_scheduled_barista_can_submit_a_report(): void
    {
        $barista = $this->scheduledBarista();

        $this->postJson('/api/reports', $this->payload($barista))
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('report', [
            'outlet_id' => $this->outlet->id,
            'user_id' => $barista->id,
            'item_id' => $this->item->id,
            'report_status' => 'OUT',
            'accepted' => false,
        ]);
    }

    public function test_a_barista_with_no_schedule_at_all_cannot_submit(): void
    {
        // Regression: the schedule check used to be skipped entirely for users
        // with no shift history, so any barista ID could report anywhere.
        $barista = User::factory()->create(['role' => 'barista']);

        $this->postJson('/api/reports', $this->payload($barista))
            ->assertStatus(403)
            ->assertJson(['message' => 'You are not scheduled to work at this outlet today.']);

        $this->assertDatabaseCount('report', 0);
    }

    public function test_a_barista_scheduled_elsewhere_cannot_submit_here(): void
    {
        $otherOutlet = Outlet::factory()->create(['kode_outlet' => 'OUT-TEST02']);
        $barista = User::factory()->create(['role' => 'barista']);

        JadwalShift::create([
            'id_outlet' => $otherOutlet->kode_outlet,
            'id_user' => $barista->id,
            'tanggal' => now('Asia/Jakarta')->toDateString(),
            'status' => JadwalShift::STATUS_APPROVED,
        ]);

        $this->postJson('/api/reports', $this->payload($barista))->assertStatus(403);

        $this->assertDatabaseCount('report', 0);
    }

    public function test_an_unapproved_shift_does_not_grant_access(): void
    {
        $barista = $this->scheduledBarista('Pending');

        $this->postJson('/api/reports', $this->payload($barista))->assertStatus(403);

        $this->assertDatabaseCount('report', 0);
    }

    public function test_a_manager_cannot_submit_a_barista_report(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->postJson('/api/reports', $this->payload($manager))
            ->assertStatus(403)
            ->assertJson(['message' => 'Only baristas can submit reports.']);
    }

    public function test_items_from_another_outlet_are_rejected(): void
    {
        $barista = $this->scheduledBarista();
        $foreignItem = Item::factory()->create();

        $this->postJson('/api/reports', [
            'outlet_id' => $this->outlet->id,
            'user_id' => $barista->id,
            'items' => [['item_id' => $foreignItem->id, 'status' => 'OUT']],
        ])->assertStatus(422);

        $this->assertDatabaseCount('report', 0);
    }

    public function test_a_restock_can_be_reported(): void
    {
        $barista = $this->scheduledBarista();

        ItemOutletOwnership::where('item_id', $this->item->id)
            ->update(['current_status' => 'out_of_stock']);

        $this->postJson('/api/reports', $this->payload($barista, 'READY'))
            ->assertStatus(201);

        $this->assertDatabaseHas('report', [
            'item_id' => $this->item->id,
            'report_status' => 'READY',
        ]);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $barista = $this->scheduledBarista();

        $this->postJson('/api/reports', $this->payload($barista, 'SOMETHING_ELSE'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.status');
    }

    public function test_an_identical_pending_report_is_not_duplicated(): void
    {
        $barista = $this->scheduledBarista();

        $this->postJson('/api/reports', $this->payload($barista))->assertStatus(201);
        $response = $this->postJson('/api/reports', $this->payload($barista))->assertStatus(201);

        $response->assertJsonPath('data.total_items', 0);
        $response->assertJsonPath('data.duplicate_item_ids', [$this->item->id]);

        $this->assertDatabaseCount('report', 1);
    }

    public function test_a_different_status_is_still_recorded(): void
    {
        $barista = $this->scheduledBarista();

        $this->postJson('/api/reports', $this->payload($barista, 'ALMOST_OUT'))->assertStatus(201);
        $this->postJson('/api/reports', $this->payload($barista, 'OUT'))->assertStatus(201);

        $this->assertDatabaseCount('report', 2);
    }

    public function test_an_accepted_report_does_not_block_a_new_one(): void
    {
        $barista = $this->scheduledBarista();

        $this->postJson('/api/reports', $this->payload($barista))->assertStatus(201);

        Report::query()->update(['accepted' => true]);

        $this->postJson('/api/reports', $this->payload($barista))->assertStatus(201);

        $this->assertDatabaseCount('report', 2);
    }

    public function test_repeats_within_one_payload_collapse_to_one_row(): void
    {
        $barista = $this->scheduledBarista();

        $this->postJson('/api/reports', [
            'outlet_id' => $this->outlet->id,
            'user_id' => $barista->id,
            'items' => [
                ['item_id' => $this->item->id, 'status' => 'ALMOST_OUT'],
                ['item_id' => $this->item->id, 'status' => 'OUT'],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseCount('report', 1);
        $this->assertDatabaseHas('report', ['report_status' => 'OUT']);
    }
}
