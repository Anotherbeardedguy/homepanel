<?php

namespace App\Services;

use App\Models\ChoreOccurrence;
use App\Models\FamilyMember;
use App\Models\PinEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PinCheck
{
    public function authorizeCompletion(ChoreOccurrence $occurrence, string $pin): FamilyMember
    {
        $occurrence->loadMissing(['assigneeMember', 'chore.assignee']);
        $expected = $occurrence->assigneeMember ?? $occurrence->chore->assignee;

        if ($expected !== null) {
            return $this->expect($expected, $occurrence, $pin);
        }

        return $this->matchAny($occurrence, $pin);
    }

    public function authorizeReopen(ChoreOccurrence $occurrence, string $pin): void
    {
        $member = $occurrence->completedByMember;

        if ($member === null) {
            throw ValidationException::withMessages([
                'pin' => 'Kuittauksella ei ole tekijää.',
            ]);
        }

        $this->expect($member, $occurrence, $pin);
    }

    private function expect(FamilyMember $member, ChoreOccurrence $occurrence, string $pin): FamilyMember
    {
        if ($member->pin_locked_until !== null && $member->pin_locked_until->isFuture()) {
            throw ValidationException::withMessages(['pin' => 'Odota hetki ja yritä uudelleen.']);
        }

        if (! $member->hasPin() || ! Hash::check($pin, (string) $member->pin_hash)) {
            $this->registerFailure($member, $occurrence);

            throw ValidationException::withMessages([
                'pin' => $member->fresh()->pin_locked_until?->isFuture()
                    ? 'Odota hetki ja yritä uudelleen.'
                    : 'Väärä PIN.',
            ]);
        }

        $member->forceFill(['pin_failures' => 0, 'pin_locked_until' => null])->save();

        return $member;
    }

    private function matchAny(ChoreOccurrence $occurrence, string $pin): FamilyMember
    {
        if ($occurrence->pin_locked_until !== null && $occurrence->pin_locked_until->isFuture()) {
            throw ValidationException::withMessages(['pin' => 'Odota hetki ja yritä uudelleen.']);
        }

        $members = FamilyMember::query()->where('active', true)->whereNotNull('pin_hash')->get();

        foreach ($members as $member) {
            if (! Hash::check($pin, (string) $member->pin_hash)) {
                continue;
            }

            if ($member->pin_locked_until !== null && $member->pin_locked_until->isFuture()) {
                throw ValidationException::withMessages(['pin' => 'Odota hetki ja yritä uudelleen.']);
            }

            $member->forceFill(['pin_failures' => 0, 'pin_locked_until' => null])->save();

            return $member;
        }

        $this->registerAnonymousFailure($occurrence);

        throw ValidationException::withMessages([
            'pin' => $occurrence->fresh()->pin_locked_until?->isFuture()
                ? 'Odota hetki ja yritä uudelleen.'
                : 'Väärä PIN.',
        ]);
    }

    private function registerFailure(FamilyMember $member, ChoreOccurrence $occurrence): void
    {
        $failures = (int) $member->pin_failures + 1;
        $locked = $failures >= 5;
        $member->forceFill([
            'pin_failures' => $locked ? 0 : $failures,
            'pin_locked_until' => $locked ? now()->addMinutes(5) : null,
        ])->save();

        PinEvent::query()->create([
            'family_member_id' => $member->id,
            'chore_occurrence_id' => $occurrence->id,
            'kind' => $locked ? 'locked' : 'wrong',
        ]);
    }

    private function registerAnonymousFailure(ChoreOccurrence $occurrence): void
    {
        $failures = (int) $occurrence->pin_failures + 1;
        $locked = $failures >= 5;
        $occurrence->forceFill([
            'pin_failures' => $locked ? 0 : $failures,
            'pin_locked_until' => $locked ? now()->addMinutes(5) : null,
        ])->save();

        PinEvent::query()->create([
            'family_member_id' => null,
            'chore_occurrence_id' => $occurrence->id,
            'kind' => $locked ? 'locked' : 'wrong',
        ]);
    }
}
