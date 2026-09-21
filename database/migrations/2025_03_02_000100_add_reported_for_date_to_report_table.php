<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('report')) {
            return;
        }

        Schema::table('report', function (Blueprint $table) {
            if (!Schema::hasColumn('report', 'reported_for_date')) {
                $table->date('reported_for_date')->nullable()->after('report_status');
            }
        });

        DB::table('report')
            ->orderBy('id')
            ->select(['id', 'created_at'])
            ->chunkById(250, function ($reports) {
                foreach ($reports as $report) {
                    $createdAt = $report->created_at
                        ? Carbon::parse($report->created_at, 'UTC')
                        : Carbon::now('UTC');

                    $reportedDate = $createdAt
                        ->setTimezone('Asia/Jakarta')
                        ->toDateString();

                    DB::table('report')
                        ->where('id', $report->id)
                        ->update(['reported_for_date' => $reportedDate]);
                }
            });

        Schema::table('report', function (Blueprint $table) {
            if (!Schema::hasColumn('report', 'reported_for_date')) {
                return;
            }

            $table->index(['reported_for_date', 'outlet_id'], 'report_reported_date_outlet_idx');
            $table->index(['created_at', 'outlet_id'], 'report_created_at_outlet_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('report')) {
            return;
        }

        Schema::table('report', function (Blueprint $table) {
            if (Schema::hasColumn('report', 'reported_for_date')) {
                $table->dropIndex('report_reported_date_outlet_idx');
            }

            if (Schema::hasColumn('report', 'created_at')) {
                $table->dropIndex('report_created_at_outlet_idx');
            }
        });

        Schema::table('report', function (Blueprint $table) {
            if (Schema::hasColumn('report', 'reported_for_date')) {
                $table->dropColumn('reported_for_date');
            }
        });
    }
};
