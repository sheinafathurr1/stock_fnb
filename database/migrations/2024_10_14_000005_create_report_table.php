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
        Schema::create('report', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlet')->onDelete('cascade');
            $table->string('barista_name');
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->dateTime('submitted_at');
            $table->timestamps();
            
            // Note: Foreign key to jadwal_shift table is not enforced
            // because jadwal_shift is from imported SQL, not migrations
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report');
    }
};

