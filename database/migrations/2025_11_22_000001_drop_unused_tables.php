<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Three tables were created but never read or written by the application:
     *
     * - report_line: a parent/child report design that was abandoned when
     *   `report` became one row per item.
     * - schedules: superseded by `jadwal_shift`, which is what every access
     *   check and the schedule screen actually query.
     * - user_outlet_assignments: outlet access is derived from the shift
     *   roster, so this never had a reader.
     *
     * Dropped child-first so the foreign keys unwind cleanly.
     */
    public function up(): void
    {
        Schema::dropIfExists('report_line');
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('user_outlet_assignments');
    }

    /**
     * Reverse the migrations.
     *
     * Recreates the tables as their original migrations defined them. The data
     * is gone either way — these tables were always empty in practice — but a
     * rollback should still leave the schema where it found it.
     */
    public function down(): void
    {
        if (!Schema::hasTable('report_line')) {
            Schema::create('report_line', function (Blueprint $table) {
                $table->id();
                $table->foreignId('report_id')->constrained('report')->onDelete('cascade');
                $table->foreignId('item_id')->constrained('item')->onDelete('cascade');
                $table->enum('status', ['READY', 'ALMOST_OUT', 'OUT']);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('schedules')) {
            Schema::create('schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('outlet_id')->constrained('outlet')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('shift_date');
                $table->timestamps();

                $table->unique(['outlet_id', 'user_id', 'shift_date'], 'schedules_unique_shift');
            });
        }

        if (!Schema::hasTable('user_outlet_assignments')) {
            Schema::create('user_outlet_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('outlet_id');
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('outlet_id')->references('id')->on('outlet')->onDelete('cascade');

                $table->unique(['user_id', 'outlet_id']);
                $table->index('user_id');
                $table->index('outlet_id');
            });
        }
    }
};
