<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['place'])]
class WeatherSetting extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'place' => 'Lahti',
        ]);
    }
}
