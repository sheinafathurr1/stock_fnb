<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
