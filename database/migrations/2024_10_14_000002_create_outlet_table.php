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
        // This app may share a database with an existing application.
        if (Schema::hasTable('outlet')) {
            return;
        }

        Schema::create('outlet', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode_outlet')->unique();
            $table->string('icon')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outlet');
    }
};

