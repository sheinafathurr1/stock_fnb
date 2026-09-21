<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $table = 'kategori';

    protected $fillable = [
        'nama',
    ];

    /**
     * Get the items for the kategori.
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'kategori_id');
    }
}

