<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'token_hash', 'can_complete', 'last_seen_at', 'revoked_at'])]
class Device extends Model
{
    protected function casts(): array
    {
        return [
            'can_complete' => 'boolean',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function pairings(): HasMany
    {
        return $this->hasMany(DevicePairing::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
