<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class JadwalShift extends Model
{
    /**
     * Status a shift must carry before it counts as a real, working shift.
     */
    public const STATUS_APPROVED = 'Approve';

    protected $table = 'jadwal_shift';

    protected $fillable = [
        'id_jam',
        'id_tipe_pekerjaan',
        'id_outlet',
        'tanggal',
        'id_user',
        'status',
        'check_in_time',
        'check_out_time',
        'task',
        'task_status'
    ];

    protected $casts = [
        'tanggal' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
    ];

    /**
     * Always persist the shift day as a bare date.
     *
     * The date cast alone lets Eloquent write "2026-09-27 00:00:00", which
     * compares as greater than "2026-09-27" on drivers that store the value
     * verbatim. That silently dropped shifts falling on the last day of the
     * range from the weekly schedule screen, and stopped firstOrCreate from
     * ever matching an existing shift.
     */
    protected function tanggal(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : Carbon::parse($value)->toDateString(),
        );
    }

    /**
     * Limit the query to approved shifts on the given date.
     */
    public function scopeApprovedOn($query, string $date, string $outletCode)
    {
        return $query->where('id_outlet', $outletCode)
            ->whereDate('tanggal', $date)
            ->where('status', self::STATUS_APPROVED);
    }

    /**
     * Get the user assigned to this shift.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    /**
     * Get the outlet for this shift.
     * Note: Relationship uses kode_outlet (string) not id
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'id_outlet', 'kode_outlet');
    }
}

