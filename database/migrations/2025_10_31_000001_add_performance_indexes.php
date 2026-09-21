<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds critical indexes to improve query performance:
     * - item_outlet_ownership.current_status: Speeds up stock filtering queries
     * - item.deleted: Speeds up active item queries (WHERE deleted = false)
     * - report(outlet_id, created_at): Speeds up report filtering by outlet and date range
     */
    public function up(): void
    {
        // Use raw SQL to add indexes only if they don't already exist
        DB::statement('CREATE INDEX IF NOT EXISTS idx_item_outlet_ownership_current_status ON item_outlet_ownership(current_status)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_item_deleted ON item(deleted)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_report_outlet_date ON report(outlet_id, created_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes if they exist
        DB::statement('DROP INDEX IF EXISTS idx_item_outlet_ownership_current_status ON item_outlet_ownership');
        DB::statement('DROP INDEX IF EXISTS idx_item_deleted ON item');
        DB::statement('DROP INDEX IF EXISTS idx_report_outlet_date ON report');
    }
};
