<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display reports with optional outlet filtering.
     */
    public function index(Request $request): Response
    {
        $selectedOutletId = $request->integer('outlet');
        $selectedDateInput = $request->string('date')->trim();
        $selectedDate = now('Asia/Jakarta')->toDateString();

        if ($selectedDateInput->isNotEmpty()) {
            try {
                $parsedDate = Carbon::createFromFormat('Y-m-d', $selectedDateInput->value(), 'Asia/Jakarta');
                $selectedDate = $parsedDate->toDateString();
            } catch (\Throwable $e) {
                // Ignore invalid date input and fall back to today.
            }
        }

        $timezone = 'Asia/Jakarta';
        $supportsReportedDate = Schema::hasColumn('report', 'reported_for_date');

        $reportsQuery = Report::query();

        if ($supportsReportedDate) {
            $reportsQuery->where('reported_for_date', $selectedDate);
        } else {
            $appTimezone = config('app.timezone', 'UTC');
            $startOfDay = Carbon::createFromFormat('Y-m-d', $selectedDate, $timezone)
                ->startOfDay()
                ->setTimezone($appTimezone);

            $endOfDay = Carbon::createFromFormat('Y-m-d', $selectedDate, $timezone)
                ->endOfDay()
                ->setTimezone($appTimezone);

            $reportsQuery->whereBetween('created_at', [$startOfDay, $endOfDay]);
        }

        // Filter reports by user's scheduled outlets (based on jadwal_shift)
        $user = $request->user();
        $scheduledOutlets = $user->getAccessibleOutlets();
        $scheduledOutletIds = $scheduledOutlets->pluck('id');

        // If user has schedule history, only show reports from scheduled outlets
        if ($user->hasScheduleHistory()) {
            $reportsQuery->whereIn('outlet_id', $scheduledOutletIds);
        }

        if ($selectedOutletId) {
            $reportsQuery->where('outlet_id', $selectedOutletId);
        }

        $reports = $reportsQuery
            ->with(['outlet:id,nama,kode_outlet,icon', 'user:id,name', 'item:id,nama'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Report $report) use ($timezone) {
                return [
                    'id' => $report->id,
                    'submittedAt' => $report->created_at?->timezone($timezone)->toIso8601String(),
                    'outlet' => $report->outlet?->short_name ?? $report->outlet?->nama ?? '-',
                    'reporter' => $report->user?->name ?? '-',
                    'item' => $report->item?->nama ?? '-',
                    'status' => $report->report_status,
                    'accepted' => $report->accepted,
                    'acceptedAt' => $report->accepted_at?->timezone($timezone)->toIso8601String(),
                ];
            });

        // Filter outlets dropdown based on user's assigned outlets
        $outlets = $user->getAccessibleOutlets()->sortBy('nama')
            ->map(function ($outlet) {
                return [
                    'id' => $outlet->id,
                    'name' => $outlet->short_name,
                ];
            })->values();

        return Inertia::render('Reports/Pages/Reports', [
            'reports' => $reports,
            'outlets' => $outlets,
            'filters' => [
                'outlet' => $selectedOutletId,
                'date' => $selectedDate,
            ],
        ]);
    }

    /**
     * Store a newly created stock report.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|integer|exists:outlet,id',
            'user_id' => 'required|integer|exists:users,id',
            'items' => 'required|array|min:1|max:100',
            'items.*.item_id' => 'required|integer|exists:item,id',
            'items.*.status' => 'required|in:ALMOST_OUT,OUT', // Only accept problem statuses
            'items.*.action' => 'nullable|string|max:50',
        ]);

        // Security: Verify all items belong to the specified outlet
        $outletId = $validated['outlet_id'];
        $itemIds = collect($validated['items'])->pluck('item_id')->unique();

        $validItems = DB::table('item_outlet_ownership')
            ->where('outlet_id', $outletId)
            ->whereIn('item_id', $itemIds)
            ->pluck('item_id');

        $invalidItems = $itemIds->diff($validItems);

        if ($invalidItems->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'One or more items do not belong to this outlet.',
                'invalid_item_ids' => $invalidItems->values(),
            ], 422);
        }

        // Security: Verify user has barista role
        $user = \App\Models\User::find($validated['user_id']);
        if (!$user || strtolower($user->role) !== 'barista') {
            return response()->json([
                'success' => false,
                'message' => 'Only baristas can submit reports.',
            ], 403);
        }

        // Security: Verify barista is scheduled at this outlet today
        // Only validate if user has schedule history (backward compatible)
        if ($user->hasScheduleHistory() && !$user->isScheduledTodayAtOutlet($outletId)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not scheduled to work at this outlet today.',
            ], 403);
        }

        $reportedForDate = now('Asia/Jakarta')->toDateString();
        $supportsReportedDate = Schema::hasColumn('report', 'reported_for_date');

        try {
            $reportIds = DB::transaction(function () use ($validated, $reportedForDate, $supportsReportedDate) {
                $createdIds = [];

                foreach ($validated['items'] as $item) {
                    $reportAttributes = [
                        'outlet_id' => $validated['outlet_id'],
                        'user_id' => $validated['user_id'],
                        'item_id' => $item['item_id'],
                        'report_status' => $item['status'], // Keep original: 'ALMOST_OUT' or 'OUT'
                    ];

                    if ($supportsReportedDate) {
                        $reportAttributes['reported_for_date'] = $reportedForDate;
                    }

                    $report = Report::create($reportAttributes);

                    $createdIds[] = $report->id;
                }

                return $createdIds;
            });

            return response()->json([
                'success' => true,
                'message' => 'Stock report submitted successfully!',
                'data' => [
                    'report_ids' => $reportIds,
                    'total_items' => count($reportIds),
                    'created_at' => now(),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create stock report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit stock report. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Accept a report and update item status.
     */
    public function accept(Request $request, Report $report)
    {
        // 1. Validate user is a manager (case-insensitive check)
        if (strtolower($request->user()->role) !== 'manager') {
            return response()->json([
                'success' => false,
                'message' => 'Only managers can accept reports.',
            ], 403);
        }

        // 2. Check if already accepted
        if ($report->accepted) {
            return response()->json([
                'success' => false,
                'message' => 'This report has already been accepted.',
            ], 400);
        }

        try {
            DB::transaction(function () use ($report, $request) {
                // 3. Find the item_outlet_ownership record using item_id + outlet_id
                $itemOutlet = \App\Models\ItemOutletOwnership::where('item_id', $report->item_id)
                    ->where('outlet_id', $report->outlet_id)
                    ->first();

                if (!$itemOutlet) {
                    throw new \Exception('Item-outlet relationship not found.');
                }

                // 4. Update the item's current status to match reported status
                // Normalize: 'ALMOST_OUT' -> 'almost_out', 'OUT' -> 'out_of_stock'
                $itemOutlet->current_status = $this->normalizeReportStatus($report->report_status);
                $itemOutlet->save();

                // 5. Mark report as accepted
                $report->accepted = true;
                $report->accepted_by = $request->user()->id;
                $report->accepted_at = now('Asia/Jakarta');
                $report->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Report accepted successfully!',
                'data' => [
                    'report_id' => $report->id,
                    'accepted_at' => $report->accepted_at->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to accept report', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to accept report. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * API endpoint for periodic AJAX polling.
     * Returns reports as JSON for auto-refresh without page reload.
     */
    public function poll(Request $request)
    {
        $selectedOutletId = $request->integer('outlet');
        $selectedDate = $request->string('date')->trim();

        if ($selectedDate->isEmpty()) {
            $selectedDate = now('Asia/Jakarta')->toDateString();
        } else {
            $selectedDate = $selectedDate->value();
        }

        $timezone = 'Asia/Jakarta';
        $supportsReportedDate = Schema::hasColumn('report', 'reported_for_date');

        $reportsQuery = Report::query();

        if ($supportsReportedDate) {
            $reportsQuery->where('reported_for_date', $selectedDate);
        } else {
            $startOfDay = Carbon::createFromFormat('Y-m-d', $selectedDate, $timezone)
                ->startOfDay()
                ->setTimezone(config('app.timezone', 'UTC'));
            $endOfDay = Carbon::createFromFormat('Y-m-d', $selectedDate, $timezone)
                ->endOfDay()
                ->setTimezone(config('app.timezone', 'UTC'));
            $reportsQuery->whereBetween('created_at', [$startOfDay, $endOfDay]);
        }

        if ($selectedOutletId) {
            $reportsQuery->where('outlet_id', $selectedOutletId);
        }

        $reports = $reportsQuery
            ->with(['outlet:id,nama,kode_outlet,icon', 'user:id,name', 'item:id,nama'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (Report $report) use ($timezone) {
                return [
                    'id' => $report->id,
                    'submittedAt' => $report->created_at?->timezone($timezone)->toIso8601String(),
                    'outlet' => $report->outlet?->short_name ?? $report->outlet?->nama ?? '-',
                    'reporter' => $report->user?->name ?? '-',
                    'item' => $report->item?->nama ?? '-',
                    'status' => $report->report_status,
                    'accepted' => $report->accepted,
                    'acceptedAt' => $report->accepted_at?->timezone($timezone)->toIso8601String(),
                ];
            });

        $pendingCount = $reports->where('accepted', false)->count();

        return response()->json([
            'success' => true,
            'data' => $reports,
            'meta' => [
                'total' => $reports->count(),
                'pending' => $pendingCount,
                'polled_at' => now($timezone)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete all reports (reset).
     */
    public function reset()
    {
        try {
            $count = Report::count();
            Report::query()->delete();

            return redirect()
                ->route('reports.index')
                ->with('success', "All {$count} reports have been deleted successfully!");

        } catch (\Exception $e) {
            Log::error('Failed to reset reports', [
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('reports.index')
                ->with('error', 'Failed to delete reports. Please try again.');
        }
    }

    /**
     * Normalize report status from frontend format to database format.
     * Maps: 'ALMOST_OUT' → 'almost_out', 'OUT' → 'out_of_stock'
     */
    private function normalizeReportStatus(string $status): string
    {
        $statusMap = [
            'ALMOST_OUT' => 'almost_out',
            'OUT' => 'out_of_stock',
        ];

        return $statusMap[$status] ?? $status;
    }
}
