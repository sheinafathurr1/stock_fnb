<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\JadwalShift;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleLookupController extends Controller
{
    /**
     * Return the baristas scheduled for today for the given outlet.
     */
    public function __invoke(Request $request, Outlet $outlet): JsonResponse
    {
        try {
            $today = now('Asia/Jakarta')->toDateString();

            // Query jadwal_shift table using outlet code (kode_outlet)
            $schedules = JadwalShift::approvedOn($today, $outlet->kode_outlet)->get();

            // Get unique user IDs
            $userIds = $schedules->pluck('id_user')->unique()->filter();

            // Fetch user details
            $users = User::whereIn('id', $userIds)->get(['id', 'name']);

            $staff = $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                ];
            })->values();

            if ($staff->isEmpty()) {
                return response()->json([
                    'status' => 'empty',
                    'date' => $today,
                    'staff' => [],
                    'message' => 'No scheduled baristas for today at this outlet.',
                ]);
            }

            $message = $staff->count() === 1
                ? 'The baristas on today’s shift are selected automatically.'
                : 'Several baristas are on duty today. Please select who will report.';

            return response()->json([
                'status' => 'ok',
                'date' => $today,
                'staff' => $staff,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            \Log::error('Schedule lookup failed: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load schedule.',
            ], 500);
        }
    }
}
