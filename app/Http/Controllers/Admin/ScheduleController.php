<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JadwalShift;
use App\Models\Outlet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    /**
     * Display weekly shift schedule
     */
    public function index(Request $request): Response
    {
        $selectedOutletId = $request->integer('outlet');
        $weekOffset = $request->integer('week', 0); // 0 = current week, -1 = last week, 1 = next week

        // Get current week start (Monday) in Asia/Jakarta timezone
        $startOfWeek = now('Asia/Jakarta')->startOfWeek()->addWeeks($weekOffset);
        $endOfWeek = $startOfWeek->copy()->endOfWeek();

        // Get all outlets
        $outlets = Outlet::orderBy('nama')->get()->map(function ($outlet) {
            return [
                'id' => $outlet->id,
                'name' => $outlet->short_name,
                'code' => $outlet->kode_outlet,
            ];
        });

        // Build query
        $schedulesQuery = JadwalShift::with(['user:id,name', 'outlet'])
            ->whereBetween('tanggal', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->where('status', 'Approve');

        if ($selectedOutletId) {
            $outlet = Outlet::find($selectedOutletId);
            if ($outlet) {
                $schedulesQuery->where('id_outlet', $outlet->kode_outlet);
            }
        }

        $schedules = $schedulesQuery->orderBy('tanggal')->orderBy('id_jam')->get();

        // Build week data (Monday to Sunday)
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $daySchedules = $schedules->filter(function ($schedule) use ($date) {
                return Carbon::parse($schedule->tanggal)->isSameDay($date);
            });

            $weekDays[] = [
                'date' => $date->toDateString(),
                'dayName' => $date->format('l'), // Monday, Tuesday, etc.
                'dayShort' => $date->format('D'), // Mon, Tue, etc.
                'dayNumber' => $date->format('d'),
                'isToday' => $date->isToday(),
                'shifts' => $daySchedules->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'user_name' => $schedule->user?->name ?? 'Unknown',
                        'user_id' => $schedule->id_user,
                        'outlet_name' => $schedule->outlet?->nama ?? 'Unknown',
                        'shift_time' => $schedule->id_jam,
                        'status' => $schedule->status,
                        'check_in' => $schedule->check_in_time?->format('H:i'),
                        'check_out' => $schedule->check_out_time?->format('H:i'),
                    ];
                })->values()->toArray(),
            ];
        }

        return Inertia::render('Schedule/Pages/JadwalShift', [
            'weekDays' => $weekDays,
            'outlets' => $outlets,
            'selectedOutletId' => $selectedOutletId,
            'weekOffset' => $weekOffset,
            'weekStart' => $startOfWeek->format('M d, Y'),
            'weekEnd' => $endOfWeek->format('M d, Y'),
            'currentWeek' => $weekOffset === 0,
        ]);
    }
}
