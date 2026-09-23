<?php

namespace App\Services;

use App\Enums\OccurrenceStatus;
use App\Models\AuditEvent;
use App\Models\ChoreOccurrence;
use App\Support\PanelActor;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChoreCompletion
{
    public function __construct(private readonly PinCheck $pins) {}

    public function complete(ChoreOccurrence $occurrence, PanelActor $actor, string $pin): ChoreOccurrence
    {
        if (! $actor->canCompleteChores()) {
            throw new AccessDeniedHttpException('Tätä näyttöä ei ole sallittu kuittaamaan kotitöitä.');
        }

        $occurrence->refresh();
        $member = $occurrence->status === OccurrenceStatus::Done
            ? null
            : $this->pins->authorizeCompletion($occurrence, $pin);

        if ($member === null) {
            $this->pins->authorizeReopen($occurrence, $pin);
        }

        return DB::transaction(function () use ($occurrence, $actor, $member): ChoreOccurrence {
            $locked = ChoreOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === OccurrenceStatus::Done) {
                return $locked;
            }

            $locked->load('chore');

            $locked->forceFill([
                'status' => OccurrenceStatus::Done,
                'completed_at' => now(),
                'completed_by' => $actor->user?->id,
                'completed_by_device_id' => $actor->device?->id,
                'completed_by_member_id' => $member->id,
                'earned_eur' => $locked->chore->reward_eur,
                'pin_failures' => 0,
                'pin_locked_until' => null,
            ])->save();

            AuditEvent::record('chore.completed', $locked, $actor);

            return $locked;
        });
    }

    public function reopen(ChoreOccurrence $occurrence, PanelActor $actor, string $pin): ChoreOccurrence
    {
        if (! $actor->canReopenChores()) {
            throw new AccessDeniedHttpException('Vain perheenjäsen voi perua kuittauksen.');
        }

        $occurrence->refresh();
        if ($occurrence->status === OccurrenceStatus::Done) {
            $this->pins->authorizeReopen($occurrence, $pin);
        }

        return DB::transaction(function () use ($occurrence, $actor): ChoreOccurrence {
            $locked = ChoreOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === OccurrenceStatus::Open) {
                return $locked;
            }

            $locked->forceFill([
                'status' => OccurrenceStatus::Open,
                'completed_at' => null,
                'completed_by' => null,
                'completed_by_device_id' => null,
                'completed_by_member_id' => null,
                'earned_eur' => null,
            ])->save();

            AuditEvent::record('chore.reopened', $locked, $actor);

            return $locked;
        });
    }
}
