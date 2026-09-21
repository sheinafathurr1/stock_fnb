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
            $table->boolean('accepted')->default(false)->after('report_status');
            $table->unsignedBigInteger('accepted_by')->nullable()->after('accepted');
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');

            // Foreign key for accepted_by (manager who accepted)
            $table->foreign('accepted_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report', function (Blueprint $table) {
            $table->dropForeign(['accepted_by']);
            $table->dropColumn(['accepted', 'accepted_by', 'accepted_at']);
        });
    }
};
