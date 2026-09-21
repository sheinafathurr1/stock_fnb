<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemOutletOwnership extends Model
{
    protected $table = 'item_outlet_ownership';

    protected $fillable = [
        'item_id',
        'outlet_id',
        'current_status',
    ];

    protected $casts = [
        'current_status' => 'string',
    ];

    /**
     * Get the item that owns the ownership.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the outlet that owns the ownership.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}

