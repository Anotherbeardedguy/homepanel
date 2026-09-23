<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['family_member_id', 'chore_occurrence_id', 'kind'])]
class PinEvent extends Model
{
    public const UPDATED_AT = null;

    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'family_member_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(ChoreOccurrence::class, 'chore_occurrence_id');
    }
}
