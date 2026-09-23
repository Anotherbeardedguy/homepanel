<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'status', 'credentials', 'last_attempt_at', 'last_success_at', 'last_error'])]
class Integration extends Model
{
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'last_attempt_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }

    public static function forProvider(string $provider): self
    {
        return static::query()->firstOrCreate(
            ['provider' => $provider],
            ['status' => 'disabled'],
        );
    }
}
