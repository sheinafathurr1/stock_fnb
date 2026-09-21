<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
     * Always persist the reporting day as a bare date.
     *
     * The date cast alone lets Eloquent write "2026-09-21 00:00:00", which a
     * plain equality filter on "2026-09-21" never matches on drivers that
     * store what they are given (SQLite). Normalising on write keeps the
     * column comparable without falling back to whereDate(), which would give
     * up the (reported_for_date, outlet_id) index on MySQL.
     */
    protected function reportedForDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : Carbon::parse($value)->toDateString(),
        );
    }

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

