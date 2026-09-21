<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemOutletOwnership;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Timezone the outlets operate in; a "day" of reporting is measured here.
     */
    private const TIMEZONE = 'Asia/Jakarta';

    /**
     * Rows per page, shared by the paginated view and its polling endpoint so
     * a refresh cannot silently swap the page out from under the manager.
     */
    private const PER_PAGE = 20;

    /**
     * Statuses a barista may report. READY is the restock signal: it is how an
     * item that ran out gets put back on the shelf.
     */
    private const REPORTABLE_STATUSES = ['READY', 'ALMOST_OUT', 'OUT'];

    /**
     * Reported status to the stock status it sets once a manager accepts it.
     */
    private const STATUS_MAP = [
        'READY' => 'in_stock',
        'ALMOST_OUT' => 'almost_out',
        'OUT' => 'out_of_stock',
    ];

    /**
     * Display reports with optional outlet filtering.
     */
    public function index(Request $request): Response
    {
        $filters = $this->reportFilters($request);

        $reports = $this->scopedReportsQuery($request->user(), $filters)
            ->with(['outlet:id,nama,kode_outlet,icon', 'user:id,name', 'item:id,nama'])
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Report $report) => $this->presentReport($report));

        // Filter outlets dropdown based on user's assigned outlets
        $outlets = $request->user()->getAccessibleOutlets()->sortBy('nama')
            ->map(function ($outlet) {
                return [
                    'id' => $outlet->id,
                    'name' => $outlet->short_name,
                ];
            })->values();

        return Inertia::render('Reports/Pages/Reports', [
            'reports' => $reports,
            'outlets' => $outlets,
            // Counted over the whole filtered day, not just the page on
            // screen, so the badge means "still waiting on me".
            'pendingCount' => $this->scopedReportsQuery($request->user(), $filters)
                ->where('accepted', false)
                ->count(),
            'filters' => [
                'outlet' => $filters['outlet'],
                'date' => $filters['date'],
            ],
        ]);
    }

    /**
     * Read the outlet/date filters shared by index, poll and reset.
     *
     * An unparseable date falls back to today rather than erroring, so a
     * hand-edited URL cannot take the page down.
     */
    private function reportFilters(Request $request): array
    {
        $selectedOutletId = $request->integer('outlet') ?: null;
        $selectedDateInput = $request->string('date')->trim();
        $selectedDate = now(self::TIMEZONE)->toDateString();

        if ($selectedDateInput->isNotEmpty()) {
            try {
                $selectedDate = Carbon::createFromFormat('Y-m-d', $selectedDateInput->value(), self::TIMEZONE)
                    ->toDateString();
            } catch (\Throwable $e) {
                // Ignore invalid date input and fall back to today.
            }
        }

        return [
            'outlet' => $selectedOutletId,
            'date' => $selectedDate,
        ];
    }

    /**
     * Reports for one day, limited to the outlets this user may see.
     *
     * Every read and write of the report log goes through here so no endpoint
     * can quietly serve another outlet's data.
     */
    private function scopedReportsQuery(User $user, array $filters): Builder
    {
        $query = Report::query();

        if ($this->supportsReportedDate()) {
            $query->where('reported_for_date', $filters['date']);
        } else {
            $query->whereBetween('created_at', $this->dayBoundsInAppTimezone($filters['date']));
        }

        // Users with schedule history only ever see the outlets they work at;
        // users without it (e.g. a head-office manager) see everything.
        if ($user->hasScheduleHistory()) {
            $query->whereIn('outlet_id', $user->getAccessibleOutlets()->pluck('id'));
        }

        if ($filters['outlet']) {
            $query->where('outlet_id', $filters['outlet']);
        }

        return $query;
    }

    /**
     * Shape a report row for the UI.
     */
    private function presentReport(Report $report): array
    {
        return [
            'id' => $report->id,
            'submittedAt' => $report->created_at?->timezone(self::TIMEZONE)->toIso8601String(),
            'outlet' => $report->outlet?->short_name ?? $report->outlet?->nama ?? '-',
            'reporter' => $report->user?->name ?? '-',
            'item' => $report->item?->nama ?? '-',
            'status' => $report->report_status,
            'accepted' => $report->accepted,
            'acceptedAt' => $report->accepted_at?->timezone(self::TIMEZONE)->toIso8601String(),
        ];
    }

    /**
     * Whether the report table carries the denormalised reporting date.
     *
     * Older deployments predate that column, and this runs on every request,
     * so the answer is resolved once per process.
     */
    private function supportsReportedDate(): bool
    {
        static $supported = null;

        return $supported ??= Schema::hasColumn('report', 'reported_for_date');
    }

    /**
     * The UTC-stored bounds of one Jakarta day.
     */
    private function dayBoundsInAppTimezone(string $date): array
    {
        $appTimezone = config('app.timezone', 'UTC');

        return [
            Carbon::createFromFormat('Y-m-d', $date, self::TIMEZONE)->startOfDay()->setTimezone($appTimezone),
            Carbon::createFromFormat('Y-m-d', $date, self::TIMEZONE)->endOfDay()->setTimezone($appTimezone),
        ];
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
            'items.*.status' => ['required', Rule::in(self::REPORTABLE_STATUSES)],
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
        $user = User::find($validated['user_id']);
        if (!$user || strtolower($user->role) !== 'barista') {
            return response()->json([
                'success' => false,
                'message' => 'Only baristas can submit reports.',
            ], 403);
        }

        // Security: the reporting form is public, so the submitted identity is
        // only trustworthy insofar as it matches today's approved roster. This
        // is the same roster the outlet's schedule lookup offers, so a barista
        // can never be picked here without also being selectable there.
        if (!$user->isScheduledTodayAtOutlet($outletId)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not scheduled to work at this outlet today.',
            ], 403);
        }

        $reportedForDate = now('Asia/Jakarta')->toDateString();
        $supportsReportedDate = $this->supportsReportedDate();

        try {
            $result = DB::transaction(function () use ($validated, $reportedForDate, $supportsReportedDate) {
                $createdIds = [];
                $duplicateItemIds = [];

                // Collapse repeats inside one payload: the last status a barista
                // set for an item is the one they meant.
                $items = collect($validated['items'])
                    ->keyBy(fn (array $item) => $item['item_id'])
                    ->values();

                foreach ($items as $item) {
                    $reportAttributes = [
                        'outlet_id' => $validated['outlet_id'],
                        'user_id' => $validated['user_id'],
                        'item_id' => $item['item_id'],
                        'report_status' => $item['status'], // 'READY', 'ALMOST_OUT' or 'OUT'
                    ];

                    if ($supportsReportedDate) {
                        $reportAttributes['reported_for_date'] = $reportedForDate;
                    }

                    // An identical report that no manager has acted on yet adds
                    // nothing to the queue, so skip it instead of stacking rows.
                    if ($this->hasPendingDuplicate($reportAttributes, $reportedForDate, $supportsReportedDate)) {
                        $duplicateItemIds[] = $item['item_id'];

                        continue;
                    }

                    $report = Report::create($reportAttributes);

                    $createdIds[] = $report->id;
                }

                return [$createdIds, $duplicateItemIds];
            });

            [$reportIds, $duplicateItemIds] = $result;

            return response()->json([
                'success' => true,
                'message' => $this->submissionMessage(count($reportIds), count($duplicateItemIds)),
                'data' => [
                    'report_ids' => $reportIds,
                    'total_items' => count($reportIds),
                    'duplicate_item_ids' => $duplicateItemIds,
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
     * Find an unaccepted report that already says exactly this.
     */
    private function hasPendingDuplicate(array $attributes, string $reportedForDate, bool $supportsReportedDate): bool
    {
        $query = Report::query()
            ->where('outlet_id', $attributes['outlet_id'])
            ->where('item_id', $attributes['item_id'])
            ->where('report_status', $attributes['report_status'])
            ->where('accepted', false);

        if ($supportsReportedDate) {
            $query->where('reported_for_date', $reportedForDate);
        } else {
            $query->whereBetween('created_at', $this->dayBoundsInAppTimezone($reportedForDate));
        }

        return $query->exists();
    }

    /**
     * Describe what a submission actually did.
     */
    private function submissionMessage(int $created, int $duplicates): string
    {
        if ($created === 0) {
            return 'Those items were already reported and are waiting for a manager.';
        }

        if ($duplicates === 0) {
            return 'Stock report submitted successfully!';
        }

        return sprintf(
            'Stock report submitted successfully! %d item%s already reported and waiting for a manager.',
            $duplicates,
            $duplicates === 1 ? ' was' : 's were',
        );
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

        // 2. A manager may only act on the outlets they are scheduled at,
        //    matching what the reports list is willing to show them.
        $user = $request->user();
        if ($user->hasScheduleHistory()
            && !$user->getAccessibleOutlets()->pluck('id')->contains($report->outlet_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this outlet.',
            ], 403);
        }

        // 3. Check if already accepted
        if ($report->accepted) {
            return response()->json([
                'success' => false,
                'message' => 'This report has already been accepted.',
            ], 400);
        }

        try {
            DB::transaction(function () use ($report, $request) {
                // 4. Find the item_outlet_ownership record using item_id + outlet_id
                $itemOutlet = ItemOutletOwnership::where('item_id', $report->item_id)
                    ->where('outlet_id', $report->outlet_id)
                    ->first();

                if (!$itemOutlet) {
                    throw new \Exception('Item-outlet relationship not found.');
                }

                // 5. Update the item's current status to match reported status
                // Normalize: 'READY' -> 'in_stock', 'ALMOST_OUT' -> 'almost_out', 'OUT' -> 'out_of_stock'
                $itemOutlet->current_status = $this->normalizeReportStatus($report->report_status);
                $itemOutlet->save();

                // 6. Mark report as accepted
                $report->accepted = true;
                $report->accepted_by = $request->user()->id;
                // Store UTC like every other timestamp; presentReport() is what
                // converts to Jakarta for display. Writing Jakarta wall-clock
                // time here made accepted_at read seven hours into the future.
                $report->accepted_at = now();
                $report->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Report accepted successfully!',
                'data' => [
                    'report_id' => $report->id,
                    'accepted_at' => $report->accepted_at->timezone(self::TIMEZONE)->toIso8601String(),
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
        $request->validate([
            'outlet' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $filters = $this->reportFilters($request);

        // Poll the page the manager is actually looking at, and count pending
        // across the whole filtered day rather than just the rows on screen.
        $reports = $this->scopedReportsQuery($user, $filters)
            ->with(['outlet:id,nama,kode_outlet,icon', 'user:id,name', 'item:id,nama'])
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE, ['*'], 'page', $request->integer('page') ?: 1);

        $pendingCount = $this->scopedReportsQuery($user, $filters)
            ->where('accepted', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => $reports->getCollection()->map(fn (Report $report) => $this->presentReport($report))->values(),
            'meta' => [
                'total' => $reports->total(),
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'pending' => $pendingCount,
                'polled_at' => now(self::TIMEZONE)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete the reports currently in view.
     *
     * Deliberately scoped to the same outlet/date filters the manager is
     * looking at: "reset" used to wipe every report for every outlet and every
     * day, which is not recoverable and not what the button appears to do.
     */
    public function reset(Request $request)
    {
        $filters = $this->reportFilters($request);

        try {
            $count = $this->scopedReportsQuery($request->user(), $filters)->count();

            $this->scopedReportsQuery($request->user(), $filters)->delete();

            return redirect()
                ->route('reports.index', array_filter([
                    'outlet' => $filters['outlet'],
                    'date' => $filters['date'],
                ]))
                ->with('success', "{$count} report(s) for {$filters['date']} have been deleted successfully!");

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
     * Normalize a reported status to the stock status it sets.
     * Maps: 'READY' → 'in_stock', 'ALMOST_OUT' → 'almost_out', 'OUT' → 'out_of_stock'
     */
    private function normalizeReportStatus(string $status): string
    {
        return self::STATUS_MAP[$status] ?? $status;
    }
}
