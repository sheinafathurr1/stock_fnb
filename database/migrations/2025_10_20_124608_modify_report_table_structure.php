<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('report', function (Blueprint $table) {
            // Drop old columns
            $table->dropColumn(['barista_name', 'shift_id', 'submitted_at']);
            
            // Add new columns
            $table->foreignId('user_id')->after('outlet_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('item_id')->after('user_id')->constrained('item')->onDelete('cascade');
            $table->enum('report_status', ['READY', 'ALMOST_OUT', 'OUT'])->after('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report', function (Blueprint $table) {
            // Drop new columns
            $table->dropForeign(['user_id']);
            $table->dropForeign(['item_id']);
            $table->dropColumn(['user_id', 'item_id', 'report_status']);
            
            // Restore old columns
            $table->string('barista_name')->after('outlet_id');
            $table->unsignedBigInteger('shift_id')->nullable()->after('barista_name');
            $table->dateTime('submitted_at')->after('shift_id');
        });
    }
};
