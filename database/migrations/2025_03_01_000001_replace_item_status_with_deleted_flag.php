<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->boolean('deleted')
                ->default(false)
                ->after('kategori_id');
        });

        DB::table('item')->update(['deleted' => false]);

        Schema::table('item', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('item', function (Blueprint $table) {
            $table->enum('status', ['ready', 'out'])
                ->default('ready')
                ->after('kategori_id');
        });

        DB::table('item')->update(['status' => 'ready']);

        Schema::table('item', function (Blueprint $table) {
            $table->dropColumn('deleted');
        });
    }
};

