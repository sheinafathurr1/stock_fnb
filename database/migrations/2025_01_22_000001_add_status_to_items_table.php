<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add 'status' column to items table to track item availability.
     * Values: 'ready' (in stock) or 'out' (out of stock)
     */
    public function up(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->enum('status', ['ready', 'out'])
                  ->default('ready')
                  ->after('kategori_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
