<?php

namespace App\Models;

use App\Enums\ChoreRecurrence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'assignee_id', 'recurrence', 'weekdays', 'due_on', 'due_time', 'active', 'reward_eur', 'anchor_on', 'interval_days'])]
class Chore extends Model
{
    protected function casts(): array
    {
        return [
            'recurrence' => ChoreRecurrence::class,
            'weekdays' => 'array',
            'due_on' => 'date',
            'active' => 'boolean',
            'reward_eur' => 'decimal:2',
            'anchor_on' => 'date',
        ];
    }

    public function rotates(): bool
    {
        return $this->rotationMembers->isNotEmpty();
    }

    public function assigneeLabel(): string
    {
        if ($this->rotationMembers->isNotEmpty()) {
            return 'Vuoro: '.$this->rotationMembers->pluck('display_name')->join(', ');
        }

        return $this->assignee?->display_name ?? 'Kuka ehtii';
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'assignee_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(ChoreOccurrence::class);
    }

    public function rotationMembers(): BelongsToMany
    {
        return $this->belongsToMany(FamilyMember::class, 'chore_rotation_members')
            ->withPivot('position')
            ->orderBy('chore_rotation_members.position');
    }
}
