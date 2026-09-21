<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $table = 'report';

    protected $fillable = [
        'outlet_id',
        'user_id',
        'item_id',
        'report_status',
        'reported_for_date',
        'accepted',
        'accepted_by',
        'accepted_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'reported_for_date' => 'date',
        'accepted' => 'boolean',
        'accepted_at' => 'datetime',
    ];

    /**
     * Get the outlet that owns the report.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Get the user (barista) who created the report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the item associated with this report.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the manager who accepted this report.
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}

