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
        if (Schema::hasTable('user_outlet_assignments')) {
            return;
        }

        Schema::create('user_outlet_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('outlet_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('outlet_id')
                ->references('id')
                ->on('outlet')
                ->onDelete('cascade');

            // Prevent duplicate assignments
            $table->unique(['user_id', 'outlet_id']);

            // Indexes for faster queries
            $table->index('user_id');
            $table->index('outlet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_outlet_assignments');
    }
};
