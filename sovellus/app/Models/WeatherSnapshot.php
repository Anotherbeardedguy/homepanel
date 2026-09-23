<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'place',
    'observed_at',
    'temperature',
    'wind_ms',
    'forecast_at',
    'forecast_temperature',
    'forecast_feels_like',
    'forecast_wind_ms',
    'symbol',
    'description',
    'next_at',
    'next_temperature',
    'next_description',
    'fetched_at',
])]
class WeatherSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'observed_at' => 'datetime',
            'temperature' => 'decimal:2',
            'wind_ms' => 'decimal:2',
            'forecast_at' => 'datetime',
            'forecast_temperature' => 'decimal:2',
            'forecast_feels_like' => 'decimal:2',
            'forecast_wind_ms' => 'decimal:2',
            'next_at' => 'datetime',
            'next_temperature' => 'decimal:2',
            'fetched_at' => 'datetime',
        ];
    }
}
