<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['external_calendar_id', 'name', 'selected', 'show_private_titles', 'show_location'])]
class CalendarSource extends Model
{
    protected function casts(): array
    {
        return [
            'selected' => 'boolean',
            'show_private_titles' => 'boolean',
            'show_location' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'source_id');
    }
}
