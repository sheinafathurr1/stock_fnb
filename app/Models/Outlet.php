<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use HasFactory;
    protected $table = 'outlet';

    protected $fillable = [
        'nama',
        'kode_outlet',
        'icon',
    ];

    protected $appends = [
        'short_name',
    ];

    /**
     * Get the outlet name without "Barista" prefix.
     */
    public function getShortNameAttribute(): string
    {
        return str_replace('Barista ', '', $this->nama);
    }

    /**
     * Get the items that belong to this outlet.
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_outlet_ownership')
            ->withPivot('current_status')
            ->withTimestamps();
    }

    /**
     * Get the reports for the outlet.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Get shift schedules for the outlet (jadwal_shift table).
     * Note: Uses kode_outlet as the foreign key
     */
    public function jadwalShift(): HasMany
    {
        return $this->hasMany(JadwalShift::class, 'id_outlet', 'kode_outlet');
    }

    /**
     * Get users scheduled at this outlet (based on jadwal_shift).
     * Note: Returns users who have ever been scheduled here.
     */
    public function scheduledUsers()
    {
        return User::whereHas('schedules', function ($query) {
            $query->where('id_outlet', $this->kode_outlet);
        })->get();
    }

    /**
     * Get baristas scheduled at this outlet.
     */
    public function scheduledBaristas()
    {
        return User::whereRaw('LOWER(role) = ?', ['barista'])
            ->whereHas('schedules', function ($query) {
                $query->where('id_outlet', $this->kode_outlet);
            })->get();
    }

    /**
     * Get managers scheduled at this outlet.
     */
    public function scheduledManagers()
    {
        return User::whereRaw('LOWER(role) = ?', ['manager'])
            ->whereHas('schedules', function ($query) {
                $query->where('id_outlet', $this->kode_outlet);
            })->get();
    }
}
