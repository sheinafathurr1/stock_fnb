<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockReportController extends Controller
{
    /**
     * Display the stock report for the selected outlet.
     */
    public function show(Request $request)
    {
        $requestedOutlet = $request->query('outlet');

        // If no outlet parameter provided or empty string, redirect to home
        if (empty($requestedOutlet) || trim($requestedOutlet) === '') {
            return redirect('/')
                ->with('error', 'Please select an outlet to continue.');
        }

        // Trim the outlet parameter
        $requestedOutlet = trim($requestedOutlet);

        // Build query with filters
        $baristaPrefixedName = str_starts_with(strtolower($requestedOutlet), 'barista ')
            ? $requestedOutlet
            : 'Barista ' . $requestedOutlet;

        $outlet = Outlet::query()
            ->where(function ($query) use ($requestedOutlet, $baristaPrefixedName) {
                $query->where('nama', $requestedOutlet)
                    ->orWhere('nama', $baristaPrefixedName)
                    ->orWhere('kode_outlet', $requestedOutlet);
            })
            ->first();

        // If outlet not found, redirect to home with error
        if (!$outlet) {
            return redirect('/')
                ->with('error', 'Outlet "' . $requestedOutlet . '" not found. Please select a valid outlet.');
        }

        // Load stock items for the outlet
        $stockItems = Item::query()
            ->join('item_outlet_ownership as ownership', 'ownership.item_id', '=', 'item.id')
            ->where('ownership.outlet_id', $outlet->id)
            ->where('item.deleted', false)
            ->orderBy('item.nama')
            ->get([
                'item.id as id',
                'item.nama as name',
                'ownership.current_status as status',
            ])
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'name' => $record->name,
                    'status' => $record->status ?? 'in_stock',
                ];
            });

        // Filter outlets based on user's assigned outlets (if logged in)
        $user = $request->user();
        if ($user) {
            $outletsList = $user->getAccessibleOutlets()->sortBy('nama');
        } else {
            // For public access (not logged in), show all outlets
            $outletsList = Outlet::orderBy('nama')->get();
        }

        $outlets = $outletsList->map(function ($outletItem) {
            return [
                'id' => $outletItem->id,
                'name' => $outletItem->short_name,
                'icon' => $outletItem->icon,
            ];
        })->values();

        $selectedOutletData = [
            'id' => $outlet->id,
            'name' => $outlet->short_name,
        ];

        return Inertia::render('Reports/Pages/StockReport', [
            'selectedOutlet' => $selectedOutletData,
            'outlets' => $outlets,
            'stockItems' => $stockItems,
            'todayDate' => now('Asia/Jakarta')->toDateString(),
        ]);
    }
}
