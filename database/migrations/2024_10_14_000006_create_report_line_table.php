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
        Schema::create('report_line', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('report')->onDelete('cascade');
            $table->foreignId('item_id')->constrained('item')->onDelete('cascade');
            $table->enum('status', ['READY', 'ALMOST_OUT', 'OUT']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_line');
    }
};

