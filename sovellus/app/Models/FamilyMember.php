<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

#[Fillable(['display_name', 'color', 'user_id', 'active'])]
#[Hidden(['pin_hash'])]
class FamilyMember extends Model
{
    public const COLORS = [
        '#2F9BFF',
        '#00C2E0',
        '#00C2A8',
        '#22C55E',
        '#84CC16',
        '#F5C400',
        '#FF8A00',
        '#FF3B30',
        '#FF4F8B',
        '#8B5CF6',
        '#D946EF',
        '#E07A3D',
    ];

    public const COLOR_LABELS = [
        '#2F9BFF' => 'Sininen',
        '#00C2E0' => 'Taivaansininen',
        '#00C2A8' => 'Turkoosi',
        '#22C55E' => 'Vihreä',
        '#84CC16' => 'Vaaleanvihreä',
        '#F5C400' => 'Keltainen',
        '#FF8A00' => 'Oranssi',
        '#FF3B30' => 'Punainen',
        '#FF4F8B' => 'Pinkki',
        '#8B5CF6' => 'Violetti',
        '#D946EF' => 'Purppura',
        '#E07A3D' => 'Kupari',
    ];

    /**
     * @return list<string>
     */
    public static function allowedColors(?string $current = null): array
    {
        $colors = self::COLORS;

        if (is_string($current) && preg_match('/^#[0-9A-Fa-f]{6}$/', $current) === 1 && ! in_array(strtoupper($current), $colors, true)) {
            $colors[] = strtoupper($current);
        }

        return $colors;
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'pin_locked_until' => 'datetime',
        ];
    }

    public function assignPin(string $pin): void
    {
        $this->forceFill([
            'pin_hash' => Hash::make($pin),
            'pin_failures' => 0,
            'pin_locked_until' => null,
        ])->save();
    }

    public function hasPin(): bool
    {
        return is_string($this->pin_hash) && $this->pin_hash !== '';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chores(): HasMany
    {
        return $this->hasMany(Chore::class, 'assignee_id');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(FamilyAbsence::class);
    }
}
