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
        if (Schema::hasTable('jadwal_shift')) {
            return;
        }

        Schema::create('jadwal_shift', function (Blueprint $table) {
            $table->id();
            $table->string('id_outlet');
            $table->foreignId('id_user')->nullable()->constrained('users')->nullOnDelete();
            $table->string('id_jam')->nullable();
            $table->string('id_tipe_pekerjaan')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->text('task')->nullable();
            $table->string('task_status')->nullable();
            $table->timestamps();

            $table->index(['id_outlet', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_shift');
    }
};
