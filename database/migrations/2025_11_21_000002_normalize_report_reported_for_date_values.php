<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rows written through Eloquent picked up a " 00:00:00" suffix on drivers
     * that store the value verbatim, which made the reports date filter miss
     * them entirely. Trim them back to a bare date; the model now normalises
     * this on write.
     */
    public function up(): void
    {
        if (!Schema::hasTable('report') || !Schema::hasColumn('report', 'reported_for_date')) {
            return;
        }

        DB::table('report')
            ->whereNotNull('reported_for_date')
            ->orderBy('id')
            ->select(['id', 'reported_for_date'])
            ->chunkById(250, function ($reports) {
                foreach ($reports as $report) {
                    $normalized = substr((string) $report->reported_for_date, 0, 10);

                    if ($normalized === (string) $report->reported_for_date) {
                        continue;
                    }

                    DB::table('report')
                        ->where('id', $report->id)
                        ->update(['reported_for_date' => $normalized]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: the previous values were malformed.
     */
    public function down(): void
    {
        //
    }
};
