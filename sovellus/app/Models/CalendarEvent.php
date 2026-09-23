<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source_id',
    'external_event_id',
    'starts_at',
    'ends_at',
    'start_date',
    'end_date',
    'all_day',
    'title',
    'place',
])]
class CalendarEvent extends Model
{
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
            'all_day' => 'boolean',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CalendarSource::class, 'source_id');
    }
}
