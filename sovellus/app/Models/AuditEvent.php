<?php

namespace App\Models;

use App\Support\PanelActor;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'device_id',
        'action',
        'subject_type',
        'subject_id',
    ];

    public static function record(string $action, Model $subject, PanelActor $actor): void
    {
        static::query()->create([
            'user_id' => $actor->user?->id,
            'device_id' => $actor->device?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }
}
