<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportLine extends Model
{
    protected $table = 'report_line';

    protected $fillable = [
        'report_id',
        'item_id',
        'status',
        'action',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the report that owns the report line.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Get the item that owns the report line.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}

