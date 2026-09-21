# Stock Report Application - Developer Documentation

## Table of Contents

1. [Project Overview](#project-overview)
2. [Frontend - Item Inventory Dashboard](#frontend---item-inventory-dashboard)
3. [Backend - Report API Endpoint](#backend---report-api-endpoint)
4. [Database - Report and ReportLine Models](#database---report-and-reportline-models)
5. [Auth - User Authentication Flow](#auth---user-authentication-flow)
6. [Common Issues & Solutions](#common-issues--solutions)
7. [Best Practices](#best-practices)

---

## Project Overview

**Stock Report** is a multi-outlet inventory management system built with Laravel + Inertia.js + React. It allows managers to track inventory across multiple outlets and enables baristas to submit stock reports.

**Technology Stack:**
- **Backend**: Laravel 12.x (PHP 8.2+)
- **Frontend**: React 18 with Inertia.js
- **Database**: MySQL
- **UI Components**: shadcn/ui + Tailwind CSS
- **State Management**: React hooks + Inertia server-side state
- **Animations**: GSAP, CountUp.js

**Key Features:**
- Multi-outlet inventory management
- Real-time stock status tracking (In Stock, Almost Out, Out of Stock)
- Barista stock reporting system
- Role-based access control (Manager/Barista)
- Date-filtered reports with pagination
- Soft delete for items

---

## Frontend - Item Inventory Dashboard

### 1. Overview

**What it does:**
The Item Inventory Dashboard is the main interface for managers to view, search, filter, and manage inventory items across multiple outlets. It provides real-time statistics, status tracking, and CRUD operations for items.

**Why it's important:**
- Central hub for inventory management
- Provides at-a-glance stock health metrics
- Enables quick filtering by category, outlet, and search
- Supports multi-outlet item assignments with individual status tracking

**Who uses it:**
- **Managers** (primary users with full CRUD access)

### 2. Location & File Structure

**Frontend Component:**
```
/resources/js/Features/Inventory/Pages/Dashboard.jsx
```

**Related Components:**
```
/resources/js/Features/Inventory/Components/
  ├── ItemFormDialog.jsx          # Create/Edit item dialog
  ├── DeleteConfirmDialog.jsx     # Delete confirmation dialog

/resources/js/Shared/
  ├── Layouts/AuthenticatedLayout.jsx
  ├── Components/                 # Reusable components (Button, Input, etc.)

/resources/js/Components/ui/      # shadcn/ui components
  ├── card.jsx
  ├── button.jsx
  ├── input.jsx
  ├── pagination.jsx
  └── ...
```

**Backend Controller:**
```
/app/Http/Controllers/Admin/ItemController.php
```

**Routes:**
```php
// web.php
Route::get('/dashboard', [ItemController::class, 'index'])->name('dashboard');
Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
Route::post('/items', [ItemController::class, 'store'])->name('items.store');
Route::get('/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
Route::delete('/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
```

### 3. Key Files & Their Purpose

#### Backend: ItemController.php

**Purpose:** Handles all item-related operations (index, create, store, edit, update, destroy)

**Key Methods:**

1. **`index(Request $request): Response`**
   - Fetches paginated items with filters (search, category, outlet)
   - Calculates stock summary statistics
   - Returns data to Dashboard component via Inertia

2. **`store(Request $request): RedirectResponse`**
   - Validates and creates new item
   - Creates outlet ownership relationships
   - Sets initial stock status per outlet

3. **`update(Request $request, Item $item): RedirectResponse`**
   - Updates item details
   - Syncs outlet relationships (add/remove)
   - Updates stock status per outlet

4. **`destroy(Item $item): RedirectResponse`**
   - Soft deletes item (sets `deleted = true`)

#### Frontend: Dashboard.jsx

**Purpose:** Main inventory dashboard page component

**Key Features:**
- **Statistics Cards**: Total Items, Categories, Almost Out, Out of Stock
- **Search & Filters**: Real-time search, category filter, outlet filter
- **Items Table**: Displays items with outlets and status badges
- **Pagination**: Laravel pagination with preserveScroll
- **Dialogs**: Create, Edit, Delete modals

**Props Received from Backend:**
```javascript
{
  items: {
    data: [...],      // Array of item objects
    links: [...],     // Pagination links
    meta: {...}       // Pagination metadata
  },
  kategoris: [...],   // Categories array
  outlets: [...],     // Outlets array
  stockSummary: {
    totalItems: 0,
    categoryCount: 0,
    outletCount: 0,
    almostOut: 0,
    outOfStock: 0
  },
  filters: {
    search: '',
    category: '',
    outlet: ''
  },
  flash: {            // Flash messages
    success: '',
    error: ''
  }
}
```

### 4. Data Flow & How It Works

#### Page Load Flow (Inertia)

```
1. User visits /dashboard
   ↓
2. Laravel Route → ItemController@index
   ↓
3. Controller queries database:
   - Items with filters
   - Categories (Kategori)
   - Outlets
   - Stock summary statistics
   ↓
4. Controller returns Inertia response:
   Inertia::render('Inventory/Pages/Dashboard', $props)
   ↓
5. Inertia sends JSON to frontend
   ↓
6. React Dashboard component receives props
   ↓
7. Component renders with data
```

#### Filter/Search Flow

```
1. User types in search box
   ↓
2. useState updates searchTerm
   ↓
3. useEffect debounces (400ms)
   ↓
4. applyFilters() called
   ↓
5. router.get('/dashboard', {search, category, outlet})
   ↓
6. Inertia makes XHR request (preserveScroll: true)
   ↓
7. Backend re-queries with filters
   ↓
8. Inertia updates component props
   ↓
9. React re-renders table
```

#### CRUD Operations Flow

**Create/Edit Item:**
```
1. User clicks "Add Item" button
   ↓
2. setCreateDialogOpen(true)
   ↓
3. ItemFormDialog component renders
   ↓
4. User fills form (name, category, outlets, statuses)
   ↓
5. Form submits via Inertia useForm()
   ↓
6. POST /items or PUT /items/{id}
   ↓
7. Backend validates and saves
   ↓
8. Redirects to dashboard with flash message
   ↓
9. Dashboard re-renders with new data
   ↓
10. Toast notification shows success
```

### 5. How to Modify/Add New Features

#### Example 1: Add a New Filter (e.g., Filter by Stock Status)

**Step 1: Update Backend Controller**

```php
// ItemController.php - index() method

public function index(Request $request): Response
{
    $filters = $request->validate([
        'search' => ['nullable', 'string', 'max:255'],
        'category' => ['nullable', 'integer', 'exists:kategori,id'],
        'outlet' => ['nullable', 'integer', 'exists:outlet,id'],
        'status' => ['nullable', 'string', 'in:in_stock,almost_out,out_of_stock'], // NEW
    ]);

    $itemsQuery = Item::query()
        ->select(['id', 'nama', 'kategori_id', 'deleted'])
        ->where('deleted', false);

    // ... existing filters ...

    // Add status filter
    if (!empty($filters['status'])) {
        $itemsQuery->whereHas('ownerships', function ($query) use ($filters) {
            $query->where('current_status', $filters['status']);
        });
    }

    // ... rest of the code ...

    return Inertia::render('Inventory/Pages/Dashboard', [
        // ... existing props ...
        'filters' => [
            'search' => $filters['search'] ?? '',
            'category' => $filters['category'] ?? '',
            'outlet' => $filters['outlet'] ?? '',
            'status' => $filters['status'] ?? '', // NEW
        ],
    ]);
}
```

**Step 2: Update Frontend Component**

```jsx
// Dashboard.jsx

export default function Dashboard({ items, kategoris, outlets, stockSummary, filters }) {
    // Add state for new filter
    const [selectedStatus, setSelectedStatus] = useState(filters.status ?? '');

    // Update useEffect to sync with filters prop
    useEffect(() => {
        // ... existing code ...
        setSelectedStatus(filters.status ?? '');
    }, [filters.search, filters.category, filters.outlet, filters.status]); // Add filters.status

    // Update applyFilters function
    const applyFilters = (overrides = {}) => {
        const payload = {};

        // ... existing filters ...

        const statusValue = overrides.status ?? selectedStatus;
        if (statusValue) {
            payload.status = statusValue;
        }

        router.get(route('dashboard'), payload, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    // Add handler
    const handleStatusChange = (event) => {
        const value = event.target.value;
        setSelectedStatus(value);
        applyFilters({ status: value, page: 1 });
    };

    // Update hasActiveFilters
    const hasActiveFilters = Boolean(searchTerm || selectedCategory || selectedOutlet || selectedStatus);

    // Update handleResetFilters
    const handleResetFilters = () => {
        setSearchTerm('');
        setSelectedCategory('');
        setSelectedOutlet('');
        setSelectedStatus(''); // NEW
        applyFilters({ search: '', category: '', outlet: '', status: '', page: 1 });
    };

    return (
        // ... JSX ...
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5"> {/* Change to 5 columns */}
            {/* ... existing filters ... */}

            {/* NEW: Status Filter */}
            <div className="flex flex-col gap-2">
                <label className="text-sm font-medium text-gray-700">
                    Filter by Status
                </label>
                <select
                    value={selectedStatus}
                    onChange={handleStatusChange}
                    className="h-10 w-full rounded-md border border-gray-200 bg-white px-3 text-sm text-gray-700 shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                >
                    <option value="">All Statuses</option>
                    <option value="in_stock">In Stock</option>
                    <option value="almost_out">Almost Out</option>
                    <option value="out_of_stock">Out of Stock</option>
                </select>
            </div>
        </div>
    );
}
```

#### Example 2: Add a New Field to Item (e.g., SKU)

**Step 1: Create Migration**

```bash
php artisan make:migration add_sku_to_item_table
```

```php
// database/migrations/xxxx_add_sku_to_item_table.php

public function up()
{
    Schema::table('item', function (Blueprint $table) {
        $table->string('sku', 50)->nullable()->after('nama');
        $table->index('sku'); // Add index for faster lookups
    });
}

public function down()
{
    Schema::table('item', function (Blueprint $table) {
        $table->dropColumn('sku');
    });
}
```

```bash
php artisan migrate
```

**Step 2: Update Model**

```php
// app/Models/Item.php

protected $fillable = [
    'nama',
    'sku',        // ADD THIS
    'kategori_id',
    'deleted',
];
```

**Step 3: Update Controller Validation**

```php
// ItemController.php - store() and update() methods

$request->validate([
    'nama' => 'required|string|max:255',
    'sku' => 'nullable|string|max:50|unique:item,sku', // ADD THIS
    'kategori_id' => 'required|exists:kategori,id',
    // ... rest
]);

// In update method, exclude current item from unique check:
$request->validate([
    // ...
    'sku' => ['nullable', 'string', 'max:50', 'unique:item,sku,' . $item->id],
    // ...
]);
```

**Step 4: Update Frontend Form (ItemFormDialog.jsx)**

```jsx
// ItemFormDialog.jsx

const { data, setData, post, put, processing, errors, reset } = useForm({
    nama: item?.nama || '',
    sku: item?.sku || '',  // ADD THIS
    kategori_id: item?.kategori_id || '',
    // ... rest
});

// In JSX:
<div className="flex flex-col gap-2">
    <label htmlFor="sku" className="text-sm font-medium">
        SKU (Stock Keeping Unit) <span className="text-gray-400">(optional)</span>
    </label>
    <Input
        id="sku"
        type="text"
        value={data.sku}
        onChange={(e) => setData('sku', e.target.value)}
        placeholder="e.g., ITEM-001"
        maxLength={50}
    />
    {errors.sku && (
        <p className="text-sm text-red-600">{errors.sku}</p>
    )}
</div>
```

**Step 5: Display in Table**

```jsx
// Dashboard.jsx - in the table <tbody>

<td className="px-6 py-4">
    <div className="font-medium text-gray-900">{item.nama}</div>
    {item.sku && (
        <p className="text-xs text-gray-500">SKU: {item.sku}</p>
    )}
</td>
```

### 6. Code Examples

#### Example: Fetching Items with Filters (Backend)

```php
// app/Http/Controllers/Admin/ItemController.php

public function index(Request $request): Response
{
    // Validate filters
    $filters = $request->validate([
        'search' => ['nullable', 'string', 'max:255'],
        'category' => ['nullable', 'integer', 'exists:kategori,id'],
        'outlet' => ['nullable', 'integer', 'exists:outlet,id'],
    ]);

    // Build query
    $itemsQuery = Item::query()
        ->select(['id', 'nama', 'kategori_id', 'deleted'])
        ->where('deleted', false);

    // Apply search filter
    if (!empty($filters['search'])) {
        $searchTerm = $filters['search'];
        $itemsQuery->where(function ($query) use ($searchTerm) {
            $query->where('nama', 'like', "%{$searchTerm}%");
        });
    }

    // Apply category filter
    if (!empty($filters['category'])) {
        $itemsQuery->where('kategori_id', $filters['category']);
    }

    // Apply outlet filter
    if (!empty($filters['outlet'])) {
        $itemsQuery->whereHas('ownerships', function ($query) use ($filters) {
            $query->where('outlet_id', $filters['outlet']);
        });
    }

    // Paginate with eager loading
    $items = $itemsQuery
        ->with([
            'kategori:id,nama',
            'ownerships' => function ($query) use ($filters) {
                $query->select(['id', 'item_id', 'outlet_id', 'current_status'])
                    ->with('outlet:id,nama,kode_outlet,icon')
                    ->when(
                        !empty($filters['outlet']),
                        fn ($ownershipQuery) => $ownershipQuery->where('outlet_id', $filters['outlet'])
                    );
            },
        ])
        ->orderBy('nama')
        ->paginate(20)
        ->withQueryString(); // Preserve query params in pagination links

    // Calculate statistics
    $statusBreakdown = ItemOutletOwnership::query()
        ->whereHas('item', function($query) {
            $query->where('deleted', false); // Only count active items
        })
        ->select('current_status', DB::raw('COUNT(*) as aggregate'))
        ->groupBy('current_status')
        ->pluck('aggregate', 'current_status');

    $stockSummary = [
        'totalItems' => Item::where('deleted', false)->count(),
        'categoryCount' => Kategori::count(),
        'outletCount' => Outlet::count(),
        'almostOut' => (int) ($statusBreakdown['almost_out'] ?? 0),
        'outOfStock' => (int) ($statusBreakdown['out_of_stock'] ?? 0),
    ];

    // Return Inertia response
    return Inertia::render('Inventory/Pages/Dashboard', [
        'items' => $items,
        'kategoris' => Kategori::all(),
        'outlets' => Outlet::all(),
        'stockSummary' => $stockSummary,
        'filters' => $filters,
    ]);
}
```

#### Example: Using Inertia Form in React

```jsx
// ItemFormDialog.jsx (simplified)

import { useForm } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

export default function ItemFormDialog({ open, onOpenChange, item = null, kategoris, outlets, mode = 'create' }) {
    const { data, setData, post, put, processing, errors, reset } = useForm({
        nama: item?.nama || '',
        kategori_id: item?.kategori_id || '',
        outlet_ids: item?.outlets?.map(o => o.id) || [],
        ownership_statuses: item?.outlets?.reduce((acc, o) => {
            acc[o.id] = o.current_status;
            return acc;
        }, {}) || {},
    });

    const handleSubmit = (e) => {
        e.preventDefault();

        const options = {
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        if (mode === 'create') {
            post(route('items.store'), options);
        } else {
            put(route('items.update', item.id), options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'create' ? 'Add New Item' : 'Edit Item'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Item Name */}
                    <div className="flex flex-col gap-2">
                        <label htmlFor="nama" className="text-sm font-medium">
                            Item Name <span className="text-red-500">*</span>
                        </label>
                        <Input
                            id="nama"
                            type="text"
                            value={data.nama}
                            onChange={(e) => setData('nama', e.target.value)}
                            placeholder="Enter item name"
                            required
                        />
                        {errors.nama && (
                            <p className="text-sm text-red-600">{errors.nama}</p>
                        )}
                    </div>

                    {/* Category Selection */}
                    <div className="flex flex-col gap-2">
                        <label htmlFor="kategori_id" className="text-sm font-medium">
                            Category <span className="text-red-500">*</span>
                        </label>
                        <select
                            id="kategori_id"
                            value={data.kategori_id}
                            onChange={(e) => setData('kategori_id', e.target.value)}
                            className="h-10 w-full rounded-md border border-gray-200 bg-white px-3"
                            required
                        >
                            <option value="">Select a category</option>
                            {kategoris.map((kategori) => (
                                <option key={kategori.id} value={kategori.id}>
                                    {kategori.nama}
                                </option>
                            ))}
                        </select>
                        {errors.kategori_id && (
                            <p className="text-sm text-red-600">{errors.kategori_id}</p>
                        )}
                    </div>

                    {/* Outlet Assignment (checkboxes with status dropdowns) */}
                    <div className="flex flex-col gap-2">
                        <label className="text-sm font-medium">
                            Assign to Outlets <span className="text-red-500">*</span>
                        </label>
                        <div className="space-y-2">
                            {outlets.map((outlet) => (
                                <div key={outlet.id} className="flex items-center gap-4 p-2 border rounded">
                                    <input
                                        type="checkbox"
                                        id={`outlet-${outlet.id}`}
                                        checked={data.outlet_ids.includes(outlet.id)}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setData('outlet_ids', [...data.outlet_ids, outlet.id]);
                                            } else {
                                                setData('outlet_ids', data.outlet_ids.filter(id => id !== outlet.id));
                                            }
                                        }}
                                    />
                                    <label htmlFor={`outlet-${outlet.id}`} className="flex-1">
                                        {outlet.icon} {outlet.nama}
                                    </label>

                                    {/* Status dropdown (only shown if outlet is checked) */}
                                    {data.outlet_ids.includes(outlet.id) && (
                                        <select
                                            value={data.ownership_statuses[outlet.id] || 'in_stock'}
                                            onChange={(e) => setData('ownership_statuses', {
                                                ...data.ownership_statuses,
                                                [outlet.id]: e.target.value,
                                            })}
                                            className="text-sm border rounded px-2 py-1"
                                        >
                                            <option value="in_stock">In Stock</option>
                                            <option value="almost_out">Almost Out</option>
                                            <option value="out_of_stock">Out of Stock</option>
                                        </select>
                                    )}
                                </div>
                            ))}
                        </div>
                        {errors.outlet_ids && (
                            <p className="text-sm text-red-600">{errors.outlet_ids}</p>
                        )}
                    </div>

                    {/* Submit Buttons */}
                    <div className="flex justify-end gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : (mode === 'create' ? 'Create Item' : 'Update Item')}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
```

### 7. Dependencies & Commands

**npm packages used in Dashboard:**
```json
{
  "@inertiajs/react": "^1.0.0",
  "react": "^18.2.0",
  "react-dom": "^18.2.0",
  "gsap": "^3.12.0",
  "react-countup": "^6.4.0",
  "lucide-react": "^0.263.1",
  "sonner": "^1.0.0"
}
```

**Laravel Commands:**
```bash
# Create new item
php artisan make:model Item -m

# Create controller
php artisan make:controller Admin/ItemController

# Run migrations
php artisan migrate

# Create seed data
php artisan db:seed

# Clear caches
php artisan optimize:clear
```

**Development:**
```bash
# Watch for frontend changes
npm run dev

# Build for production
npm run build

# Run Laravel dev server
php artisan serve
```

---

## Backend - Report API Endpoint

### 1. Overview

**What it does:**
The Report API endpoint handles stock report submissions from baristas. It validates that items belong to the outlet, verifies user permissions, and creates report records in a database transaction.

**Why it's important:**
- Allows baristas to report stock issues (Almost Out, Out of Stock)
- Enforces data integrity (items must belong to outlet)
- Provides audit trail of stock reports
- Implements security validations (role checks, item ownership)

**Who uses it:**
- **Baristas** (submit reports via Stock Report page)
- **Managers** (view reports on Reports page)

### 2. Location & File Structure

**Controller:**
```
/app/Http/Controllers/Admin/ReportController.php
```

**Models:**
```
/app/Models/Report.php
/app/Models/ReportLine.php  (currently unused but available)
```

**Routes:**
```php
// web.php
Route::get('/reports', [ReportController::class, 'index'])
    ->name('reports.index');

Route::post('/api/reports', [ReportController::class, 'store'])
    ->middleware(['throttle:10,1'])  // 10 requests per minute
    ->name('api.reports.store');

Route::delete('/reports/reset', [ReportController::class, 'reset'])
    ->name('reports.reset');
```

**Frontend:**
```
/resources/js/Features/Reports/Pages/StockReport.jsx  (submission page)
/resources/js/Features/Reports/Pages/Reports.jsx      (view reports)
```

### 3. Key Files & Their Purpose

#### ReportController.php

**Purpose:** Manages report viewing and submission

**Key Methods:**

1. **`index(Request $request): Response`**
   - Displays paginated reports with filters (outlet, date)
   - Supports legacy `created_at` and new `reported_for_date` columns
   - Returns formatted data to Reports page

2. **`store(Request $request)`**
   - **Primary API endpoint** for report submission
   - Validates request data
   - Verifies items belong to outlet (security check)
   - Verifies user is a barista
   - Creates report records in transaction
   - Returns JSON response

3. **`reset()`**
   - Deletes all reports (development/testing only)
   - Redirects to reports page

#### Report.php Model

**Purpose:** Eloquent model for `report` table

**Relationships:**
```php
public function outlet(): BelongsTo      // belongs to Outlet
public function user(): BelongsTo        // belongs to User (barista)
public function item(): BelongsTo        // belongs to Item
```

**Fillable Fields:**
```php
[
    'outlet_id',
    'user_id',
    'item_id',
    'report_status',       // ALMOST_OUT or OUT
    'reported_for_date',   // Date of report (not created_at)
]
```

#### ReportLine.php Model

**Purpose:** Designed for multi-item reports (currently unused)

**Note:** The current implementation creates one `Report` per item. `ReportLine` was designed for a structure where one report has many line items, but this pattern isn't currently used.

### 4. Data Flow & How It Works

#### Report Submission Flow

```
1. Barista fills out Stock Report form
   - Selects outlet
   - Marks items as ALMOST_OUT or OUT
   ↓
2. Frontend submits POST request
   axios.post('/api/reports', {
     outlet_id: 1,
     user_id: 70,
     items: [
       { item_id: 5, status: 'ALMOST_OUT' },
       { item_id: 12, status: 'OUT' }
     ]
   })
   ↓
3. ReportController@store receives request
   ↓
4. Validation (lines 100-107)
   - outlet_id exists
   - user_id exists
   - items array (min 1, max 100)
   - item_id exists
   - status is ALMOST_OUT or OUT
   ↓
5. Security Check: Item-Outlet Ownership (lines 109-126)
   - Query item_outlet_ownership table
   - Ensure all submitted items belong to selected outlet
   - Return 422 error if any item doesn't belong
   ↓
6. Security Check: User Role (lines 128-135)
   - Verify user exists
   - Verify user.role === 'barista'
   - Return 403 error if not a barista
   ↓
7. Database Transaction (lines 141-162)
   - Loop through each item
   - Create Report record for each item
   - Store report_ids
   ↓
8. Success Response (lines 164-172)
   {
     success: true,
     message: 'Stock report submitted successfully!',
     data: {
       report_ids: [45, 46],
       total_items: 2,
       created_at: '2025-11-12T14:30:00+07:00'
     }
   }
   ↓
9. Frontend receives response
   - Shows success toast
   - Resets form
```

#### Report Viewing Flow (Reports Page)

```
1. Manager visits /reports
   ↓
2. ReportController@index
   ↓
3. Build query with filters:
   - If reported_for_date column exists: filter by date
   - Else: filter by created_at between start/end of day
   - If outlet selected: filter by outlet_id
   ↓
4. Eager load relationships:
   - outlet (id, nama, kode_outlet, icon)
   - user (id, name)
   - item (id, nama)
   ↓
5. Paginate (20 per page)
   ↓
6. Transform for frontend (lines 65-74):
   {
     id: 45,
     submittedAt: '2025-11-12T14:30:00+07:00',
     outlet: 'Outlet A',
     reporter: 'Barista Name',
     item: 'Coffee Beans',
     status: 'ALMOST_OUT'
   }
   ↓
7. Return to React component via Inertia
   ↓
8. Frontend renders table with filters
```

### 5. How to Modify/Add New Features

#### Example 1: Add Notes Field to Reports

**Step 1: Add Migration**

```bash
php artisan make:migration add_notes_to_report_table
```

```php
// database/migrations/xxxx_add_notes_to_report_table.php

public function up()
{
    Schema::table('report', function (Blueprint $table) {
        $table->text('notes')->nullable()->after('report_status');
    });
}

public function down()
{
    Schema::table('report', function (Blueprint $table) {
        $table->dropColumn('notes');
    });
}
```

```bash
php artisan migrate
```

**Step 2: Update Model**

```php
// app/Models/Report.php

protected $fillable = [
    'outlet_id',
    'user_id',
    'item_id',
    'report_status',
    'reported_for_date',
    'notes',  // ADD THIS
];
```

**Step 3: Update Controller Validation**

```php
// ReportController.php - store() method

$validated = $request->validate([
    'outlet_id' => 'required|integer|exists:outlet,id',
    'user_id' => 'required|integer|exists:users,id',
    'items' => 'required|array|min:1|max:100',
    'items.*.item_id' => 'required|integer|exists:item,id',
    'items.*.status' => 'required|in:ALMOST_OUT,OUT',
    'items.*.notes' => 'nullable|string|max:500',  // ADD THIS
]);

// Update report creation
foreach ($validated['items'] as $item) {
    $reportAttributes = [
        'outlet_id' => $validated['outlet_id'],
        'user_id' => $validated['user_id'],
        'item_id' => $item['item_id'],
        'report_status' => $item['status'],
        'notes' => $item['notes'] ?? null,  // ADD THIS
    ];

    if ($supportsReportedDate) {
        $reportAttributes['reported_for_date'] = $reportedForDate;
    }

    $report = Report::create($reportAttributes);
    $createdIds[] = $report->id;
}
```

**Step 4: Update Frontend**

```jsx
// StockReport.jsx

// In state management
const [stockItems, setStockItems] = useState(() =>
    initialStockItems.map((item) => ({
        ...item,
        status: item.ownership?.current_status || 'in_stock',
        report: item.ownership?.current_status === 'out_of_stock' ||
                item.ownership?.current_status === 'almost_out',
        notes: '',  // ADD THIS
    }))
);

// In handleItemToggle or handleStatusChange
const handleNotesChange = (itemId, notes) => {
    setStockItems((prev) =>
        prev.map((item) =>
            item.id === itemId ? { ...item, notes } : item
        )
    );
};

// In JSX (inside the table row for each item)
{item.report && (
    <textarea
        value={item.notes}
        onChange={(e) => handleNotesChange(item.id, e.target.value)}
        placeholder="Add notes (optional)..."
        className="w-full mt-2 p-2 text-sm border rounded"
        rows="2"
        maxLength="500"
    />
)}

// In handleSubmit
const itemsToReport = stockItems
    .filter((item) => item.report)
    .map((item) => ({
        item_id: item.id,
        status: item.status === 'almost_out' ? 'ALMOST_OUT' : 'OUT',
        notes: item.notes || null,  // ADD THIS
    }));
```

#### Example 2: Add Bulk Report Approval

**Step 1: Add Migration**

```bash
php artisan make:migration add_approval_to_report_table
```

```php
public function up()
{
    Schema::table('report', function (Blueprint $table) {
        $table->boolean('approved')->default(false)->after('report_status');
        $table->unsignedBigInteger('approved_by')->nullable()->after('approved');
        $table->timestamp('approved_at')->nullable()->after('approved_by');

        $table->foreign('approved_by')->references('id')->on('users');
    });
}
```

**Step 2: Add Controller Method**

```php
// ReportController.php

public function approve(Request $request)
{
    $validated = $request->validate([
        'report_ids' => 'required|array|min:1',
        'report_ids.*' => 'exists:report,id',
    ]);

    DB::transaction(function () use ($validated, $request) {
        Report::whereIn('id', $validated['report_ids'])
            ->update([
                'approved' => true,
                'approved_by' => $request->user()->id,
                'approved_at' => now('Asia/Jakarta'),
            ]);
    });

    return redirect()
        ->route('reports.index')
        ->with('success', count($validated['report_ids']) . ' reports approved!');
}
```

**Step 3: Add Route**

```php
// web.php
Route::post('/reports/approve', [ReportController::class, 'approve'])
    ->name('reports.approve');
```

**Step 4: Update Frontend**

```jsx
// Reports.jsx

const [selectedReports, setSelectedReports] = useState([]);

const handleApprove = () => {
    router.post(route('reports.approve'), {
        report_ids: selectedReports,
    }, {
        onSuccess: () => {
            setSelectedReports([]);
        },
    });
};

// In JSX - add checkboxes and approval button
<thead>
    <tr>
        <th className="px-6 py-3.5">
            <input
                type="checkbox"
                checked={selectedReports.length === reportsData.length}
                onChange={(e) => {
                    if (e.target.checked) {
                        setSelectedReports(reportsData.map(r => r.id));
                    } else {
                        setSelectedReports([]);
                    }
                }}
            />
        </th>
        {/* ... rest of headers ... */}
    </tr>
</thead>

<tbody>
    {reportsData.map((report) => (
        <tr key={report.id}>
            <td className="px-6 py-4">
                <input
                    type="checkbox"
                    checked={selectedReports.includes(report.id)}
                    onChange={(e) => {
                        if (e.target.checked) {
                            setSelectedReports([...selectedReports, report.id]);
                        } else {
                            setSelectedReports(selectedReports.filter(id => id !== report.id));
                        }
                    }}
                />
            </td>
            {/* ... rest of cells ... */}
        </tr>
    ))}
</tbody>

{selectedReports.length > 0 && (
    <Button onClick={handleApprove} className="mt-4">
        Approve {selectedReports.length} Report{selectedReports.length > 1 ? 's' : ''}
    </Button>
)}
```

### 6. Code Examples

#### Complete Report Submission Example (Backend)

```php
// app/Http/Controllers/Admin/ReportController.php

public function store(Request $request)
{
    // Step 1: Validate request
    $validated = $request->validate([
        'outlet_id' => 'required|integer|exists:outlet,id',
        'user_id' => 'required|integer|exists:users,id',
        'items' => 'required|array|min:1|max:100',
        'items.*.item_id' => 'required|integer|exists:item,id',
        'items.*.status' => 'required|in:ALMOST_OUT,OUT',
        'items.*.action' => 'nullable|string|max:50',
    ]);

    // Step 2: Security - Verify items belong to outlet
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

    // Step 3: Security - Verify user is barista
    $user = \App\Models\User::find($validated['user_id']);
    if (!$user || $user->role !== 'barista') {
        return response()->json([
            'success' => false,
            'message' => 'Only baristas can submit reports.',
        ], 403);
    }

    // Step 4: Get current date in Asia/Jakarta timezone
    $reportedForDate = now('Asia/Jakarta')->toDateString();
    $supportsReportedDate = Schema::hasColumn('report', 'reported_for_date');

    // Step 5: Create reports in transaction
    try {
        $reportIds = DB::transaction(function () use ($validated, $reportedForDate, $supportsReportedDate) {
            $createdIds = [];

            foreach ($validated['items'] as $item) {
                $reportAttributes = [
                    'outlet_id' => $validated['outlet_id'],
                    'user_id' => $validated['user_id'],
                    'item_id' => $item['item_id'],
                    'report_status' => $item['status'], // ALMOST_OUT or OUT
                ];

                // Add reported_for_date if column exists
                if ($supportsReportedDate) {
                    $reportAttributes['reported_for_date'] = $reportedForDate;
                }

                $report = Report::create($reportAttributes);
                $createdIds[] = $report->id;
            }

            return $createdIds;
        });

        // Step 6: Return success response
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
        // Step 7: Handle errors
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
```

#### Complete Frontend Report Submission Example

```jsx
// StockReport.jsx - simplified handleSubmit

const handleSubmit = async (e) => {
    e.preventDefault();

    // Validate
    if (!formData.outletId) {
        toast.error('Please select an outlet');
        return;
    }

    if (!formData.reporterId) {
        toast.error('Please select a reporter');
        return;
    }

    // Get items to report
    const itemsToReport = stockItems
        .filter((item) => item.report)
        .map((item) => ({
            item_id: item.id,
            status: item.status === 'almost_out' ? 'ALMOST_OUT' : 'OUT',
        }));

    if (itemsToReport.length === 0) {
        toast.error('Please mark at least one item as Almost Out or Out of Stock');
        return;
    }

    setIsSubmitting(true);

    try {
        // Submit via axios
        const response = await window.axios.post(route('api.reports.store'), {
            outlet_id: parseInt(formData.outletId),
            user_id: parseInt(formData.reporterId),
            items: itemsToReport,
        });

        if (response.data.success) {
            toast.success(response.data.message);

            // Reset form
            setStockItems((prev) =>
                prev.map((item) => ({
                    ...item,
                    status: item.ownership?.current_status || 'in_stock',
                    report: false,
                }))
            );

            setFormData((prev) => ({ ...prev, reporterId: '' }));
        }
    } catch (error) {
        console.error('Report submission error:', error);

        if (error.response?.status === 422) {
            // Validation error
            toast.error(error.response.data.message);
        } else if (error.response?.status === 403) {
            // Permission error
            toast.error(error.response.data.message);
        } else {
            toast.error('Failed to submit report. Please try again.');
        }
    } finally {
        setIsSubmitting(false);
    }
};
```

### 7. Dependencies & Commands

**Backend Packages (in composer.json):**
```json
{
    "laravel/framework": "^12.0",
    "inertiajs/inertia-laravel": "^1.0",
    "doctrine/dbal": "^3.0"
}
```

**Commands:**
```bash
# Create report migration
php artisan make:migration create_report_table

# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Seed database
php artisan db:seed

# Clear application cache
php artisan cache:clear

# Clear route cache
php artisan route:clear

# View routes
php artisan route:list | grep report
```

---

## Database - Report and ReportLine Models

### 1. Overview

**What they do:**
- **Report Model**: Represents individual stock reports submitted by baristas
- **ReportLine Model**: Designed for multi-item reports (currently unused)

**Why they're important:**
- Store historical stock report data
- Provide audit trail of who reported what and when
- Enable managers to view and analyze stock issues
- Support filtering by outlet, date, user, item

**Current Usage:**
- **Report**: Actively used (one report per item)
- **ReportLine**: Schema exists but not currently in use

### 2. Database Schema

#### `report` Table Structure

```sql
CREATE TABLE `report` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `outlet_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `report_status` varchar(255) NOT NULL,  -- 'ALMOST_OUT' or 'OUT'
  `reported_for_date` date DEFAULT NULL,  -- Added later for better date tracking
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_outlet_id_foreign` (`outlet_id`),
  KEY `report_user_id_foreign` (`user_id`),
  KEY `report_item_id_foreign` (`item_id`),
  CONSTRAINT `report_outlet_id_foreign` FOREIGN KEY (`outlet_id`) REFERENCES `outlet` (`id`),
  CONSTRAINT `report_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `report_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Key Columns:**
- `id`: Primary key
- `outlet_id`: Foreign key to `outlet` table (which outlet)
- `user_id`: Foreign key to `users` table (which barista submitted)
- `item_id`: Foreign key to `item` table (which item was reported)
- `report_status`: Enum-like string (`ALMOST_OUT` or `OUT`)
- `reported_for_date`: Date the report is for (not when it was created)
- `created_at`: Timestamp when record was created

#### `report_line` Table Structure (Unused)

```sql
CREATE TABLE `report_line` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `status` varchar(255) NOT NULL,
  `action` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `report_line_report_id_foreign` (`report_id`),
  KEY `report_line_item_id_foreign` (`item_id`),
  CONSTRAINT `report_line_report_id_foreign` FOREIGN KEY (`report_id`) REFERENCES `report` (`id`) ON DELETE CASCADE,
  CONSTRAINT `report_line_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `item` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Purpose:** Designed for a parent-child relationship where:
- One `report` has many `report_line` items
- Currently unused in favor of multiple `report` records

### 3. Relationships

#### Report Model Relationships

```php
// app/Models/Report.php

class Report extends Model
{
    protected $table = 'report';

    protected $fillable = [
        'outlet_id',
        'user_id',
        'item_id',
        'report_status',
        'reported_for_date',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reported_for_date' => 'date',
    ];

    // Relationships

    /**
     * Get the outlet that owns the report.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Get the user (barista) who created the report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the item associated with this report.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
```

#### Related Tables

**Outlet Table:**
- `id` → `report.outlet_id`
- Contains: nama, kode_outlet, icon

**User Table:**
- `id` → `report.user_id`
- Contains: name, email, role (barista/manager)

**Item Table:**
- `id` → `report.item_id`
- Contains: nama, kategori_id, deleted

### 4. Common Queries

#### Query 1: Get All Reports for Today

```php
use App\Models\Report;
use Carbon\Carbon;

$today = now('Asia/Jakarta')->toDateString();

$reports = Report::query()
    ->where('reported_for_date', $today)
    ->with(['outlet:id,nama', 'user:id,name', 'item:id,nama'])
    ->orderByDesc('created_at')
    ->get();
```

#### Query 2: Get Reports for Specific Outlet

```php
$outletId = 5;

$reports = Report::query()
    ->where('outlet_id', $outletId)
    ->with(['user:id,name', 'item:id,nama'])
    ->get();
```

#### Query 3: Get Out of Stock Reports

```php
$outOfStockReports = Report::query()
    ->where('report_status', 'OUT')
    ->with(['outlet', 'item'])
    ->orderByDesc('created_at')
    ->paginate(20);
```

#### Query 4: Count Reports by Status

```php
use Illuminate\Support\Facades\DB;

$statusBreakdown = Report::query()
    ->select('report_status', DB::raw('COUNT(*) as total'))
    ->groupBy('report_status')
    ->pluck('total', 'report_status');

$almostOutCount = $statusBreakdown['ALMOST_OUT'] ?? 0;
$outCount = $statusBreakdown['OUT'] ?? 0;
```

#### Query 5: Get Reports with Date Range

```php
$startDate = '2025-11-01';
$endDate = '2025-11-30';

$reports = Report::query()
    ->whereBetween('reported_for_date', [$startDate, $endDate])
    ->with(['outlet', 'user', 'item'])
    ->orderBy('reported_for_date', 'desc')
    ->get();
```

### 5. How to Add New Fields

#### Example: Add "Resolved" Status to Reports

**Step 1: Create Migration**

```bash
php artisan make:migration add_resolved_fields_to_report_table
```

```php
// database/migrations/xxxx_add_resolved_fields_to_report_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('report', function (Blueprint $table) {
            $table->boolean('resolved')->default(false)->after('report_status');
            $table->timestamp('resolved_at')->nullable()->after('resolved');
            $table->unsignedBigInteger('resolved_by')->nullable()->after('resolved_at');
            $table->text('resolution_notes')->nullable()->after('resolved_by');

            $table->foreign('resolved_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('report', function (Blueprint $table) {
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['resolved', 'resolved_at', 'resolved_by', 'resolution_notes']);
        });
    }
};
```

**Step 2: Run Migration**

```bash
php artisan migrate
```

**Step 3: Update Model**

```php
// app/Models/Report.php

protected $fillable = [
    'outlet_id',
    'user_id',
    'item_id',
    'report_status',
    'reported_for_date',
    'resolved',           // ADD
    'resolved_at',        // ADD
    'resolved_by',        // ADD
    'resolution_notes',   // ADD
];

protected $casts = [
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'reported_for_date' => 'date',
    'resolved' => 'boolean',     // ADD
    'resolved_at' => 'datetime', // ADD
];

// Add relationship for resolver
public function resolver(): BelongsTo
{
    return $this->belongsTo(User::class, 'resolved_by');
}
```

**Step 4: Add Controller Method**

```php
// ReportController.php

public function resolve(Request $request, Report $report)
{
    $validated = $request->validate([
        'resolution_notes' => 'nullable|string|max:1000',
    ]);

    $report->update([
        'resolved' => true,
        'resolved_at' => now('Asia/Jakarta'),
        'resolved_by' => $request->user()->id,
        'resolution_notes' => $validated['resolution_notes'] ?? null,
    ]);

    return redirect()
        ->route('reports.index')
        ->with('success', 'Report marked as resolved!');
}
```

**Step 5: Add Route**

```php
// web.php
Route::post('/reports/{report}/resolve', [ReportController::class, 'resolve'])
    ->name('reports.resolve');
```

**Step 6: Update Frontend**

```jsx
// Reports.jsx

const handleResolve = (reportId) => {
    router.post(route('reports.resolve', reportId), {
        resolution_notes: 'Restocked',
    });
};

// In table row:
<td className="px-6 py-4">
    {report.resolved ? (
        <span className="text-xs text-green-600">✓ Resolved</span>
    ) : (
        <Button
            variant="outline"
            size="sm"
            onClick={() => handleResolve(report.id)}
        >
            Mark Resolved
        </Button>
    )}
</td>
```

### 6. Migration Examples

#### Create Report Table Migration

```php
// database/migrations/2024_10_14_000005_create_report_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('item_id');
            $table->string('report_status');
            $table->timestamps();

            // Foreign keys
            $table->foreign('outlet_id')
                ->references('id')
                ->on('outlet')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('item_id')
                ->references('id')
                ->on('item')
                ->cascadeOnDelete();

            // Indexes
            $table->index('outlet_id');
            $table->index('user_id');
            $table->index('item_id');
            $table->index('report_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report');
    }
};
```

#### Add reported_for_date Column Migration

```bash
php artisan make:migration add_reported_for_date_to_report_table
```

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('report', function (Blueprint $table) {
            $table->date('reported_for_date')->nullable()->after('report_status');
            $table->index('reported_for_date');
        });

        // Backfill existing records with created_at date
        DB::table('report')
            ->update([
                'reported_for_date' => DB::raw("DATE(created_at)")
            ]);
    }

    public function down()
    {
        Schema::table('report', function (Blueprint $table) {
            $table->dropColumn('reported_for_date');
        });
    }
};
```

### 7. Seeding Example

```php
// database/seeders/ReportSeeder.php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Item;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ReportSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = Outlet::all();
        $baristas = User::where('role', 'barista')->get();
        $items = Item::where('deleted', false)->get();

        if ($outlets->isEmpty() || $baristas->isEmpty() || $items->isEmpty()) {
            $this->command->warn('Missing data: outlets, baristas, or items. Skipping report seeder.');
            return;
        }

        // Create reports for the last 7 days
        for ($day = 0; $day < 7; $day++) {
            $date = now('Asia/Jakarta')->subDays($day)->toDateString();

            // Create 2-5 reports per day
            $reportsCount = rand(2, 5);

            for ($i = 0; $i < $reportsCount; $i++) {
                Report::create([
                    'outlet_id' => $outlets->random()->id,
                    'user_id' => $baristas->random()->id,
                    'item_id' => $items->random()->id,
                    'report_status' => rand(0, 1) ? 'ALMOST_OUT' : 'OUT',
                    'reported_for_date' => $date,
                    'created_at' => Carbon::createFromFormat('Y-m-d', $date, 'Asia/Jakarta')
                        ->addHours(rand(8, 18)) // Random hour between 8 AM - 6 PM
                        ->addMinutes(rand(0, 59)),
                ]);
            }
        }

        $this->command->info('Reports seeded successfully!');
    }
}
```

---

## Auth - User Authentication Flow

### 1. Overview

**What it does:**
Handles user authentication including registration, login, password reset, email verification, and logout. Uses Laravel Breeze + Inertia.js stack.

**Why it's important:**
- Secures the application with role-based access
- Manages user sessions
- Provides password recovery
- Supports email verification

**Who uses it:**
- **All users** (managers and baristas) for login/registration
- **Guests** for public pages

### 2. Location & File Structure

**Routes:**
```
/routes/auth.php         - Authentication routes
/routes/web.php          - Public and authenticated routes
```

**Controllers:**
```
/app/Http/Controllers/Auth/
  ├── AuthenticatedSessionController.php     - Login/Logout
  ├── RegisteredUserController.php           - Registration
  ├── PasswordResetLinkController.php        - Forgot password
  ├── NewPasswordController.php              - Reset password
  ├── ConfirmablePasswordController.php      - Password confirmation
  ├── EmailVerificationPromptController.php  - Email verification
  ├── VerifyEmailController.php              - Email verification handler
  └── PasswordController.php                 - Update password
```

**Frontend Pages:**
```
/resources/js/Features/Auth/Pages/
  ├── Login.jsx
  ├── Register.jsx
  ├── ForgotPassword.jsx
  ├── ResetPassword.jsx
  ├── VerifyEmail.jsx
  └── ConfirmPassword.jsx

/resources/js/Features/Auth/Components/
  └── LoginPane.jsx  - Login component on landing page
```

**Middleware:**
```
/app/Http/Middleware/
  ├── Authenticate.php         - Ensures user is authenticated
  ├── RedirectIfAuthenticated.php  - Redirects authenticated users
  └── HandleInertiaRequests.php    - Shares auth data with frontend
```

**Models:**
```
/app/Models/User.php
```

### 3. Authentication Routes

#### Guest Routes (unauthenticated users)

```php
// routes/auth.php

Route::middleware('guest')->group(function () {
    // Registration
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);

    // Login (redirects to home - login is on landing page via LoginPane)
    Route::get('login', function () {
        return redirect('/');
    })->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Password Reset
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});
```

#### Authenticated Routes

```php
Route::middleware('auth')->group(function () {
    // Email Verification
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Password Confirmation
    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    // Update Password
    Route::put('password', [PasswordController::class, 'update'])
        ->name('password.update');

    // Logout
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
```

### 4. Authentication Flow

#### Login Flow

```
1. User visits landing page (/)
   ↓
2. LoginPane component renders (for guests)
   ↓
3. User enters email and password
   ↓
4. Form submits to POST /login
   ↓
5. AuthenticatedSessionController@store
   - Validates credentials
   - Calls Auth::attempt()
   ↓
6. If successful:
   - Regenerate session ID (prevent session fixation)
   - Redirect to /dashboard
   ↓
7. If failed:
   - Return validation error
   - Show error on form
```

#### Registration Flow

```
1. User visits /register
   ↓
2. Register.jsx component renders
   ↓
3. User fills: name, email, password, role
   ↓
4. Form submits to POST /register
   ↓
5. RegisteredUserController@store
   - Validates input
   - Creates User record
   - Logs user in
   - Triggers email verification (if enabled)
   ↓
6. Redirect to /dashboard
   ↓
7. Email verification notice shown (if needed)
```

#### Password Reset Flow

```
1. User clicks "Forgot Password"
   ↓
2. Visits /forgot-password
   ↓
3. Enters email address
   ↓
4. POST /forgot-password
   ↓
5. PasswordResetLinkController@store
   - Generates password reset token
   - Stores in password_reset_tokens table
   - Sends email with reset link
   ↓
6. User clicks link in email
   ↓
7. Visits /reset-password/{token}
   ↓
8. NewPasswordController@create
   - Validates token
   - Renders reset form
   ↓
9. User enters new password
   ↓
10. POST /reset-password
    ↓
11. NewPasswordController@store
    - Validates token
    - Updates password
    - Invalidates token
    - Logs user in
    ↓
12. Redirect to /dashboard
```

#### Logout Flow

```
1. User clicks Logout button
   ↓
2. POST /logout
   ↓
3. AuthenticatedSessionController@destroy
   - Calls Auth::logout()
   - Invalidates session
   - Regenerates CSRF token
   ↓
4. Redirect to / (landing page)
```

### 5. User Model

```php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',            // 'manager' or 'barista'
        'username',
        'no_telepon',
        'avail_register',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function schedules()
    {
        return $this->hasMany(JadwalShift::class, 'id_user');
    }
}
```

### 6. Sharing Auth Data with Frontend

```php
// app/Http/Middleware/HandleInertiaRequests.php

public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user() ? [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'role' => $request->user()->role,
                // Security: Only send necessary fields (NOT email, phone, etc.)
            ] : null,
        ],
        'flash' => [
            'success' => fn () => $request->session()->get('success'),
            'error' => fn () => $request->session()->get('error'),
        ],
    ];
}
```

### 7. Using Auth in Components

#### Access Auth User in React

```jsx
import { usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { auth } = usePage().props;

    return (
        <div>
            <h1>Welcome, {auth.user?.name}!</h1>
            <p>Role: {auth.user?.role}</p>
        </div>
    );
}
```

#### Conditional Rendering Based on Role

```jsx
export default function Navigation() {
    const { auth } = usePage().props;
    const isManager = auth.user?.role === 'manager';

    return (
        <nav>
            <Link href="/dashboard">Dashboard</Link>
            <Link href="/stock-report">Stock Report</Link>

            {/* Only managers can see this */}
            {isManager && (
                <>
                    <Link href="/reports">View Reports</Link>
                    <Link href="/items/create">Add Item</Link>
                </>
            )}
        </nav>
    );
}
```

#### Login Form Example

```jsx
// LoginPane.jsx (simplified)

import { useForm } from '@inertiajs/react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';

export default function LoginPane() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div>
                <label htmlFor="email">Email</label>
                <Input
                    id="email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    required
                />
                {errors.email && (
                    <p className="text-sm text-red-600">{errors.email}</p>
                )}
            </div>

            <div>
                <label htmlFor="password">Password</label>
                <Input
                    id="password"
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    required
                />
                {errors.password && (
                    <p className="text-sm text-red-600">{errors.password}</p>
                )}
            </div>

            <div className="flex items-center">
                <input
                    id="remember"
                    type="checkbox"
                    checked={data.remember}
                    onChange={(e) => setData('remember', e.target.checked)}
                />
                <label htmlFor="remember" className="ml-2">
                    Remember me
                </label>
            </div>

            <Button type="submit" disabled={processing} className="w-full">
                {processing ? 'Logging in...' : 'Log in'}
            </Button>
        </form>
    );
}
```

### 8. Protecting Routes (Backend)

```php
// routes/web.php

// Public routes (no auth required)
Route::get('/', function () {
    return Inertia::render('LandingPage/Pages/LandingPage');
});

Route::get('/stock-report', [StockReportController::class, 'index'])
    ->name('stock-report.index');

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // All authenticated users
    Route::get('/dashboard', [ItemController::class, 'index'])
        ->name('dashboard');

    // Manager-only routes (add custom middleware if needed)
    Route::middleware(['role:manager'])->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])
            ->name('reports.index');

        Route::resource('items', ItemController::class);
    });
});
```

#### Custom Role Middleware (Optional)

```bash
php artisan make:middleware EnsureUserHasRole
```

```php
// app/Http/Middleware/EnsureUserHasRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if (!$request->user() || $request->user()->role !== $role) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
```

```php
// bootstrap/app.php or app/Http/Kernel.php

protected $middlewareAliases = [
    // ... other middleware
    'role' => \App\Http\Middleware\EnsureUserHasRole::class,
];
```

---

## Common Issues & Solutions

### Issue 1: Inertia Props Not Updating

**Problem:** Component doesn't re-render when data changes on the backend.

**Causes:**
- Not using `preserveScroll` or `preserveState`
- Using `only` option incorrectly
- Caching issues

**Solutions:**

```jsx
// Solution 1: Use preserveScroll and preserveState
router.get(route('dashboard'), filters, {
    preserveScroll: true,  // Keep scroll position
    preserveState: true,   // Preserve component state
    replace: true,         // Replace history instead of push
});

// Solution 2: Use only to fetch specific props
router.get(route('dashboard'), filters, {
    only: ['items', 'stockSummary'],  // Only fetch these props
    preserveScroll: true,
});

// Solution 3: Force full page reload
router.get(route('dashboard'), filters, {
    preserveScroll: false,
    preserveState: false,
});
```

### Issue 2: Form Validation Errors Not Showing

**Problem:** Validation errors from backend don't display on form.

**Causes:**
- Not accessing `errors` from `useForm()`
- Incorrect field names
- Backend not returning errors properly

**Solutions:**

```jsx
// Frontend: Ensure you're using errors from useForm
const { data, setData, post, errors } = useForm({
    nama: '',
    kategori_id: '',
});

// Display errors for each field
{errors.nama && <p className="text-sm text-red-600">{errors.nama}</p>}
{errors.kategori_id && <p className="text-sm text-red-600">{errors.kategori_id}</p>}

// Backend: Ensure validation returns proper errors
public function store(Request $request)
{
    $validated = $request->validate([
        'nama' => 'required|string|max:255',
        'kategori_id' => 'required|exists:kategori,id',
    ]);

    // If validation fails, Laravel automatically returns errors
    // They'll be available in the errors object on frontend
}
```

### Issue 3: Database Relationship Not Loading

**Problem:** Related data (e.g., `item.kategori`) is null even though it exists.

**Causes:**
- Forgetting to eager load relationship
- Incorrect foreign key
- Data doesn't exist

**Solutions:**

```php
// Solution 1: Eager load in query
$items = Item::with('kategori')->get();

// Solution 2: Eager load nested relationships
$reports = Report::with(['outlet', 'user', 'item.kategori'])->get();

// Solution 3: Check foreign key configuration
// In Item model:
public function kategori()
{
    // If foreign key is NOT "kategori_id", specify it:
    return $this->belongsTo(Kategori::class, 'custom_kategori_id');
}

// Solution 4: Debug in controller
$items = Item::with('kategori')->get();
dd($items->first()->kategori); // Should show kategori data
```

### Issue 4: Component Not Re-rendering After State Update

**Problem:** UI doesn't update after `setState`.

**Causes:**
- Mutating state directly
- Not using functional update
- Async timing issues

**Solutions:**

```jsx
// BAD: Mutating state directly
const badUpdate = () => {
    stockItems[0].status = 'out';  // ❌ Don't do this
    setStockItems(stockItems);
};

// GOOD: Create new array/object
const goodUpdate = () => {
    setStockItems((prev) =>
        prev.map((item, index) =>
            index === 0 ? { ...item, status: 'out' } : item
        )
    );
};

// GOOD: Functional update for arrays
const updateItem = (itemId, newStatus) => {
    setStockItems((prev) =>
        prev.map((item) =>
            item.id === itemId
                ? { ...item, status: newStatus }
                : item
        )
    );
};
```

### Issue 5: CSRF Token Mismatch

**Problem:** POST/PUT/DELETE requests fail with 419 error.

**Causes:**
- CSRF token not included
- Session expired
- axios not configured

**Solutions:**

```js
// Solution 1: Ensure bootstrap.js has CSRF config
// resources/js/bootstrap.js
import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

// Solution 2: Ensure blade template has CSRF token
// resources/views/app.blade.php
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

// Solution 3: Manually include in axios request
axios.post('/api/reports', data, {
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
});
```

### Issue 6: Pagination Links Not Working

**Problem:** Clicking pagination doesn't change page.

**Causes:**
- Not using Inertia Link component
- Missing `withQueryString()` on backend
- Not preserving filters

**Solutions:**

```php
// Backend: Use withQueryString() to preserve filters
$items = Item::query()
    ->where('deleted', false)
    ->paginate(20)
    ->withQueryString();  // ← Important!
```

```jsx
// Frontend: Use Inertia Link with preserveScroll
import { Link } from '@inertiajs/react';

<PaginationLink
    href={link.url}
    preserveScroll   // ← Keeps scroll position
    preserveState    // ← Preserves filters
>
    {link.label}
</PaginationLink>
```

### Issue 7: Flash Messages Not Showing

**Problem:** Success/error messages don't appear after form submission.

**Causes:**
- Not sharing flash in `HandleInertiaRequests`
- Not using toast/notification system
- Flash messages cleared too early

**Solutions:**

```php
// Backend: Set flash message
return redirect()
    ->route('dashboard')
    ->with('success', 'Item created successfully!');

// Middleware: Share flash with frontend
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'flash' => [
            'success' => fn () => $request->session()->get('success'),
            'error' => fn () => $request->session()->get('error'),
        ],
    ];
}
```

```jsx
// Frontend: Show toast on flash message
import { usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import { useEffect } from 'react';

export default function Dashboard() {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    // ...
}
```

---

## Best Practices

### 1. File Organization

**DO:**
- Group related components in feature folders (`Features/Inventory/`, `Features/Reports/`)
- Keep reusable components in `Shared/Components/`
- Use consistent naming (PascalCase for components, kebab-case for files)

**DON'T:**
- Mix feature components with shared components
- Create deeply nested folder structures
- Use generic names like `Component1.jsx`

### 2. Inertia.js Patterns

**DO:**
```php
// Pass only necessary data
return Inertia::render('Dashboard', [
    'items' => $items->only(['id', 'nama', 'kategori']),
    'stockSummary' => $stockSummary,
]);

// Use resource transformations
return ItemResource::collection($items);
```

**DON'T:**
```php
// Send full Eloquent models with all relationships
return Inertia::render('Dashboard', [
    'items' => Item::with('everything')->get(),  // ❌ Too much data
]);
```

### 3. State Management

**DO:**
```jsx
// Use functional updates when depending on previous state
setItems((prev) => [...prev, newItem]);

// Derive state when possible
const activeItems = items.filter(item => !item.deleted);
const itemCount = activeItems.length;

// Use useMemo for expensive calculations
const sortedItems = useMemo(() => {
    return items.sort((a, b) => a.nama.localeCompare(b.nama));
}, [items]);
```

**DON'T:**
```jsx
// Mutate state directly
items.push(newItem);  // ❌
setItems(items);      // ❌

// Store derived state unnecessarily
const [items, setItems] = useState([]);
const [activeItems, setActiveItems] = useState([]);  // ❌ Derive instead
```

### 4. Database Queries

**DO:**
```php
// Use eager loading to prevent N+1 queries
$items = Item::with('kategori')->get();

// Select only needed columns
$items = Item::select(['id', 'nama', 'kategori_id'])->get();

// Use indexes for frequently queried columns
// In migration:
$table->index('kategori_id');
$table->index('deleted');
```

**DON'T:**
```php
// Cause N+1 queries
$items = Item::all();
foreach ($items as $item) {
    echo $item->kategori->nama;  // ❌ Queries kategori for each item
}

// Select all columns when not needed
$items = Item::get();  // ❌ Gets all columns
```

### 5. Validation

**DO:**
```php
// Use Form Requests for complex validation
php artisan make:request StoreItemRequest

// StoreItemRequest.php
public function rules()
{
    return [
        'nama' => 'required|string|max:255',
        'kategori_id' => 'required|exists:kategori,id',
        'outlet_ids' => 'required|array|min:1',
        'outlet_ids.*' => 'exists:outlet,id',
    ];
}

// Use in controller
public function store(StoreItemRequest $request)
{
    $validated = $request->validated();
    // ...
}
```

**DON'T:**
```php
// Inline complex validation in controller
public function store(Request $request)
{
    // ❌ Hard to reuse, hard to test
    if (!$request->nama || strlen($request->nama) > 255) {
        return back()->withErrors(['nama' => 'Invalid']);
    }
}
```

### 6. Error Handling

**DO:**
```php
// Log errors with context
try {
    $report = Report::create($data);
} catch (\Exception $e) {
    Log::error('Failed to create report', [
        'error' => $e->getMessage(),
        'data' => $data,
        'user_id' => auth()->id(),
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Failed to create report. Please try again.',
    ], 500);
}
```

**DON'T:**
```php
// Swallow errors silently
try {
    $report = Report::create($data);
} catch (\Exception $e) {
    // ❌ No logging, no user feedback
}
```

### 7. Component Composition

**DO:**
```jsx
// Create small, reusable components
function StatusBadge({ status }) {
    const meta = STATUS_META[status];
    return (
        <span className={`badge ${meta.tone}`}>
            {meta.label}
        </span>
    );
}

// Use composition
function ItemRow({ item }) {
    return (
        <tr>
            <td>{item.nama}</td>
            <td><StatusBadge status={item.status} /></td>
        </tr>
    );
}
```

**DON'T:**
```jsx
// Create giant monolithic components
function Dashboard() {
    // ❌ 1000+ lines of JSX
    // ❌ Everything in one component
    // ❌ Hard to test, hard to maintain
}
```

### 8. Security

**DO:**
```php
// Validate item belongs to outlet before allowing operations
$validItems = DB::table('item_outlet_ownership')
    ->where('outlet_id', $outletId)
    ->whereIn('item_id', $itemIds)
    ->pluck('item_id');

if ($itemIds->diff($validItems)->isNotEmpty()) {
    abort(422, 'Invalid items for this outlet');
}

// Use rate limiting on public endpoints
Route::post('/api/reports', [ReportController::class, 'store'])
    ->middleware(['throttle:10,1']);

// Only send necessary user data to frontend
'user' => $request->user() ? [
    'id' => $request->user()->id,
    'name' => $request->user()->name,
    'role' => $request->user()->role,
] : null,
```

**DON'T:**
```php
// Trust user input without validation
Report::create($request->all());  // ❌ Mass assignment vulnerability

// Expose sensitive data to frontend
'user' => $request->user(),  // ❌ Exposes email, phone, etc.

// Allow unlimited API requests
Route::post('/api/reports', [ReportController::class, 'store']);  // ❌ No rate limit
```

### 9. Performance

**DO:**
```jsx
// Debounce search inputs
useEffect(() => {
    const handler = setTimeout(() => {
        applyFilters({ search: searchTerm });
    }, 400);  // Wait 400ms after typing stops

    return () => clearTimeout(handler);
}, [searchTerm]);

// Use pagination for large datasets
$items = Item::paginate(20);

// Use React.memo for expensive components
const ExpensiveComponent = React.memo(({ data }) => {
    // ...
});
```

**DON'T:**
```jsx
// Make API calls on every keystroke
onChange={(e) => {
    setSearchTerm(e.target.value);
    applyFilters({ search: e.target.value });  // ❌ Too many requests
}}

// Load all data at once
$items = Item::all();  // ❌ Could be thousands of records
```

### 10. TypeScript (Recommended for Large Projects)

While this project uses JavaScript, consider TypeScript for:
- Better autocomplete in IDE
- Catch type errors before runtime
- Self-documenting code

```tsx
// Example with TypeScript
interface Item {
    id: number;
    nama: string;
    kategori_id: number;
    deleted: boolean;
    outlets: Outlet[];
}

interface Props {
    items: PaginatedData<Item>;
    kategoris: Kategori[];
    outlets: Outlet[];
}

export default function Dashboard({ items, kategoris, outlets }: Props) {
    // TypeScript provides autocomplete and type checking
}
```

---

## Conclusion

This documentation covers the core features of the Stock Report application. For additional help:

- **Laravel Docs**: https://laravel.com/docs
- **Inertia.js Docs**: https://inertiajs.com
- **React Docs**: https://react.dev
- **shadcn/ui**: https://ui.shadcn.com

**Next Steps:**
1. Set up development environment
2. Run migrations: `php artisan migrate`
3. Seed database: `php artisan db:seed`
4. Start dev server: `npm run dev` and `php artisan serve`
5. Visit `http://localhost:8000`

For questions or issues, consult the codebase or contact the development team.
