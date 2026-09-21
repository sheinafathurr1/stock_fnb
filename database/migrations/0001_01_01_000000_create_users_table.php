<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns this application needs on `users`, for the case where the table
     * is already owned by another app sharing the database.
     *
     * Only nullable columns appear here: adding a NOT NULL column to a table
     * that already has rows would fail.
     */
    private array $optionalColumns = [
        'role' => 'string',
        'username' => 'string',
        'avail_register' => 'string',
        'no_telepon' => 'string',
        'email' => 'string',
        'email_verified_at' => 'timestamp',
    ];

    /**
     * Run the migrations.
     *
     * This app is designed to sit alongside an existing shift-management
     * application on the same database, so every table it creates is created
     * only if absent. Where `users` already exists, its missing columns are
     * added instead, since role-based access needs them.
     */
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            $this->addMissingUserColumns();
        } else {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('role', 50)->nullable();
                $table->string('name', 50)->nullable();
                $table->string('username', 50)->nullable();
                $table->string('avail_register', 50)->nullable();
                $table->string('email')->nullable();
                $table->string('no_telepon')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Bring an existing `users` table up to what this app expects.
     */
    private function addMissingUserColumns(): void
    {
        $missing = array_filter(
            $this->optionalColumns,
            fn ($type, $column) => !Schema::hasColumn('users', $column),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing !== []) {
            Schema::table('users', function (Blueprint $table) use ($missing) {
                foreach ($missing as $column => $type) {
                    $table->{$type}($column)->nullable();
                }
            });
        }

        if (!Schema::hasColumn('users', 'remember_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->rememberToken();
            });
        }

        if (!Schema::hasColumn('users', 'created_at') && !Schema::hasColumn('users', 'updated_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
