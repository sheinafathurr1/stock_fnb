<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy value => current value.
     */
    private array $renames = [
        'READY' => 'in_stock',
        'ALMOST_OUT' => 'almost_out',
        'OUT' => 'out_of_stock',
    ];

    /**
     * Run the migrations.
     *
     * The earlier enum migration only widened the column on MySQL. Everywhere
     * else — including the SQLite database .env.example ships with — the
     * column kept a CHECK constraint listing only the old uppercase values,
     * so writing the new lowercase ones failed and no stock could be saved at
     * all. Widening the column to a plain string on every driver fixes that;
     * the allowed values are already enforced by request validation.
     */
    public function up(): void
    {
        if (!Schema::hasTable('item_outlet_ownership')) {
            return;
        }

        Schema::table('item_outlet_ownership', function (Blueprint $table) {
            $table->string('current_status', 20)->default('in_stock')->change();
        });

        // Any rows left behind by a half-applied rename.
        foreach ($this->renames as $legacy => $current) {
            DB::table('item_outlet_ownership')
                ->where('current_status', $legacy)
                ->update(['current_status' => $current]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Only the width changes; restoring a constraint that rejected the data
     * the application writes would just reintroduce the bug.
     */
    public function down(): void
    {
        if (!Schema::hasTable('item_outlet_ownership')) {
            return;
        }

        Schema::table('item_outlet_ownership', function (Blueprint $table) {
            $table->string('current_status', 20)->default('in_stock')->change();
        });
    }
};
