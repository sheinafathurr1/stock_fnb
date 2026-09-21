<?php

namespace Tests\Feature;

use App\Models\JadwalShift;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleLookupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test manager can view today's staff for an outlet with one barista scheduled.
     */
    public function test_single_barista_scheduled_today_returns_ok_status(): void
    {
        $outlet = Outlet::factory()->create(['nama' => 'Barista Test', 'kode_outlet' => 'OUT-TEST01']);
        $barista = User::factory()->create(['name' => 'John Doe', 'role' => 'barista']);

        $today = now('Asia/Jakarta')->toDateString();
        JadwalShift::create([
            'id_outlet' => $outlet->kode_outlet,
            'id_user' => $barista->id,
            'tanggal' => $today,
            'status' => 'Approve',
        ]);

        $response = $this->getJson("/api/outlets/{$outlet->id}/schedules/today");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'date' => $today,
                'staff' => [
                    ['id' => $barista->id, 'name' => 'John Doe'],
                ],
                'message' => 'The baristas on today’s shift are selected automatically.',
            ]);
    }

    /**
     * Test multiple baristas scheduled today returns ok status with selection prompt.
     */
    public function test_multiple_baristas_scheduled_today_returns_ok_status(): void
    {
        $outlet = Outlet::factory()->create(['nama' => 'Barista Test', 'kode_outlet' => 'OUT-TEST02']);
        $barista1 = User::factory()->create(['name' => 'Alice', 'role' => 'barista']);
        $barista2 = User::factory()->create(['name' => 'Bob', 'role' => 'barista']);

        $today = now('Asia/Jakarta')->toDateString();
        JadwalShift::create([
            'id_outlet' => $outlet->kode_outlet,
            'id_user' => $barista1->id,
            'tanggal' => $today,
            'status' => 'Approve',
        ]);
        JadwalShift::create([
            'id_outlet' => $outlet->kode_outlet,
            'id_user' => $barista2->id,
            'tanggal' => $today,
            'status' => 'Approve',
        ]);

        $response = $this->getJson("/api/outlets/{$outlet->id}/schedules/today");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'date' => $today,
                'message' => 'Several baristas are on duty today. Please select who will report.',
            ])
            ->assertJsonCount(2, 'staff');
    }

    /**
     * Test no baristas scheduled today returns empty status with friendly message.
     */
    public function test_no_baristas_scheduled_today_returns_empty_status(): void
    {
        $outlet = Outlet::factory()->create(['nama' => 'Barista Test', 'kode_outlet' => 'OUT-TEST03']);

        $response = $this->getJson("/api/outlets/{$outlet->id}/schedules/today");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'empty',
                'date' => now('Asia/Jakarta')->toDateString(),
                'staff' => [],
                'message' => 'No scheduled baristas for today at this outlet.',
            ]);
    }

    /**
     * Test baristas scheduled on different dates are not included in today's result.
     */
    public function test_only_today_schedules_are_returned(): void
    {
        $outlet = Outlet::factory()->create(['nama' => 'Barista Test', 'kode_outlet' => 'OUT-TEST04']);
        $baristaToday = User::factory()->create(['name' => 'Today Barista']);
        $baristaYesterday = User::factory()->create(['name' => 'Yesterday Barista']);
        $baristaTomorrow = User::factory()->create(['name' => 'Tomorrow Barista']);

        $today = now('Asia/Jakarta')->toDateString();
        $yesterday = now('Asia/Jakarta')->subDay()->toDateString();
        $tomorrow = now('Asia/Jakarta')->addDay()->toDateString();

        JadwalShift::create([
            'id_outlet' => $outlet->kode_outlet,
            'id_user' => $baristaToday->id,
            'tanggal' => $today,
            'status' => 'Approve',
        ]);
        JadwalShift::create([
            'id_outlet' => $outlet->kode_outlet,
            'id_user' => $baristaYesterday->id,
            'tanggal' => $yesterday,
            'status' => 'Approve',
        ]);
        JadwalShift::create([
            'id_outlet' => $outlet->kode_outlet,
            'id_user' => $baristaTomorrow->id,
            'tanggal' => $tomorrow,
            'status' => 'Approve',
        ]);

        $response = $this->getJson("/api/outlets/{$outlet->id}/schedules/today");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'staff' => [
                    ['id' => $baristaToday->id, 'name' => 'Today Barista'],
                ],
            ])
            ->assertJsonCount(1, 'staff');
    }

    /**
     * Test duplicate users in multiple shifts today are deduplicated.
     */
    public function test_duplicate_user_schedules_are_deduplicated(): void
    {
        $outlet = Outlet::factory()->create(['nama' => 'Barista Test', 'kode_outlet' => 'OUT-TEST05']);
        $barista = User::factory()->create(['name' => 'John Doe']);

        $today = now('Asia/Jakarta')->toDateString();

        // Create two shifts for the same user on the same day (morning and evening)
        JadwalShift::create([
            'id_outlet' => $outlet->kode_outlet,
            'id_user' => $barista->id,
            'tanggal' => $today,
            'status' => 'Approve',
            'id_jam' => '1', // Morning shift
        ]);
        JadwalShift::create([
            'id_outlet' => $outlet->kode_outlet,
            'id_user' => $barista->id,
            'tanggal' => $today,
            'status' => 'Approve',
            'id_jam' => '2', // Evening shift
        ]);

        $response = $this->getJson("/api/outlets/{$outlet->id}/schedules/today");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'staff')
            ->assertJson([
                'staff' => [
                    ['id' => $barista->id, 'name' => 'John Doe'],
                ],
            ]);
    }

    /**
     * Test schedules for different outlets are isolated.
     */
    public function test_schedules_are_outlet_specific(): void
    {
        $outlet1 = Outlet::factory()->create(['nama' => 'Barista Outlet 1', 'kode_outlet' => 'OUT-TEST06']);
        $outlet2 = Outlet::factory()->create(['nama' => 'Barista Outlet 2', 'kode_outlet' => 'OUT-TEST07']);

        $barista1 = User::factory()->create(['name' => 'Alice']);
        $barista2 = User::factory()->create(['name' => 'Bob']);

        $today = now('Asia/Jakarta')->toDateString();

        JadwalShift::create([
            'id_outlet' => $outlet1->kode_outlet,
            'id_user' => $barista1->id,
            'tanggal' => $today,
            'status' => 'Approve',
        ]);
        JadwalShift::create([
            'id_outlet' => $outlet2->kode_outlet,
            'id_user' => $barista2->id,
            'tanggal' => $today,
            'status' => 'Approve',
        ]);

        $response1 = $this->getJson("/api/outlets/{$outlet1->id}/schedules/today");
        $response2 = $this->getJson("/api/outlets/{$outlet2->id}/schedules/today");

        $response1->assertStatus(200)
            ->assertJsonCount(1, 'staff')
            ->assertJson([
                'staff' => [
                    ['id' => $barista1->id, 'name' => 'Alice'],
                ],
            ]);

        $response2->assertStatus(200)
            ->assertJsonCount(1, 'staff')
            ->assertJson([
                'staff' => [
                    ['id' => $barista2->id, 'name' => 'Bob'],
                ],
            ]);
    }

    /**
     * Test invalid outlet returns 404.
     */
    public function test_invalid_outlet_returns_404(): void
    {
        $response = $this->getJson('/api/outlets/99999/schedules/today');

        $response->assertStatus(404);
    }
}

