<?php

namespace App\Models;

use App\Enums\OccurrenceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChoreOccurrence extends Model
{
    protected $fillable = [
        'chore_id',
        'due_date',
        'due_at',
        'status',
        'completed_at',
        'completed_by',
        'completed_by_device_id',
        'completed_by_member_id',
        'assignee_member_id',
        'earned_eur',
        'pin_failures',
        'pin_locked_until',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => OccurrenceStatus::class,
            'earned_eur' => 'decimal:2',
            'pin_locked_until' => 'datetime',
        ];
    }

    public function assigneeMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'assignee_member_id');
    }

    public function completedByMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'completed_by_member_id');
    }

    public function chore(): BelongsTo
    {
        return $this->belongsTo(Chore::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function completedByDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'completed_by_device_id');
    }
}
