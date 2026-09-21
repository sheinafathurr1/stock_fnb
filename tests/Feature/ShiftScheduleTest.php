<?php

namespace Tests\Feature;

use App\Models\JadwalShift;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShiftScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function shift(Outlet $outlet, User $user, string $date): JadwalShift
    {
        return JadwalShift::create([
            'id_outlet' => $outlet->kode_outlet,
            'id_user' => $user->id,
            'tanggal' => $date,
            'status' => JadwalShift::STATUS_APPROVED,
        ]);
    }

    public function test_the_shift_date_is_stored_without_a_time_component(): void
    {
        $outlet = Outlet::factory()->create();
        $user = User::factory()->create(['role' => 'barista']);
        $date = now('Asia/Jakarta')->toDateString();

        $shift = $this->shift($outlet, $user, $date);

        $this->assertSame(
            $date,
            DB::table('jadwal_shift')->where('id', $shift->id)->value('tanggal'),
        );
    }

    public function test_a_shift_on_the_last_day_of_a_range_is_still_found(): void
    {
        // Regression: "2026-09-27 00:00:00" sorts after "2026-09-27", so a
        // whereBetween ending on that date silently dropped the shift and the
        // weekly schedule screen lost its final day.
        $outlet = Outlet::factory()->create();
        $user = User::factory()->create(['role' => 'barista']);

        $start = now('Asia/Jakarta')->startOfWeek();
        $end = now('Asia/Jakarta')->endOfWeek();

        $this->shift($outlet, $user, $end->toDateString());

        $this->assertSame(1, JadwalShift::query()
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->count());
    }

    public function test_the_schedule_screen_shows_a_shift_on_the_last_day_of_the_week(): void
    {
        $outlet = Outlet::factory()->create();
        $barista = User::factory()->create(['role' => 'barista', 'name' => 'Sunday Barista']);
        $manager = User::factory()->create(['role' => 'manager', 'email_verified_at' => now()]);

        $this->shift($outlet, $barista, now('Asia/Jakarta')->endOfWeek()->toDateString());

        $this->actingAs($manager)
            ->get(route('schedule.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Schedule/Pages/JadwalShift')
                ->has('weekDays', 7)
                ->has('weekDays.6.shifts', 1)
                ->where('weekDays.6.shifts.0.user_name', 'Sunday Barista'));
    }

    public function test_reseeding_does_not_duplicate_shifts(): void
    {
        // The demo seeder is safe to re-run so a local install can be
        // re-rostered for the current day without piling up shifts.
        $this->seed(\Database\Seeders\OutletSeeder::class);

        $this->seed(\Database\Seeders\DemoAccountSeeder::class);
        $afterFirst = JadwalShift::count();

        $this->seed(\Database\Seeders\DemoAccountSeeder::class);

        $this->assertSame($afterFirst, JadwalShift::count());
        $this->assertGreaterThan(0, $afterFirst);
    }
}
