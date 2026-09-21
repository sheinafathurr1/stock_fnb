<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'username',
        'no_telepon',
        'avail_register',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relationships
     */

    /**
     * Get the reports this user has created.
     */
    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get the shift schedules for this user.
     */
    public function schedules()
    {
        return $this->hasMany(JadwalShift::class, 'id_user');
    }

    /**
     * Helper Methods for Schedule-Based Outlet Access Control
     */

    /**
     * Get outlet IDs this user is scheduled to work at (all time).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getScheduledOutletIds()
    {
        return $this->schedules()
            ->distinct()
            ->pluck('id_outlet');
    }

    /**
     * Get outlets this user is scheduled to work at (based on schedule history).
     * For managers: All outlets they've ever been scheduled at
     * For baristas: All outlets they've ever been scheduled at
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAccessibleOutlets()
    {
        $outletCodes = $this->getScheduledOutletIds();

        // If no schedule history, show all outlets (fallback for new users)
        if ($outletCodes->isEmpty()) {
            return Outlet::all();
        }

        // Get outlets by kode_outlet (since jadwal_shift uses kode_outlet, not id)
        return Outlet::whereIn('kode_outlet', $outletCodes)->get();
    }

    /**
     * Check if user has an approved shift at a specific outlet today.
     *
     * Mirrors the query behind the public schedule lookup endpoint, so the
     * baristas offered in the reporting dropdown are exactly the ones allowed
     * to submit a report.
     *
     * @param int $outletId - The outlet ID (not kode_outlet)
     * @return bool
     */
    public function isScheduledTodayAtOutlet($outletId)
    {
        // Get outlet's kode_outlet
        $outlet = Outlet::find($outletId);
        if (!$outlet) {
            return false;
        }

        return $this->schedules()
            ->where('id_outlet', $outlet->kode_outlet)
            ->whereDate('tanggal', now('Asia/Jakarta')->toDateString())
            ->where('status', JadwalShift::STATUS_APPROVED)
            ->exists();
    }

    /**
     * Check if user has ever been scheduled at a specific outlet.
     *
     * @param int $outletId - The outlet ID (not kode_outlet)
     * @return bool
     */
    public function hasBeenScheduledAtOutlet($outletId)
    {
        // Get outlet's kode_outlet
        $outlet = Outlet::find($outletId);
        if (!$outlet) {
            return false;
        }

        return $this->schedules()
            ->where('id_outlet', $outlet->kode_outlet)
            ->exists();
    }

    /**
     * Check if user has any schedule history.
     *
     * @return bool
     */
    public function hasScheduleHistory()
    {
        return $this->schedules()->exists();
    }
}
