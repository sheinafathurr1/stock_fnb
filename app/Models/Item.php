<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $table = 'item';

    protected $fillable = [
        'nama',
        'kategori_id',
        'deleted',
    ];

    protected $casts = [
        'deleted' => 'boolean',
    ];

    /**
     * Get the kategori that owns the item.
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    /**
     * Get the outlets that own this item.
     */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'item_outlet_ownership')
            ->withPivot('current_status')
            ->withTimestamps();
    }

    /**
     * Get the ownership records for this item.
     */
    public function ownerships(): HasMany
    {
        return $this->hasMany(ItemOutletOwnership::class);
    }

    /**
     * Get the report lines for the item.
     */
    public function reportLines(): HasMany
    {
        return $this->hasMany(ReportLine::class);
    }
}

