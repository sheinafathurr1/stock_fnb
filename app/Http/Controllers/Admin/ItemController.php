<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Kategori;
use App\Models\Outlet;
use App\Models\ItemOutletOwnership;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    /**
     * Display a listing of items.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer', 'exists:kategori,id'],
            'outlet' => ['nullable', 'integer', 'exists:outlet,id'],
        ]);

        $itemsQuery = Item::query()
            ->select(['id', 'nama', 'kategori_id', 'deleted'])
            ->where('deleted', false);

        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $itemsQuery->where(function ($query) use ($searchTerm) {
                $query->where('nama', 'like', "%{$searchTerm}%");
            });
        }

        if (!empty($filters['category'])) {
            $itemsQuery->where('kategori_id', $filters['category']);
        }

        if (!empty($filters['outlet'])) {
            $itemsQuery->whereHas('ownerships', function ($query) use ($filters) {
                $query->where('outlet_id', $filters['outlet']);
            });
        }

        $totalFilteredItems = (clone $itemsQuery)->count();

        $items = (clone $itemsQuery)
            ->with([
                'kategori:id,nama',
                'ownerships' => function ($query) use ($filters) {
                    $query
                        ->select(['id', 'item_id', 'outlet_id', 'current_status'])
                        ->with('outlet:id,nama,kode_outlet,icon')
                        ->when(
                            !empty($filters['outlet']),
                            fn ($ownershipQuery) => $ownershipQuery->where('outlet_id', $filters['outlet'])
                        );
                },
            ])
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Item $item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'kategori_id' => $item->kategori_id,
                    'kategori' => $item->kategori ? [
                        'id' => $item->kategori->id,
                        'nama' => $item->kategori->nama,
                    ] : null,
                    'deleted' => (bool) $item->deleted,
                    'outlets' => $item->ownerships->map(function (ItemOutletOwnership $ownership) {
                        $outlet = $ownership->outlet;
                        return [
                            'id' => $ownership->outlet_id,
                            'nama' => $outlet?->nama,
                            'short_name' => $outlet?->short_name ?? $outlet?->nama,
                            'icon' => $outlet?->icon,
                            'current_status' => strtolower($ownership->current_status),
                        ];
                    })->values(),
                ];
            });

        $kategoris = Kategori::all();

        // Filter outlets based on user's assigned outlets
        $outletModels = $request->user()->getAccessibleOutlets();

        $outlets = $outletModels->map(function($outlet) {
            return [
                'id' => $outlet->id,
                'nama' => $outlet->short_name,
                'kode_outlet' => $outlet->kode_outlet,
                'icon' => $outlet->icon,
            ];
        });

        // Count the same stock the table below is showing. Counting every
        // outlet in the system here made the "Almost Out" and "Out of Stock"
        // cards contradict the rows underneath them whenever a filter was set.
        $statusBreakdown = ItemOutletOwnership::query()
            ->whereIn('outlet_id', $outletModels->pluck('id'))
            ->whereHas('item', function ($query) use ($filters) {
                $query->where('deleted', false);

                if (!empty($filters['search'])) {
                    $query->where('nama', 'like', "%{$filters['search']}%");
                }

                if (!empty($filters['category'])) {
                    $query->where('kategori_id', $filters['category']);
                }
            })
            ->when(
                !empty($filters['outlet']),
                fn ($query) => $query->where('outlet_id', $filters['outlet'])
            )
            ->select('current_status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status');

        $stockSummary = [
            'totalItems' => $totalFilteredItems,
            'categoryCount' => $kategoris->count(),
            'outletCount' => $outletModels->count(),
            'almostOut' => (int) ($statusBreakdown['almost_out'] ?? 0),
            'outOfStock' => (int) ($statusBreakdown['out_of_stock'] ?? 0),
        ];

        return Inertia::render('Inventory/Pages/Dashboard', [
            'items' => $items,
            'kategoris' => $kategoris,
            'outlets' => $outlets,
            'stockSummary' => $stockSummary,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? '',
                'outlet' => $filters['outlet'] ?? '',
            ],
        ]);
    }

    /**
     * Show the form for creating a new item.
     */
    public function create(Request $request): Response
    {
        $kategoris = Kategori::all();

        // Filter outlets based on user's assigned outlets
        $outlets = $request->user()->getAccessibleOutlets()->map(function($outlet) {
            return [
                'id' => $outlet->id,
                'nama' => $outlet->short_name,
                'kode_outlet' => $outlet->kode_outlet,
                'icon' => $outlet->icon,
            ];
        });

        return Inertia::render('Inventory/Pages/ItemForm', [
            'kategoris' => $kategoris,
            'outlets' => $outlets,
            'mode' => 'create',
        ]);
    }

    /**
     * Store a newly created item.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'kategori_id' => 'required|exists:kategori,id',
            'deleted' => 'sometimes|boolean',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => ['integer', Rule::in($this->accessibleOutletIds($request))],
            'ownership_statuses' => 'nullable|array',
            'ownership_statuses.*' => 'in:in_stock,almost_out,out_of_stock',
        ], [], ['outlet_ids.*' => 'outlet']);

        // Create the item
        $item = Item::create([
            'nama' => $request->nama,
            'kategori_id' => $request->kategori_id,
            'deleted' => $request->boolean('deleted', false),
        ]);

        $ownershipStatuses = collect($request->input('ownership_statuses', []))
            ->mapWithKeys(fn ($status, $key) => [
                (int) $key => $this->normalizeOwnershipStatus($status),
            ]);

        // Create ownership relationships
        if ($request->has('outlet_ids')) {
            foreach ($request->outlet_ids as $outletId) {
                ItemOutletOwnership::create([
                    'item_id' => $item->id,
                    'outlet_id' => $outletId,
                    'current_status' => $ownershipStatuses->get((int) $outletId, 'in_stock'),
                ]);
            }
        }

        return redirect()->route('dashboard')->with('success', 'Item created successfully!');
    }

    /**
     * Show the form for editing the specified item.
     */
    public function edit(Request $request, Item $item): Response
    {
        $kategoris = Kategori::all();

        // Filter outlets based on user's assigned outlets
        $outlets = $request->user()->getAccessibleOutlets()->map(function($outlet) {
            return [
                'id' => $outlet->id,
                'nama' => $outlet->short_name,
                'kode_outlet' => $outlet->kode_outlet,
                'icon' => $outlet->icon,
            ];
        });
        $item->load(['kategori', 'outlets']);

        return Inertia::render('Inventory/Pages/ItemForm', [
            'item' => $item,
            'kategoris' => $kategoris,
            'outlets' => $outlets,
            'mode' => 'edit',
        ]);
    }

    /**
     * Update the specified item.
     */
    public function update(Request $request, Item $item): RedirectResponse
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'kategori_id' => 'required|exists:kategori,id',
            'deleted' => 'sometimes|boolean',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => ['integer', Rule::in($this->accessibleOutletIds($request))],
            'ownership_statuses' => 'nullable|array',
            'ownership_statuses.*' => 'in:in_stock,almost_out,out_of_stock',
        ], [], ['outlet_ids.*' => 'outlet']);

        // Update the item
        $attributes = [
            'nama' => $request->nama,
            'kategori_id' => $request->kategori_id,
        ];

        if ($request->has('deleted')) {
            $attributes['deleted'] = $request->boolean('deleted');
        }

        $item->update($attributes);

        $requestedOutletIds = collect($request->outlet_ids)->map(fn ($id) => (int) $id);
        $ownershipStatuses = collect($request->input('ownership_statuses', []))
            ->mapWithKeys(fn ($status, $key) => [
                (int) $key => $this->normalizeOwnershipStatus($status),
            ]);

        $currentOwnerships = $item->ownerships()->get()->keyBy('outlet_id');

        // Only detach outlets this manager can actually see. The edit form only
        // offers their own outlets, so without this a scoped manager saving an
        // item would wipe its ownership at every outlet they cannot see.
        $accessibleOutletIds = collect($this->accessibleOutletIds($request));

        $toDetach = $currentOwnerships->keys()
            ->intersect($accessibleOutletIds)
            ->diff($requestedOutletIds);

        if ($toDetach->isNotEmpty()) {
            ItemOutletOwnership::where('item_id', $item->id)
                ->whereIn('outlet_id', $toDetach)
                ->delete();
        }

        foreach ($requestedOutletIds as $outletId) {
            $desiredStatus = $ownershipStatuses->get(
                $outletId,
                optional($currentOwnerships->get($outletId))->current_status ?? 'in_stock'
            );

            if ($currentOwnerships->has($outletId)) {
                $ownership = $currentOwnerships->get($outletId);

                if ($ownership->current_status !== $desiredStatus) {
                    $ownership->update(['current_status' => $desiredStatus]);
                }
            } else {
                ItemOutletOwnership::create([
                    'item_id' => $item->id,
                    'outlet_id' => $outletId,
                    'current_status' => $desiredStatus,
                ]);
            }
        }

        return redirect()->route('dashboard')->with('success', 'Item updated successfully!');
    }

    /**
     * Remove the specified item.
     */
    public function destroy(Item $item): RedirectResponse
    {
        $item->update(['deleted' => true]);

        return redirect()->route('dashboard')->with('success', 'Item deleted successfully!');
    }

    /**
     * Outlet IDs this user is allowed to attach items to.
     *
     * Without this, a manager scoped to one outlet could write stock into
     * any other outlet simply by posting its ID.
     */
    private function accessibleOutletIds(Request $request): array
    {
        return $request->user()->getAccessibleOutlets()->pluck('id')->all();
    }

    /**
     * Normalize ownership status input.
     */
    private function normalizeOwnershipStatus(?string $status): string
    {
        $value = strtolower((string) $status);
        $allowed = ['in_stock', 'almost_out', 'out_of_stock'];

        return in_array($value, $allowed, true) ? $value : 'in_stock';
    }
}
