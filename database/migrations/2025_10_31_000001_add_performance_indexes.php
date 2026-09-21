<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes to add, keyed by table.
     *
     * - item_outlet_ownership.current_status: stock filtering on the dashboard
     * - item.deleted: active item queries (WHERE deleted = false)
     * - report(outlet_id, created_at): report filtering by outlet and day
     */
    private array $indexes = [
        'item_outlet_ownership' => ['idx_item_outlet_ownership_current_status' => ['current_status']],
        'item' => ['idx_item_deleted' => ['deleted']],
        'report' => ['idx_report_outlet_date' => ['outlet_id', 'created_at']],
    ];

    /**
     * Run the migrations.
     *
     * Uses the schema builder rather than raw DDL: "CREATE INDEX IF NOT EXISTS"
     * is not valid MySQL, which is the documented production database.
     */
    public function up(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $name => $columns) {
                if (Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->indexes as $table => $definitions) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($definitions as $name => $columns) {
                if (!Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($name) {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }
};
