<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('item_outlet_ownership')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE item_outlet_ownership MODIFY current_status VARCHAR(20)");
        }

        DB::table('item_outlet_ownership')
            ->where('current_status', 'READY')
            ->update(['current_status' => 'in_stock']);

        DB::table('item_outlet_ownership')
            ->where('current_status', 'ALMOST_OUT')
            ->update(['current_status' => 'almost_out']);

        DB::table('item_outlet_ownership')
            ->where('current_status', 'OUT')
            ->update(['current_status' => 'out_of_stock']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE item_outlet_ownership MODIFY current_status ENUM('in_stock','almost_out','out_of_stock') NOT NULL DEFAULT 'in_stock'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('item_outlet_ownership')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE item_outlet_ownership MODIFY current_status VARCHAR(20)");
        }

        DB::table('item_outlet_ownership')
            ->where('current_status', 'in_stock')
            ->update(['current_status' => 'READY']);

        DB::table('item_outlet_ownership')
            ->where('current_status', 'almost_out')
            ->update(['current_status' => 'ALMOST_OUT']);

        DB::table('item_outlet_ownership')
            ->where('current_status', 'out_of_stock')
            ->update(['current_status' => 'OUT']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE item_outlet_ownership MODIFY current_status ENUM('READY','ALMOST_OUT','OUT') NOT NULL DEFAULT 'READY'");
        }
    }
};
