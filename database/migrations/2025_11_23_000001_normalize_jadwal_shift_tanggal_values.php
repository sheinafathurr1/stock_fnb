<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Shifts written through Eloquent picked up a " 00:00:00" suffix on
     * drivers that store the value verbatim. A shift dated the last day of a
     * range then sorted after that range's upper bound, so the weekly
     * schedule screen dropped it. The model now normalises this on write;
     * this trims the rows already stored.
     *
     * Only the format is touched — no row is added, removed or re-dated —
     * because on many deployments this table is populated by an external
     * roster system.
     */
    public function up(): void
    {
        if (!Schema::hasTable('jadwal_shift') || !Schema::hasColumn('jadwal_shift', 'tanggal')) {
            return;
        }

        DB::table('jadwal_shift')
            ->whereNotNull('tanggal')
            ->orderBy('id')
            ->select(['id', 'tanggal'])
            ->chunkById(250, function ($shifts) {
                foreach ($shifts as $shift) {
                    $normalized = substr((string) $shift->tanggal, 0, 10);

                    if ($normalized === (string) $shift->tanggal) {
                        continue;
                    }

                    DB::table('jadwal_shift')
                        ->where('id', $shift->id)
                        ->update(['tanggal' => $normalized]);
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
