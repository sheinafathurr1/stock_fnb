<?php

use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\API\ScheduleLookupController;
use App\Http\Controllers\Public\StockReportController;
use App\Models\Outlet;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Get outlets from our new outlet table
    $outlets = Outlet::orderBy('nama')
        ->get()
        ->map(function($outlet) {
            return [
                'id' => $outlet->kode_outlet,
                'name' => $outlet->short_name,  // Use short_name to remove "Barista" prefix
                'icon' => $outlet->icon,
            ];
        });

    return Inertia::render('LandingPage/Pages/LandingPage', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
        'outlets' => $outlets,
    ]);
});

Route::get('/stock-report', [StockReportController::class, 'show'])
    ->name('stock-report');

Route::get('/dashboard', [ItemController::class, 'index'])->middleware(['auth', 'verified', 'role:manager'])->name('dashboard');

Route::middleware(['auth', 'verified', 'role:manager'])->group(function () {
    Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/api/reports/poll', [ReportController::class, 'poll'])->name('reports.poll');
    Route::post('/reports/{report}/accept', [ReportController::class, 'accept'])->name('reports.accept');
    Route::delete('/reports/reset', [ReportController::class, 'reset'])->name('reports.reset');
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::get('/api/outlets/{outlet}/schedules/today', ScheduleLookupController::class)
    ->name('api.outlets.schedules.today');

Route::post('/api/reports', [ReportController::class, 'store'])
    ->middleware(['throttle:10,1'])
    ->name('api.reports.store');
