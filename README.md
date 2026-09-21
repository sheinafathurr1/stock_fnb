# Stock Report Application

A modern web application built with Laravel and React for managing coffee shop/barista operations, including stock reporting, shift scheduling, and multi-outlet management.

## 🚀 Features

### Core Features
- **Stock Reporting System** - Baristas report stock changes without logging in
- **Multi-Outlet Support** - Each outlet carries its own items and statuses
- **Roster-Based Access Control** - Who may report, and which outlets a manager
  sees, both follow the shift roster
- **Manager Review Queue** - Reports are accepted before they change stock
- **Interactive Dashboard** - Stock overview with search, category and outlet filters
- **Shift Scheduling** - Weekly roster view per outlet

### Stock Management
- **Inventory Tracking** - Per-outlet status for every item
- **Three Reportable States** - Almost Out, Out of Stock, and Back in Stock
  (so an item that ran out can be put back on the shelf)
- **Duplicate Suppression** - An identical report still awaiting review is not
  filed twice
- **Report History** - Filter by outlet and date, with auto-refresh

### User Management
- **Authentication** - Session-based login for managers
- **User Roles** - `manager` and `barista`
- **Profile Management** - User profile editing capabilities

## 🛠 Technology Stack

### Backend
- **Laravel 12** - PHP web framework
- **Inertia.js** - Modern monolithic SPA approach
- **Laravel Sanctum** - API authentication
- **MySQL or SQLite** - MySQL in production; `.env.example` defaults to SQLite

### Frontend
- **React 18** - User interface library
- **Tailwind CSS** - Utility-first CSS framework
- **Vite** - Fast build tool and dev server
- **Axios** - Promise-based HTTP client
- **shadcn/ui** - Component library for React
- **Lucide React** - Beautiful icon library
- **gsap** - Animation library

### Development Tools
- **Composer** - PHP dependency management
- **NPM/PNPM** - Node.js package management
- **Laravel Sail** - Docker development environment
- **Laravel Pint** - PHP code style fixer

## 📋 Prerequisites

Before running this application, make sure you have the following installed:

- **PHP 8.2 or higher**
- **Composer** - PHP dependency manager
- **Node.js 20.19+ or 22.12+** and **npm** or **pnpm** (required by Vite 7; Node 18 will not build)
- **MySQL** - Only if you are not using the default SQLite database
- **Git** - Version control system

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/sheinafathurr/stock_fnb
cd stock_fnb
```

### 2. Install PHP Dependencies
```bash
composer install
```

### 3. Install Node.js Dependencies
```bash
npm install
# or
pnpm install
```

### 4. Environment Configuration
```bash
cp .env.example .env
```

`.env.example` uses SQLite, which needs no setup beyond creating the file:
```bash
touch database/database.sqlite
```

To use MySQL instead, update your `.env` with the appropriate credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 5. Generate Application Key
```bash
php artisan key:generate
```

### 6. Database Setup
```bash
# Run migrations
php artisan migrate

# Seed the database (categories, outlets, items, and demo accounts)
php artisan db:seed
```

Seeding creates the accounts you need to sign in:

| Role | Email | Password |
| --- | --- | --- |
| Manager | `manager@example.com` | `password` |
| Barista | `barista1@example.com` … | `password` |

Each seeded barista is rostered at one outlet for the current day, which is
what makes them selectable on the reporting page.

### 7. Sharing a database with an existing shift application

This app is built to run alongside an existing shift-management app on the
same database — it reads its roster straight from that app's `jadwal_shift`
table. Every table it creates is created only if absent, and where `users`
already exists its missing columns are added rather than the table being
recreated, so `php artisan migrate` is safe to run against that database.

Two things to do afterwards:

```bash
# 1. Seed only the reference data; your users already exist, so skip the
#    demo accounts. These seeders are safe to re-run.
php artisan db:seed --class=KategoriSeeder
php artisan db:seed --class=OutletSeeder
php artisan db:seed --class=ItemSeeder
php artisan db:seed --class=ItemOutletOwnershipSeeder

# 2. Give an existing user the manager role, since roles start out null.
php artisan tinker --execute="App\Models\User::where('email','you@example.com')->update(['role'=>'manager']);"
```

`outlet.kode_outlet` must match the `jadwal_shift.id_outlet` values your shift
app uses — that is what links a roster entry to an outlet. Adjust
`database/seeders/OutletSeeder.php` if your codes differ.

### 8. Build Assets
```bash
npm run build
# or for development
npm run dev
```

## 🎯 Usage

### Starting the Application

#### Using Laravel Sail (Recommended)
```bash
./vendor/bin/sail up -d
./vendor/bin/sail npm run dev
```

#### Using PHP's Built-in Server
```bash
# Terminal 1 - Start Laravel server
php artisan serve

# Terminal 2 - Start Vite dev server
npm run dev

# Terminal 3 - Queue worker (optional)
php artisan queue:work

# Terminal 4 - Laravel Pail (optional)
php artisan pail
```

### Accessing the Application

1. **Homepage**: http://127.0.0.1:8000
2. **Dashboard** (Manager only): http://127.0.0.1:8000/dashboard


## 📊 Database Schema

### Main Tables
- **users** - User accounts with roles (`manager` / `barista`)
- **outlet** - Locations, keyed by `kode_outlet`
- **kategori** - Item categories
- **item** - Stock items (`deleted` acts as a soft-delete flag)
- **item_outlet_ownership** - Which items an outlet carries, and each one's
  `current_status` (`in_stock` / `almost_out` / `out_of_stock`)
- **report** - One row per reported item, with `report_status`
  (`READY` / `ALMOST_OUT` / `OUT`), `reported_for_date` and the acceptance
  fields (`accepted`, `accepted_by`, `accepted_at`)
- **jadwal_shift** - Shift roster; drives both who may report and which
  outlets a user can see

### Laravel System Tables
- **sessions** - User session management
- **cache** - Application caching
- **jobs** - Background job processing
- **migrations** - Database version control

> `report_line`, `schedules` and `user_outlet_assignments` were created by
> earlier migrations but never read or written. They are dropped by
> `2025_11_22_000001_drop_unused_tables`, which is reversible if you need them
> back.

## 👥 User Roles & Permissions

Outlet access is derived from the shift roster (`jadwal_shift`), not from a
separate assignment table: a user sees the outlets they have been scheduled
at, and a user with no roster history sees all of them.

### Manager Role
- Access to the dashboard, reports and schedule pages
- Create, edit and remove items at the outlets they cover
- Accept reports, which applies the reported status to the item
- Delete the reports currently in view (scoped to the outlet/date filters)

### Barista Role
- Submit stock reports from the public `/stock-report` page, without logging in
- Only while rostered at that outlet today with an approved shift

## 🔄 Reporting Flow

1. A barista opens `/stock-report?outlet=<kode_outlet>` and marks each item
   whose status changed — **Almost Out**, **Out of Stock**, or **Back in
   Stock** once it has been restocked.
2. They pick their name from today's approved roster and submit. Submissions
   are rejected unless that barista is actually rostered at that outlet today,
   and an identical report that no manager has acted on yet is not duplicated.
3. A manager reviews the queue on `/reports` and accepts each row, which
   writes the reported status onto the item for that outlet.

## 🔧 Configuration

### Environment Variables
```env
# Application
APP_NAME="Stock Report"
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_DATABASE=your_database

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

### Available Commands

```bash
# Development
php artisan serve          # Start development server
npm run dev               # Start Vite dev server
npm run build             # Build for production

# Database
php artisan migrate       # Run migrations
php artisan migrate:fresh # Fresh migration with seed
php artisan db:seed       # Seed database

# Testing
php artisan test          # Run tests
php artisan pint          # Fix code style

# Queue & Jobs
php artisan queue:work    # Process queued jobs
php artisan pail         # Monitor application logs
```
### Styling
The application uses Tailwind CSS. Customize styles in:
- `resources/css/app.css` - Main stylesheet
- `tailwind.config.js` - Tailwind configuration

### Adding New Outlets
Outlets are read from the `outlet` table, so add a row there (or extend
`database/seeders/OutletSeeder.php`). No frontend change is needed.

## 🔒 Security Features

- **CSRF Protection** - Laravel's built-in CSRF protection
- **Input Sanitization** - All user inputs are sanitized
- **Role-Based Access** - Secure route protection
- **Session Management** - Secure session handling
- **Password Hashing** - Bcrypt password hashing

## 🚨 Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Verify your `.env` database credentials
   - Ensure MySQL server is running
   - Check database user permissions

2. **Asset Compilation Issues**
   ```bash
   rm -rf node_modules package-lock.json
   npm install
   npm run dev
   ```

3. **Permission Issues**
   ```bash
   chmod -R 755 storage bootstrap/cache
   php artisan config:clear
   php artisan cache:clear
   ```

### Code Style
- Follow PSR-12 PHP coding standards
- Use Laravel Pint for code formatting: `php artisan pint`
- ESLint for JavaScript/React code

### Migration from Previous Versions
```bash
php artisan migrate
npm install
npm run build
```

---

**Made with ❤️ using Laravel & React by [K9Fox](https://yafff.tech/)**