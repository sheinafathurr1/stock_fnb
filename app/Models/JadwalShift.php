<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalShift extends Model
{
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

